<?php

namespace Tests\Feature;

use App\Models\Tramite;
use App\Services\ExpedienteService;
use App\Services\SolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class EspacioDualTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function loginDev(string $via, string $run)
    {
        $this->withSession(['cu' => ['state' => hash('sha256', 'st'), 'via' => $via, 'exp' => time() + 600]]);

        return $this->post('/dev/login', ['run' => $run, 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'via' => $via, 'state' => 'st']);
    }

    private function dual(?string $espacio = null)
    {
        $u = $this->usuario('funcionario');
        $s = ['ciudadano' => ['run' => $u->run, 'nombres' => 'Ana', 'apellidos' => 'Pérez'], 'login_at' => time(), 'last_seen' => time()];
        if ($espacio) {
            $s['espacio'] = $espacio;
        }

        return [$this->actingAs($u)->withSession($s), $u];
    }

    public function test_funcionario_que_ingresa_con_claveunica_queda_con_sesion_dual_y_va_al_inicio_dual(): void
    {
        $u = $this->usuario('funcionario');
        $this->loginDev('funcionario', $u->run)->assertRedirect('/espacio');
        $this->assertAuthenticatedAs($u);
        $this->assertSame($u->run, session('ciudadano.run'));
    }

    public function test_vecino_sin_cuenta_de_funcionario_no_obtiene_sesion_de_funcionario(): void
    {
        $this->loginDev('ciudadano', '20.000.001-3')->assertRedirect('/ciudadano');
        $this->assertGuest();
        $this->get('/app')->assertRedirect('/ingreso');
        $this->get('/espacio')->assertRedirect('/ciudadano');
    }

    public function test_via_ciudadano_de_un_funcionario_no_le_da_acceso_a_la_bandeja(): void
    {
        $u = $this->usuario('funcionario');
        $this->loginDev('ciudadano', $u->run)->assertRedirect('/ciudadano');
        $this->assertGuest();
        $this->get('/app')->assertRedirect('/ingreso');
    }

    public function test_inicio_dual_ofrece_ambos_espacios_y_no_tiene_menu_lateral(): void
    {
        [$t] = $this->dual('app');
        $html = $t->get('/espacio')->assertOk()->assertSee('Espacio funcionario')->assertSee('Espacio vecino(a)')->getContent();
        $this->assertStringNotContainsString('sgd-sidebar', $html);
        $this->assertStringContainsString(route('app.bandeja'), $html);
        $this->assertStringContainsString(route('ciudadano.index'), $html);
        $this->assertNull(session('espacio'));
    }

    public function test_el_espacio_se_fija_por_la_ruta_y_el_menu_se_adapta(): void
    {
        [$t] = $this->dual();
        $app = $t->get('/app')->assertOk()->getContent();
        $this->assertSame('app', session('espacio'));
        $this->assertStringContainsString('Mi trabajo', $app);
        $this->assertStringContainsString('sgd-sidebar', $app);
        $this->assertStringContainsString('Cambiar de espacio', $app);
        $this->assertStringContainsString('Ir a mis trámites', $app);
        $ciu = $this->get('/ciudadano')->assertOk()->getContent();
        $this->assertSame('ciudadano', session('espacio'));
        $this->assertStringNotContainsString('sgd-sidebar', $ciu, 'el espacio vecino usa el portal, sin menú lateral');
        $this->assertStringContainsString('portal-hero', $ciu);
        $this->assertStringContainsString('Mis solicitudes', $ciu);
        $this->assertStringNotContainsString('Documentos vigentes', $ciu);
        $this->assertStringContainsString('Cambiar de espacio', $ciu);
        $this->assertStringContainsString('Ir a mi trabajo', $ciu);
    }

    public function test_solo_funcionarios_ven_cambiar_de_espacio_en_el_menu(): void
    {
        $this->comoCiudadano()->get('/ciudadano')->assertOk()->assertDontSee('Cambiar de espacio');
        auth()->forgetGuards();
        $this->flushSession();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertOk()->assertDontSee('Cambiar de espacio');
    }

    public function test_el_espacio_ciudadano_de_un_funcionario_solo_muestra_sus_propias_solicitudes(): void
    {
        $u = $this->usuario('funcionario');
        $ajena = $this->solicitudDOM(); // de otra persona
        [$t] = $this->dual('ciudadano');
        $t->get('/ciudadano')->assertOk()->assertDontSee($ajena->folio);
        $t->get('/ciudadano/solicitudes/'.$ajena->folio)->assertNotFound();
    }

    public function test_catalogo_lleva_al_formulario_desde_la_sesion_dual(): void
    {
        [$t] = $this->dual();
        $t->get('/tramites/1/iniciar')->assertRedirect('/ciudadano/nueva/1');
    }

    public function test_conflicto_de_interes_no_tramita_como_funcionario_lo_que_pidio_como_vecino(): void
    {
        $u = $this->usuario('funcionario', 1);
        $s = app(SolicitudService::class)->ingresar(['run' => $u->run, 'nombres' => 'Ana', 'apellidos' => 'Pérez'], Tramite::query()->with('anexos')->findOrFail(1), 'a@example.com', 'Solicito permiso de edificación para mi terreno.', 'correo', [1 => $this->archivo('p.pdf'), 2 => $this->archivo('d.pdf')], []);
        $u->forceFill(['unidad_id' => $s->tramite->unidades()->value('unidades.id')])->save();
        $this->comoFuncionario($u)->get('/app')->assertDontSee($s->folio);
        $this->get('/app/solicitudes/'.$s->folio)->assertForbidden();
        $this->post('/app/solicitudes/'.$s->folio.'/asignar')->assertForbidden();
        // otro funcionario de la misma unidad sí puede
        $otro = $this->usuario('funcionario', 2);
        $otro->forceFill(['unidad_id' => $u->unidad_id])->save();
        auth()->forgetGuards();
        $this->comoFuncionario($otro)->get('/app/solicitudes/'.$s->folio)->assertOk();
        // y una vez abierto el expediente, quien lo pidió tampoco lo opera ni lo ve como funcionario
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s->refresh(), $otro);
        auth()->forgetGuards();
        $this->comoFuncionario($u)->get('/app/expedientes/'.$e->id)->assertForbidden();
        $this->post('/app/expedientes/'.$e->id.'/comentarios', ['texto' => 'x'])->assertForbidden();
        $this->actingAs($u)->withSession(['ciudadano' => ['run' => $u->run, 'nombres' => 'A', 'apellidos' => 'B'], 'login_at' => time(), 'last_seen' => time()])->get('/ciudadano/solicitudes/'.$s->folio)->assertOk();
    }

    public function test_desactivar_el_inicio_dual_restablece_sesiones_separadas(): void
    {
        config(['sgd.espacio_dual' => false]);
        $u = $this->usuario('funcionario');
        $this->loginDev('funcionario', $u->run)->assertRedirect('/app');
        $this->assertNull(session('ciudadano'));
    }

    public function test_cerrar_sesion_borra_ambas_identidades(): void
    {
        [$t] = $this->dual('app');
        $t->post('/salir')->assertRedirect('/');
        $this->assertNull(session('ciudadano'));
        $this->assertNull(session('espacio'));
        $this->assertGuest();
    }

    public function test_un_valor_de_espacio_manipulado_no_concede_nada(): void
    {
        $this->withSession(['ciudadano' => $this->ciudadano(), 'espacio' => 'app', 'login_at' => time(), 'last_seen' => time()])->get('/app')->assertRedirect('/ingreso');
        $this->get('/ciudadano')->assertOk()->assertDontSee('Documentos vigentes');
    }
}
