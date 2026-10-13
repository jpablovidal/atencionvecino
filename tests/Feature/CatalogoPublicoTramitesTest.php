<?php

namespace Tests\Feature;

use App\Models\Tramite;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->get('/')->assertOk()->assertSee('Buscar trámites')->assertSee('/tramites', false);
        config(['sgd.validar_rate_per_minute' => 2]);
        $this->get('/tramites')->assertOk();
        $this->get('/tramites')->assertOk();
        $this->get('/tramites')->assertStatus(429);
    }
}
