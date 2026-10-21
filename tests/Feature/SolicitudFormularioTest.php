<?php

namespace Tests\Feature;

use App\Http\Controllers\CiudadanoController;
use App\Models\Solicitud;
use App\Services\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Formulario de nueva solicitud: contador del detalle (2000), anexos con vista previa y consentimiento de datos personales. */
class SolicitudFormularioTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private function base(array $extra = []): array
    {
        return array_merge(['tramite_id' => 2, 'correo' => 'a@b.cl', 'detalle' => 'Solicito certificado de número.', 'consentimiento' => 'si', 'canal_notificacion' => 'correo',
            'anexo_4' => $this->archivo('cedula.pdf', $this->pdfValido())], $extra);
    }

    public function test_la_pagina_trae_contador_anexos_y_consentimiento(): void
    {
        $this->sembrar();
        $r = $this->comoCiudadano()->get('/ciudadano/nueva/2')->assertOk();
        $r->assertSee('data-contador="2000"', false)->assertSee('Máximo 2.000 caracteres', false);
        $r->assertSee('data-anexo=', false)->assertSee('id="sgd-visor"', false)->assertSee('solicitud-form.js', false);
        $r->assertSee('name="consentimiento"', false)->assertSee('Decreto Alcaldicio N° 2590', false)->assertSee('Sí, consiento', false)->assertSee('No consiento', false);
    }

    public function test_el_detalle_admite_2000_y_rechaza_2001_caracteres(): void
    {
        $this->sembrar();
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $this->base(['detalle' => str_repeat('á', 2001)]))->assertSessionHasErrors('detalle');
        $this->assertDatabaseCount('solicitudes', 0);
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $this->base(['detalle' => str_repeat('á', 2000)]))->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('solicitudes', 1);
    }

    public function test_los_saltos_de_linea_cuentan_como_un_caracter_igual_que_en_el_navegador(): void
    {
        $this->sembrar();
        $texto = str_repeat("linea\r\n", 285).'fin'; // 285*6+3 = 1713 caracteres con \n, 1998 con \r\n
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $this->base(['detalle' => $texto]))->assertSessionDoesntHaveErrors();
        $this->assertSame(1713, mb_strlen(Solicitud::query()->firstOrFail()->detalle));
    }

    public function test_el_consentimiento_queda_registrado_con_fecha_y_version(): void
    {
        $this->sembrar();
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $this->base())->assertSessionDoesntHaveErrors();
        $s = Solicitud::query()->firstOrFail();
        $this->assertTrue($s->consentimiento_datos);
        $this->assertNotNull($s->consentimiento_en);
        $this->assertSame(CiudadanoController::CONSENTIMIENTO_VERSION, $s->consentimiento_version);
        $this->assertDatabaseHas('audit_log', ['accion' => 'solicitud.ingreso']);
    }

    public function test_sin_consentimiento_igual_se_ingresa_pero_queda_marcado(): void
    {
        $this->sembrar();
        $this->comoCiudadano()->post('/ciudadano/solicitudes', array_diff_key($this->base(), ['consentimiento' => 1]))->assertSessionHasErrors('consentimiento');
        $this->assertDatabaseCount('solicitudes', 0);
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $this->base(['consentimiento' => 'no']))->assertSessionDoesntHaveErrors();
        $s = Solicitud::query()->firstOrFail();
        $this->assertFalse($s->consentimiento_datos);
        $this->assertNotNull($s->consentimiento_en);
        $this->assertDatabaseHas('audit_log', ['accion' => 'solicitud.sin_consentimiento']);
        $this->comoCiudadano()->get('/ciudadano/solicitudes/'.$s->folio)->assertOk()->assertSee('no autoriza el tratamiento', false);
    }

    public function test_el_panel_dpo_lista_las_solicitudes_sin_consentimiento_y_el_expediente_lo_advierte(): void
    {
        $this->sembrar();
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $this->base(['consentimiento' => 'no']));
        $s = Solicitud::query()->firstOrFail();
        $admin = $this->usuario('admin');
        $r = $this->comoFuncionario($admin)->get('/app/datos-personales')->assertOk();
        $r->assertSee($s->folio)->assertSee('No autoriza')->assertDontSee($s->run);
        $this->comoFuncionario($admin)->get('/app/datos-personales?estado=con_consentimiento')->assertOk()->assertDontSee($s->folio);
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/datos-personales')->assertForbidden();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s->refresh(), $this->usuario('funcionario', 1));
        $this->comoFuncionario($this->usuario('funcionario', 1))->get('/app/expedientes/'.$e->id)->assertOk()->assertSee('no autorizó el tratamiento de sus datos personales', false);
    }

    public function test_la_politica_de_privacidad_es_publica_y_enlaza_el_decreto(): void
    {
        $this->get('/privacidad')->assertOk()->assertSee('Decreto Alcaldicio N° 2590', false)->assertSee('decreto-2590-politica-privacidad.pdf', false)->assertSee('acceso, rectificación, supresión', false);
        $this->assertFileExists(public_path('assets/docs/decreto-2590-politica-privacidad.pdf'));
        $this->assertStringStartsWith('%PDF', (string) file_get_contents(public_path('assets/docs/decreto-2590-politica-privacidad.pdf'), false, null, 0, 5));
    }

    public function test_la_csp_permite_la_vista_previa_local_sin_abrir_otros_origenes(): void
    {
        $csp = (string) $this->get('/privacidad')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("img-src 'self' data: blob:", $csp);
        $this->assertStringContainsString("frame-src 'self' blob:", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }
}
