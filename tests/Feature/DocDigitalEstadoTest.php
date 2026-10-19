<?php

namespace Tests\Feature;

use App\Models\ComunicacionOficial;
use App\Models\Expediente;
use App\Services\ExpedienteService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use App\Services\Interop\DocDigitalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Comportamiento real de DocDigital según el informe de pruebas en DemoDoc (31-03-2026). */
class DocDigitalEstadoTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    private Expediente $e;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->ddConfig();
        $this->e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
    }

    private function saliente(): ComunicacionOficial
    {
        Queue::fake();
        $d = $this->documentoVigente($this->e, $this->usuario('funcionario', 1));
        $d->forceFill(['pdf_firmado_ruta' => $d->pdf_ruta, 'pdf_firmado_sha256' => $d->pdf_sha256])->save();

        return app(ComunicacionService::class)->crearSaliente($this->e, $d->refresh(), 'DEMO-SEGPRES', 'Remite oficio', $this->usuario('funcionario', 1));
    }

    private function estado(string $e): array
    {
        return [$this->dd.'/documentos/987654' => Http::response($this->ddOk(['estado_tramitacion' => $e]), 200)];
    }

    public function test_tras_enviar_se_consulta_el_estado_y_no_se_afirma_despacho(): void
    {
        $c = $this->saliente();
        $this->ddFake();
        app(ComunicacionService::class)->enviar($c);
        $c->refresh();
        $this->assertSame('OPS_PENDIENTE_DESPACHO', $c->dd_estado);
        $this->assertStringContainsString('pendiente de despacho', (string) $c->ddEtiqueta());
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_ends_with($r->url(), '/api/documentos/987654'));
        $this->comoFuncionario($this->usuario('funcionario', 1))->get("/app/expedientes/{$this->e->id}")->assertOk()->assertSee('pendiente de despacho por la Oficina de Partes');
    }

    public function test_si_falla_la_consulta_del_estado_el_envio_sigue_valido(): void
    {
        $c = $this->saliente();
        $this->ddFake([], [], [$this->dd.'/documentos/987654' => Http::response('boom', 504)]);
        app(ComunicacionService::class)->enviar($c);
        $c->refresh();
        $this->assertSame(['enviada', '987654', null], [$c->estado, $c->evidencia, $c->dd_estado]);
    }

    public function test_el_refresco_sigue_el_despacho_y_la_recepcion_y_no_vuelve_a_consultar_lo_final(): void
    {
        $c = $this->saliente();
        $svc = app(ComunicacionService::class);
        $this->ddFake();
        $svc->enviar($c);
        $this->ddFake([], [], $this->estado('OPS_RESUELTO_ENVIADO'));
        $this->assertSame(1, $svc->refrescarEstados());
        $this->assertSame('OPS_RESUELTO_ENVIADO', $c->refresh()->dd_estado);
        $this->ddFake([], [], $this->estado('OPE_RECEPCION_TOTAL'));
        $this->assertSame(1, $svc->refrescarEstados());
        $this->assertSame('OPE_RECEPCION_TOTAL', $c->refresh()->dd_estado);
        $this->assertStringContainsString('acusada por el destinatario', (string) $c->ddEtiqueta());
        $this->assertSame(0, $svc->refrescarEstados(), 'un estado final no se vuelve a consultar');
        $this->assertDatabaseHas('audit_log', ['accion' => 'docdigital.estado']);
        $this->ddFake();
        $this->artisan('docdigital:sincronizar')->expectsOutputToContain('estados consultados: 0')->assertSuccessful();
    }

    public function test_el_dashboard_cuenta_lo_pendiente_de_despacho(): void
    {
        $c = $this->saliente();
        $this->ddFake();
        app(ComunicacionService::class)->enviar($c);
        $p = app(DocDigitalDashboardService::class)->para($this->usuario('funcionario', 1));
        $this->assertSame(1, collect($p['kpis'])->firstWhere('etiqueta', 'Cargadas en DocDigital, pendientes de despacho')['valor']);
    }

    public function test_errores_de_texto_plano_se_muestran_y_el_403_no_filtra_el_run(): void
    {
        $this->ddFake([], [], [
            $this->dd.'/documentos/recibidos/60088/acusorecibo' => Http::response('No existe documento 60088 en estado pendiente para dar acuse.', 400, ['Content-Type' => 'text/plain']),
            $this->dd.'/documentos/recibidos/60234/devolver' => Http::response('Acceso denegado. Usuario run 21.586.306-9 (ID: 41.716, entidad: Municipalidad de Santo Domingo) sin permiso para la operación solicitada.', 403, ['Content-Type' => 'text/plain']),
        ]);
        $c = new HttpDocDigitalClient(config('sgd.docdigital'), 'demo');
        try {
            $c->acusarRecibo('60088');
            $this->fail('debía fallar');
        } catch (DocDigitalException $e) {
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringContainsString('No existe documento 60088 en estado pendiente para dar acuse', $e->getMessage());
        }
        try {
            $c->devolver('60234', 'No corresponde a la municipalidad');
            $this->fail('debía fallar');
        } catch (DocDigitalException $e) {
            $this->assertStringContainsString('no tiene permiso', $e->getMessage());
            $this->assertStringNotContainsString('21.586.306', $e->getMessage());
            $this->assertStringNotContainsString('41.716', $e->getMessage());
        }
    }

    public function test_estado_de_un_documento_ajeno_con_id_invalido_se_rechaza(): void
    {
        $this->expectException(DocDigitalException::class);
        (new HttpDocDigitalClient(config('sgd.docdigital'), 'demo'))->estado('../x');
    }
}
