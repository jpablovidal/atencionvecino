<?php

namespace Tests\Feature;

use App\Models\DecretoPago;
use App\Models\Plantilla;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\DashboardService;
use App\Services\DecretoPagoService;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\SolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class PanelAlcaldiaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private Usuario $alc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->alc = Usuario::query()->where('rol', 'alcalde')->firstOrFail();
    }

    /** Decreto de pago generado y enviado a firma (visa la secretaria y firma el alcalde). */
    private function decretoEnFirma(): DecretoPago
    {
        DB::table('tramite_unidad')->insert(['tramite_id' => 1, 'unidad_id' => 8]);
        $conta = Usuario::query()->where('rol', 'contabilidad')->firstOrFail();
        $s = app(SolicitudService::class)->ingresar(['run' => '20000001-3', 'nombres' => 'Prov', 'apellidos' => 'Eedor'], Tramite::query()->with('anexos')->findOrFail(1), 'p@example.com', 'Solicitud de prueba.', 'correo',
            [1 => $this->archivo('a.pdf', $this->pdfValido('a')), 2 => $this->archivo('b.pdf', $this->pdfValido('b'))], []);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $conta);
        $svc = app(DecretoPagoService::class);
        $d = $svc->crear($conta, $e, null, ['proveedor_nombre' => 'Constructora Los Aromos SpA', 'concepto' => 'Pago de la factura por obras menores de mantención', 'monto' => '1500000']);
        $d = $svc->generar($conta, $d, Plantilla::query()->where('nombre', 'Decreto de pago (ejemplo)')->firstOrFail());

        return $svc->enviarAFirma($conta, $d);
    }

    public function test_permisos_del_panel(): void
    {
        $this->assertTrue($this->alc->tienePermiso('alcaldia.panel'));
        $this->assertTrue($this->alc->tienePermiso('decretopago.ver'));
        $this->assertTrue($this->alc->tienePermiso('seguimiento.consultar'));
        $this->assertFalse($this->alc->tienePermiso('decretopago.gestionar'));
        $this->assertFalse($this->alc->tienePermiso('egresos.gestionar'));
        foreach (['funcionario', 'secretario', 'admin', 'auditor', 'oirs', 'autoridad'] as $rol) {
            $this->assertFalse($this->usuario($rol)->tienePermiso('alcaldia.panel'), $rol);
        }
        foreach (['funcionario', 'secretario', 'admin', 'auditor'] as $rol) {
            auth()->forgetGuards();
            $this->comoFuncionario($this->usuario($rol))->get('/app/alcaldia')->assertForbidden();
        }
    }

    public function test_el_panel_muestra_firmas_pagos_y_solicitudes(): void
    {
        $this->solicitudDOM();
        $d = $this->decretoEnFirma();
        $p = app(DashboardService::class)->para($this->alc);
        $etq = collect($p['kpis'])->pluck('valor', 'etiqueta');
        $this->assertSame(0, $etq['Documentos por firmar o visar (su turno)'], 'aún visa la secretaria: no es su turno');
        $this->assertSame(1, $etq['Documentos en visación y firma en la institución']);
        $tabla = collect($p['bloques'])->firstWhere('titulo', 'Mis firmas y visaciones pendientes');
        $this->assertCount(1, $tabla['filas']);
        $this->assertStringContainsString('aún no es su turno', $tabla['filas'][0][3]);
        // la secretaria visa: ahora es el turno del alcalde
        app(DocumentoService::class)->visar($d->documento->refresh(), Usuario::query()->where('rol', 'secretario')->firstOrFail(), true, null);
        $p = app(DashboardService::class)->para($this->alc);
        $etq = collect($p['kpis'])->pluck('valor', 'etiqueta');
        $this->assertSame(1, $etq['Documentos por firmar o visar (su turno)']);
        $this->assertSame(1, $etq['Decretos de pago por firmar']);
        $this->assertSame(1, collect($p['bloques'])->firstWhere('titulo', 'Decretos de pago en visación y firma')['filas'] ? 1 : 0);
        $this->comoFuncionario($this->alc)->get('/app/alcaldia')->assertOk()->assertSee('Panel de Alcaldía')->assertSee('Mis firmas y visaciones pendientes')->assertSee('Decretos de pago en visación y firma')
            ->assertSee('Constructora Los Aromos SpA')->assertSee('Solicitudes por etapa')->assertSee('Decretos de pago por estado')->assertSee('Quién tiene hoy los documentos');
        $this->get('/app')->assertOk()->assertSee('Mis firmas y visaciones pendientes');
    }

    public function test_el_panel_sin_pendientes_no_falla(): void
    {
        $this->comoFuncionario($this->alc)->get('/app/alcaldia')->assertOk()->assertSee('No tiene firmas ni visaciones pendientes')->assertSee('No hay decretos de pago en firma');
    }

    public function test_la_secretaria_no_ve_el_panel_pero_si_sus_pendientes_en_su_inicio(): void
    {
        $this->decretoEnFirma();
        $sec = Usuario::query()->where('rol', 'secretario')->firstOrFail();
        $this->comoFuncionario($sec)->get('/app')->assertOk()->assertDontSee('Decretos de pago en visación y firma');
    }
}
