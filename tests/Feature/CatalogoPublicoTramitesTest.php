<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\Tramite;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\Escenario;
use Tests\TestCase;

class CatalogoPublicoTramitesTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_catalogo_publico_sin_sesion_lista_solo_publicados(): void
    {
        $dir = Usuario::query()->where('rol', 'dir_tramites')->firstOrFail();
        $this->comoFuncionario($dir)->post('/app/tramites', ['nombre' => 'Borrador secreto', 'plazo_dias' => 5, 'descripcion' => 'Descripción suficientemente larga para este borrador.'])->assertSessionHas('ok');
        $dis = Tramite::query()->find(2);
        $dis->forceFill(['estado' => 'deshabilitado', 'activo' => false])->save();
        $r = $this->get('/tramites')->assertOk();
        $r->assertSee('Permiso de edificación')->assertSee('Solicitud de beneficio social');
        $r->assertDontSee('Borrador secreto')->assertDontSee('Certificado de número');
        $this->assertDatabaseHas('audit_log', ['accion' => 'publico.tramites']);
    }

    public function test_ficha_publica_solo_de_publicados_y_url_canonica(): void
    {
        $t = Tramite::query()->find(1);
        $this->get("/tramites/{$t->id}")->assertRedirect("/tramites/{$t->id}/permiso-de-edificacion");
        $this->get("/tramites/{$t->id}/otro-slug")->assertRedirect("/tramites/{$t->id}/permiso-de-edificacion");
        $this->get("/tramites/{$t->id}/permiso-de-edificacion")->assertOk()->assertSee('Planos de arquitectura')->assertSee('Realizar este trámite en línea');
        $t->forceFill(['estado' => 'borrador', 'activo' => false])->save();
        $this->get("/tramites/{$t->id}/permiso-de-edificacion")->assertNotFound();
        $this->get("/tramites/{$t->id}")->assertNotFound();
        $this->get('/tramites/9999')->assertNotFound();
    }

    public function test_busqueda_y_filtro_por_direccion_con_comodines_escapados(): void
    {
        $this->get('/tramites?q=edificaci')->assertSee('Permiso de edificación')->assertDontSee('beneficio social');
        $this->get('/tramites?direccion=4')->assertSee('beneficio social')->assertDontSee('Permiso de edificación');
        $this->get('/tramites?q=%25')->assertOk()->assertDontSee('Permiso de edificación'); // % literal, no comodín
        $this->get('/tramites?q='.str_repeat('a', 101))->assertSessionHasErrors('q');
        $this->get('/tramites?direccion=abc')->assertSessionHasErrors('direccion');
    }

    public function test_textos_se_escapan_y_no_se_filtran_datos_internos(): void
    {
        $t = Tramite::query()->find(1);
        $t->forceFill(['nombre' => 'Trámite <script>alert(1)</script>', 'descripcion' => '<img src=x onerror=alert(1)>'])->save();
        $html = $this->get('/tramites')->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('DOM-PE', $this->get("/tramites/{$t->id}/tramite-script-alert-1-script")->getContent()); // código interno no se publica
    }

    public function test_enlaces_desde_inicio_y_menu_y_throttle(): void
    {
        $this->get('/')->assertOk()->assertSee('Trámites más solicitados')->assertSee('role="search"', false)->assertSee('/tramites', false);
        config(['sgd.validar_rate_per_minute' => 2]);
        $this->get('/tramites')->assertOk();
        $this->get('/tramites')->assertOk();
        $this->get('/tramites')->assertStatus(429);
    }

    public function test_recuerda_el_tramite_elegido_tras_ingresar(): void
    {
        $this->get('/tramites/1/iniciar')->assertRedirect('/ingreso')->assertSessionHas('tramite_pendiente');
        // ingreso del ciudadano (ClaveÚnica simulada): vuelve directo al formulario del trámite elegido
        $cu = ['run' => '20000001-3', 'nombres' => 'Vecina', 'apellidos' => 'De Prueba'];
        $this->withSession(['tramite_pendiente' => ['id' => 1, 'exp' => time() + 600]]);
        $r = $this->app->make(AuthController::class);
        $m = new \ReflectionMethod($r, 'iniciar');
        $req = Request::create('/x');
        $req->setLaravelSession($this->app['session.store']);
        $resp = $m->invoke($r, $req, 'ciudadano', $cu);
        $this->assertStringEndsWith('/ciudadano/nueva/1', $resp->getTargetUrl());
        $this->assertFalse($req->session()->has('tramite_pendiente')); // de un solo uso
    }

    public function test_trámite_pendiente_vencido_o_ya_no_publicado_no_se_retoma(): void
    {
        $r = $this->app->make(AuthController::class);
        $m = new \ReflectionMethod($r, 'iniciar');
        $cu = ['run' => '20000001-3', 'nombres' => 'Vecina', 'apellidos' => 'De Prueba'];
        foreach ([['id' => 1, 'exp' => time() - 5], ['id' => 999, 'exp' => time() + 600]] as $pend) {
            $req = Request::create('/x');
            $req->setLaravelSession($this->app['session.store']);
            $req->session()->put('tramite_pendiente', $pend);
            $resp = $m->invoke($r, $req, 'ciudadano', $cu);
            $this->assertStringEndsWith('/ciudadano', $resp->getTargetUrl());
        }
        $t = Tramite::query()->find(1);
        $t->forceFill(['estado' => 'deshabilitado', 'activo' => false])->save();
        $this->get('/tramites/1/iniciar')->assertNotFound();
    }

    public function test_con_sesion_de_ciudadano_va_directo_al_formulario_y_funcionario_no_arrastra_el_pendiente(): void
    {
        $this->comoCiudadano()->get('/tramites/1/iniciar')->assertRedirect('/ciudadano/nueva/1');
        $this->get('/tramites/abc/iniciar')->assertNotFound();
        $r = $this->app->make(AuthController::class);
        $m = new \ReflectionMethod($r, 'iniciar');
        $req = Request::create('/x');
        $req->setLaravelSession($this->app['session.store']);
        $req->session()->put('tramite_pendiente', ['id' => 1, 'exp' => time() + 600]);
        $f = $this->usuario('funcionario');
        $resp = $m->invoke($r, $req, 'funcionario', ['run' => $f->run, 'nombres' => 'x', 'apellidos' => 'y']);
        $this->assertStringEndsWith('/espacio', $resp->getTargetUrl());
    }
}
