<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Http\Controllers\AuthController;
use App\Models\AuditLog;
use App\Models\DecretoPago;
use App\Models\Expediente;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\DecretoPagoService;
use App\Services\DocumentoService;
use App\Services\EgresoService;
use App\Services\ExpedienteService;
use App\Services\PagosTableroService;
use App\Services\ProveedorPagoService;
use App\Services\SolicitudService;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Escenario;
use Tests\TestCase;

class PagosProveedoresTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private Usuario $conta;

    private Usuario $tes;

    private Expediente $pago;

    private string $rut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->conta = Usuario::query()->where('rol', 'contabilidad')->firstOrFail();
        $this->tes = Usuario::query()->where('rol', 'tesorero')->firstOrFail();
        DB::table('tramite_unidad')->insert(['tramite_id' => 1, 'unidad_id' => 8]);
        $this->pago = $this->expediente('20000001-3');
        $this->rut = '76123456-'.Run::dv('76123456');
    }

    private function expediente(string $run): Expediente
    {
        $s = app(SolicitudService::class)->ingresar(['run' => $run, 'nombres' => 'Prov', 'apellidos' => 'Eedor'], Tramite::query()->with('anexos')->findOrFail(1), 'p@example.com', 'Solicitud de prueba para pagos.', 'correo',
            [1 => $this->archivo('a.pdf', $this->pdfValido($run.'a')), 2 => $this->archivo('b.pdf', $this->pdfValido($run.'b'))], []);

        return app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->conta);
    }

    private function decreto(array $x = [], ?Expediente $e = null): DecretoPago
    {
        $d = app(DecretoPagoService::class)->crear($this->conta, $e ?? $this->pago, null, $x + ['proveedor_nombre' => 'Constructora Los Aromos SpA', 'proveedor_run' => $this->rut, 'concepto' => 'Pago de la factura por obras menores de mantención',
            'monto' => '1000000', 'factura' => 'F-0123', 'factura_fecha' => now('America/Santiago')->subDays(10)->format('Y-m-d')]);

        return $d;
    }

    private function emitir(DecretoPago $d): DecretoPago
    {
        return app(DecretoPagoService::class)->cargarPdf($this->conta, $d, $this->archivo('d.pdf', $this->pdfValido('dp'.$d->id)), (string) (40 + $d->id).'/2026', '2026-10-19');
    }

    private function egresar(DecretoPago $d, int $monto, string $medio = 'transferencia'): void
    {
        $e = app(EgresoService::class)->crear($this->tes, $d->refresh(), ['fecha' => now('America/Santiago')->format('Y-m-d'), 'medio_pago' => $medio, 'monto' => (string) $monto, 'detalle' => 'Pago al proveedor', 'referencia' => 'TRF-555', 'cuenta' => 'Banco Estado 0012-3456']);
        app(DocumentoService::class)->firmar($e->documento->refresh(), $this->tes, null, 'simple');
    }

    public function test_normaliza_el_numero_de_factura(): void
    {
        $this->assertSame('123', ProveedorPagoService::normalizarFactura(' F-0123 ') === 'F0123' ? '123' : 'x');
        $this->assertSame(ProveedorPagoService::normalizarFactura('000123'), ProveedorPagoService::normalizarFactura('123'));
        $this->assertSame(ProveedorPagoService::normalizarFactura('f-123'), ProveedorPagoService::normalizarFactura('F 123'));
    }

    public function test_el_proveedor_ve_cada_etapa_del_pago(): void
    {
        $svc = app(ProveedorPagoService::class);
        $f = now('America/Santiago')->subDays(10)->format('Y-m-d');
        $this->assertSame([], $svc->consultar($this->rut, 'F-0123', $f), 'aún no existe');
        $d = $this->decreto();
        $r = $svc->consultar($this->rut, 'f 0123', $f);
        $this->assertCount(1, $r);
        $this->assertSame('En revisión', $r[0]['estado']);
        $this->assertNull($r[0]['decreto'], 'sin decreto emitido no se informa folio');
        $d = $this->emitir($d);
        $r = $svc->consultar($this->rut, 'F-0123', $f)[0];
        $this->assertSame('Decreto emitido', $r['estado']);
        $this->assertSame('Decreto de pago N° '.(40 + $d->id).'/2026', $r['decreto']);
        $this->assertSame(10, $r['dias']);
        $this->egresar($d, 400000, 'cheque');
        $r = $svc->consultar($this->rut, 'F-0123', $f)[0];
        $this->assertSame('Pagada parcialmente', $r['estado']);
        $this->assertSame(400000, $r['pagado']);
        $this->egresar($d, 600000);
        $r = $svc->consultar($this->rut, 'F-0123', $f)[0];
        $this->assertSame('Pagada', $r['estado']);
        $this->assertCount(2, $r['pagos']);
        $this->assertSame('TRF-555', $r['pagos'][0]['referencia']);
        $this->assertArrayNotHasKey('cuenta', $r['pagos'][0], 'nunca datos bancarios');
    }

    public function test_exige_los_tres_datos_y_no_revela_cual_falla(): void
    {
        $svc = app(ProveedorPagoService::class);
        $f = now('America/Santiago')->subDays(10)->format('Y-m-d');
        $this->decreto();
        $this->assertSame([], $svc->consultar($this->rut, 'F-9999', $f), 'factura distinta');
        $this->assertSame([], $svc->consultar($this->rut, 'F-0123', now('America/Santiago')->subDays(11)->format('Y-m-d')), 'fecha distinta');
        $this->assertSame([], $svc->consultar('11111111-1', 'F-0123', $f), 'otro proveedor');
        $this->assertCount(1, $svc->consultar(substr($this->rut, 0, 2).'.'.substr($this->rut, 2, 3).'.'.substr($this->rut, 5), 'F-0123', $f), 'acepta el RUT con puntos');
        foreach ([['11111111-2', 'F-0123', $f], [$this->rut, '', $f], [$this->rut, 'F-0123', '2026-02-31'], [$this->rut, 'F-0123', now()->addDays(5)->format('Y-m-d')], [$this->rut, 'F-0123', 'abc']] as [$a, $b, $c]) {
            try {
                $svc->consultar($a, $b, $c);
                $this->fail('debía rechazar datos inválidos');
            } catch (FlujoException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_un_decreto_anulado_se_informa(): void
    {
        $d = $this->decreto();
        app(DecretoPagoService::class)->anular($this->conta, $d, 'Proveedor mal identificado');
        $r = app(ProveedorPagoService::class)->consultar($this->rut, 'F-0123', now('America/Santiago')->subDays(10)->format('Y-m-d'));
        $this->assertSame('Decreto anulado', $r[0]['estado']);
    }

    public function test_sin_clave_unica_solo_se_ve_la_invitacion_a_ingresar(): void
    {
        $f = now('America/Santiago')->subDays(10)->format('Y-m-d');
        $this->emitir($this->decreto());
        $this->get('/pagos-proveedores')->assertOk()->assertSee('Primero, ingrese con ClaveÚnica')->assertSee('Ingresa con ClaveÚnica')->assertDontSee('Número de factura')->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/pagos-proveedores', ['rut' => $this->rut, 'factura' => 'F-0123', 'fecha' => $f])->assertRedirect('/pagos-proveedores')->assertSessionHas('aviso');
        $this->get('/pagos-proveedores/ingresar')->assertRedirect('/ingreso/ciudadano')->assertSessionHas('retorno_pago');
    }

    public function test_tras_ingresar_con_clave_unica_vuelve_a_la_consulta_y_es_de_un_solo_uso(): void
    {
        $r = $this->app->make(AuthController::class);
        $m = new \ReflectionMethod($r, 'iniciar');
        $cu = ['run' => '20000001-3', 'nombres' => 'Vecina', 'apellidos' => 'De Prueba'];
        $req = Request::create('/x');
        $req->setLaravelSession($this->app['session.store']);
        $req->session()->put('retorno_pago', ['exp' => time() + 600]);
        $this->assertStringEndsWith('/pagos-proveedores', $m->invoke($r, $req, 'ciudadano', $cu)->getTargetUrl());
        $req2 = Request::create('/x');
        $req2->setLaravelSession($this->app['session.store']);
        $req2->session()->put('retorno_pago', ['exp' => time() - 5]);
        $this->assertStringEndsWith('/ciudadano', $m->invoke($r, $req2, 'ciudadano', $cu)->getTargetUrl(), 'el retorno vencido no se usa');
        $req3 = Request::create('/x');
        $req3->setLaravelSession($this->app['session.store']);
        $req3->session()->put('retorno_pago', ['exp' => time() + 600]);
        $this->assertStringEndsWith('/espacio', $m->invoke($r, $req3, 'funcionario', ['run' => $this->usuario('funcionario')->run, 'nombres' => 'x', 'apellidos' => 'y'])->getTargetUrl(), 'el personal no arrastra el retorno');
    }

    public function test_pagina_con_sesion_y_con_privacidad(): void
    {
        $d = $this->emitir($this->decreto());
        $f = now('America/Santiago')->subDays(10)->format('Y-m-d');
        $this->comoCiudadano()->get('/pagos-proveedores')->assertOk()->assertSee('Consulta de pago a proveedores')->assertSee('RUT de la empresa')->assertSee('Consulta como')->assertSee('***001-3');
        $r = $this->post('/pagos-proveedores', ['rut' => $this->rut, 'factura' => 'F-0123', 'fecha' => $f])->assertOk();
        $r->assertSee('Decreto emitido')->assertSee('Constructora Los Aromos SpA')->assertSee('Decreto de pago N°')->assertHeader('Cache-Control', 'no-store, private');
        $r->assertDontSee('EXP-')->assertDontSee('Diego')->assertDontSee('Bernardo');
        $this->post('/pagos-proveedores', ['rut' => $this->rut, 'factura' => 'F-0123', 'fecha' => '2025-01-01'])->assertOk()->assertSee('No encontramos información de pago con esos datos');
        $this->post('/pagos-proveedores', ['rut' => '11111111-2', 'factura' => 'F-0123', 'fecha' => $f])->assertOk()->assertSee('no es válido');
        $log = AuditLog::query()->where('accion', 'publico.pago_consulta')->latest('id')->first();
        $this->assertStringNotContainsString('76123456', (string) $log->detalle, 'el RUT de la empresa no se guarda completo');
        $this->assertSame('proveedor', $log->actor_rol);
        $this->assertSame('20000001-3', $log->actor_run, 'queda registrada la persona que consultó');
        $this->assertNotNull($d);
    }

    public function test_limite_de_consultas_por_minuto_y_por_persona(): void
    {
        $f = now('America/Santiago')->subDays(10)->format('Y-m-d');
        $this->comoCiudadano();
        for ($i = 0; $i < 12; $i++) {
            $this->post('/pagos-proveedores', ['rut' => $this->rut, 'factura' => 'X'.$i, 'fecha' => $f])->assertOk();
        }
        $this->post('/pagos-proveedores', ['rut' => $this->rut, 'factura' => 'X99', 'fecha' => $f])->assertStatus(429);
        RateLimiter::clear('pago-prov:'.hash('sha256', '20000001-3'));
        $this->assertSame(0, RateLimiter::attempts('pago-prov:'.hash('sha256', '20000001-3')));
    }

    public function test_limite_por_persona_por_hora(): void
    {
        $f = now('America/Santiago')->subDays(10)->format('Y-m-d');
        $clave = 'pago-prov:'.hash('sha256', '20000001-3');
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit($clave, 3600);
        }
        $this->comoCiudadano()->post('/pagos-proveedores', ['rut' => $this->rut, 'factura' => 'X1', 'fecha' => $f])->assertStatus(429)->assertSee('límite de consultas por hora');
    }

    public function test_tablero_del_tesorero(): void
    {
        $viejo = $this->emitir($this->decreto(['factura_fecha' => now('America/Santiago')->subDays(45)->format('Y-m-d'), 'factura' => 'F-1']));
        $this->emitir($this->decreto(['factura' => 'F-2', 'monto' => '500000'], $this->expediente('20000002-1')));
        $d3 = $this->emitir($this->decreto(['factura' => 'F-3', 'monto' => '300000'], $this->expediente('20000003-K')));
        $this->egresar($d3, 300000);
        $datos = app(PagosTableroService::class)->datos();
        $this->assertSame(2, $datos['porEgresar']->count());
        $this->assertSame($viejo->id, $datos['porEgresar']->first()['decreto']->id, 'el más antiguo primero');
        $this->assertTrue($datos['porEgresar']->first()['vencido']);
        $this->assertSame(1, $datos['kpis'][1]['valor']);
        $this->assertSame(1, $datos['kpis'][3]['valor']);
        $this->assertStringContainsString('$300.000', $datos['kpis'][3]['etiqueta']);
        $this->comoFuncionario($this->tes)->get('/app/pagos/tablero')->assertOk()->assertSee('Tablero de pagos')->assertSee('Decretos por egresar')->assertSee('Antigüedad de lo por pagar')->assertSee('Egresos del mes');
        $this->get('/app/pagos/tablero?mes=2025-13')->assertSessionHasErrors('mes');
        $this->get('/app/pagos/tablero?mes=2025-01')->assertOk();
        $csv = $this->get('/app/pagos/tablero/exportar')->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('F-1', $csv->getContent());
        $this->get('/app')->assertOk()->assertSee('Decretos por egresar');
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/pagos/tablero')->assertForbidden();
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/pagos/tablero')->assertForbidden();
        $this->assertSame('2026-10', Carbon::parse('2026-10-18')->format('Y-m'));
    }

    public function test_el_csv_neutraliza_formulas(): void
    {
        $d = $this->emitir($this->decreto(['proveedor_nombre' => '=HYPERLINK("http://x")']));
        $csv = app(PagosTableroService::class)->csv();
        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertNotNull($d);
    }
}
