<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\DecretoPago;
use App\Models\Egreso;
use App\Models\Expediente;
use App\Models\PagoFirmante;
use App\Models\Plantilla;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\DecretoPagoService;
use App\Services\DocumentoService;
use App\Services\EgresoService;
use App\Services\ExpedienteService;
use App\Services\PagoConfigService;
use App\Services\SolicitudService;
use App\Support\MontoEnLetras;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class DecretoPagoTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private Usuario $conta;

    private Usuario $tes;

    private Expediente $pago;

    private Expediente $compra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->conta = Usuario::query()->where('rol', 'contabilidad')->firstOrFail();
        $this->tes = Usuario::query()->where('rol', 'tesorero')->firstOrFail();
        DB::table('tramite_unidad')->insert(['tramite_id' => 1, 'unidad_id' => 8]); // el trámite también lo atiende Administración y Finanzas
        $this->pago = $this->expediente('20000001-3');
        $this->compra = $this->expediente('20000002-1');
    }

    private function expediente(string $run): Expediente
    {
        $s = app(SolicitudService::class)->ingresar(['run' => $run, 'nombres' => 'Prov', 'apellidos' => 'Eedor'], Tramite::query()->with('anexos')->findOrFail(1), 'p@example.com', 'Solicitud de prueba para el módulo de pagos.', 'correo',
            [1 => $this->archivo('a.pdf', $this->pdfValido($run.'a')), 2 => $this->archivo('b.pdf', $this->pdfValido($run.'b'))], []);

        return app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->conta);
    }

    private function datos(array $x = []): array
    {
        return $x + ['proveedor_nombre' => 'Constructora Los Aromos SpA', 'proveedor_run' => '76123456-'.Run::dv('76123456'), 'concepto' => 'Pago de la factura 123 por obras menores de mantención', 'monto' => '1250000',
            'imputacion' => '215-22-06-004', 'orden_compra' => 'OC-1234-56-SE26', 'factura' => 'F-123', 'fecha' => '2026-10-20'];
    }

    private function falla(callable $fn, string $msg): void
    {
        try {
            $fn();
            $this->fail('debía rechazarse');
        } catch (FlujoException $e) {
            $this->assertStringContainsString($msg, mb_strtolower($e->getMessage()));
        }
    }

    private function decretos(): DecretoPagoService
    {
        return app(DecretoPagoService::class);
    }

    private function crear(array $x = []): DecretoPago
    {
        return $this->decretos()->crear($this->conta, $this->pago, $this->compra, $this->datos($x));
    }

    /** Decreto generado con la plantilla, visado y firmado: queda emitido. */
    private function emitido(array $x = []): DecretoPago
    {
        $d = $this->crear($x);
        $d = $this->decretos()->generar($this->conta, $d, Plantilla::query()->where('nombre', 'Decreto de pago (ejemplo)')->firstOrFail());
        $this->decretos()->enviarAFirma($this->conta, $d);
        $doc = $d->documento->refresh();
        $ds = app(DocumentoService::class);
        $ds->visar($doc, Usuario::query()->where('rol', 'secretario')->firstOrFail(), true, null);
        $ds->firmar($doc->refresh(), Usuario::query()->where('rol', 'alcalde')->firstOrFail(), '111222');

        return $d->refresh();
    }

    public function test_monto_en_letras(): void
    {
        $this->assertSame('UN MILLÓN DOSCIENTOS CINCUENTA MIL PESOS', MontoEnLetras::pesos(1250000));
        $this->assertSame('UN PESO', MontoEnLetras::pesos(1));
        $this->assertSame('VEINTIÚN MIL PESOS', MontoEnLetras::pesos(21000));
        $this->assertSame('DOS MILLONES DE PESOS', MontoEnLetras::pesos(2000000));
        $this->assertSame('CIENTO UN PESOS', MontoEnLetras::pesos(101));
    }

    public function test_matriz_de_permisos(): void
    {
        $this->assertTrue($this->conta->tienePermiso('decretopago.gestionar'));
        $this->assertFalse($this->conta->tienePermiso('egresos.gestionar'));
        $this->assertTrue($this->tes->tienePermiso('egresos.gestionar'));
        $this->assertFalse($this->tes->tienePermiso('decretopago.gestionar'));
        foreach (['funcionario', 'secretario', 'alcalde', 'transparencia', 'oirs', 'autoridad'] as $rol) {
            $this->assertFalse($this->usuario($rol)->tienePermiso('decretopago.gestionar'), $rol);
            $this->assertFalse($this->usuario($rol)->tienePermiso('egresos.gestionar'), $rol);
        }
        $this->assertTrue($this->usuario('admin')->tienePermiso('decretopago.configurar'));
        $this->assertFalse($this->conta->tienePermiso('decretopago.configurar'));
        $this->assertTrue($this->usuario('auditor')->tienePermiso('decretopago.ver'));
        $this->assertFalse($this->usuario('funcionario')->tienePermiso('decretopago.ver'));
    }

    public function test_crear_valida_datos_y_asocia_el_proceso_de_compra(): void
    {
        $this->falla(fn () => $this->crear(['monto' => '12,5']), 'monto');
        $this->falla(fn () => $this->crear(['monto' => '0']), 'monto');
        $this->falla(fn () => $this->crear(['proveedor_run' => '11.111.111-2']), 'rut');
        $this->falla(fn () => $this->crear(['concepto' => 'corto']), 'concepto');
        $this->falla(fn () => $this->decretos()->crear($this->usuario('funcionario'), $this->pago, null, $this->datos()), 'no tiene permiso');
        $this->falla(fn () => $this->decretos()->crear($this->usuario('admin'), $this->pago, null, $this->datos()), 'no tiene permiso');
        $d = $this->crear(['proveedor_nombre' => '<b>Proveedor</b> Ltda']);
        $this->assertSame('Proveedor Ltda', $d->proveedor_nombre);
        $this->assertSame('76123456-'.Run::dv('76123456'), $d->proveedor_run);
        $this->assertSame(1250000, $d->monto);
        $this->assertTrue($this->pago->vinculos()->where('origen_expediente_id', $this->compra->id)->exists(), 'el proceso de compra queda asociado al expediente de pago');
        $this->falla(fn () => $this->crear(), 'ya tiene un decreto');
        $this->assertSame('borrador', $d->fase());
    }

    public function test_genera_con_plantilla_envia_a_firma_y_queda_emitido_con_correlativo_propio(): void
    {
        $d = $this->crear();
        $otra = Plantilla::query()->where('nombre', 'Oficio ordinario (ejemplo)')->firstOrFail();
        $this->falla(fn () => $this->decretos()->generar($this->conta, $d, $otra), 'plantilla activa del tipo');
        $d = $this->decretos()->generar($this->conta, $d, Plantilla::query()->where('nombre', 'Decreto de pago (ejemplo)')->firstOrFail());
        $html = $d->documento->html;
        $this->assertStringContainsString('Constructora Los Aromos SpA', $html);
        $this->assertStringContainsString('$1.250.000', $html);
        $this->assertStringContainsString('UN MILLÓN DOSCIENTOS CINCUENTA MIL PESOS', $html);
        $this->assertStringContainsString('OC-1234-56-SE26', $html);
        $this->assertStringContainsString($this->compra->codigo, $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertSame('borrador', $d->fase());
        $this->decretos()->enviarAFirma($this->conta, $d);
        $this->assertSame('en_firma', $d->refresh()->fase());
        $this->assertSame(['visacion', 'firma'], $d->documento->pasos->sortBy('orden')->pluck('tipo')->all());
        $this->falla(fn () => $this->decretos()->enviarAFirma($this->conta, $d), 'ya está en visación');
        $doc = $d->documento;
        $ds = app(DocumentoService::class);
        $ds->visar($doc, Usuario::query()->where('rol', 'secretario')->firstOrFail(), true, null);
        $ds->firmar($doc->refresh(), Usuario::query()->where('rol', 'alcalde')->firstOrFail(), '111222');
        $d->refresh();
        $this->assertSame('emitido', $d->fase());
        $this->assertSame('Decreto de pago N° 01/'.now('America/Santiago')->year, $d->folio());
    }

    public function test_cargar_pdf_emitido(): void
    {
        $d = $this->crear();
        $this->falla(fn () => $this->decretos()->cargarPdf($this->conta, $d, $this->archivo('decreto.pdf', 'no soy un pdf'), '45', '2026-10-19'), '');
        $this->falla(fn () => $this->decretos()->cargarPdf($this->conta, $d, $this->archivo('decreto.pdf'), '', '2026-10-19'), 'número');
        $this->falla(fn () => $this->decretos()->cargarPdf($this->conta, $d, $this->archivo('decreto.pdf'), '45', '2026-02-31'), 'fecha');
        $d = $this->decretos()->cargarPdf($this->conta, $d, $this->archivo('decreto.pdf', $this->pdfValido('dp')), '45/2026', '2026-10-19');
        $this->assertSame('emitido', $d->fase());
        $this->assertSame('Decreto de pago N° 45/2026', $d->folio());
        $this->assertSame('cargado', $d->origen);
        $this->assertSame($d->archivo->sha256, hash('sha256', $this->pdfValido('dp')));
        $this->falla(fn () => $this->decretos()->cargarPdf($this->conta, $d, $this->archivo('otro.pdf', $this->pdfValido('o')), '46', '2026-10-19'), 'ya tiene');
        $this->falla(fn () => $this->decretos()->generar($this->conta, $d, Plantilla::query()->where('nombre', 'Decreto de pago (ejemplo)')->firstOrFail()), 'ya tiene');
    }

    public function test_egreso_exige_decreto_emitido_monto_dentro_del_saldo_y_se_firma_simple_o_avanzada(): void
    {
        $d = $this->crear();
        $datos = ['fecha' => now('America/Santiago')->format('Y-m-d'), 'medio_pago' => 'transferencia', 'monto' => '500000', 'detalle' => 'Transferencia a la cuenta corriente del proveedor', 'referencia' => 'TRF-998877', 'cuenta' => 'Banco Estado 0012'];
        $this->falla(fn () => app(EgresoService::class)->crear($this->tes, $d, $datos), 'no está emitido');
        $d = $this->decretos()->cargarPdf($this->conta, $d, $this->archivo('decreto.pdf', $this->pdfValido('dp')), '45/2026', '2026-10-19');
        $svc = app(EgresoService::class);
        $this->falla(fn () => $svc->crear($this->conta, $d, $datos), 'no tiene permiso');
        $this->falla(fn () => $svc->crear($this->tes, $d, ['monto' => '2000000'] + $datos), 'excede el saldo');
        $this->falla(fn () => $svc->crear($this->tes, $d, ['monto' => '0'] + $datos), 'monto');
        $this->falla(fn () => $svc->crear($this->tes, $d, ['medio_pago' => 'trueque'] + $datos), 'medio de pago');
        $this->falla(fn () => $svc->crear($this->tes, $d, ['fecha' => now('America/Santiago')->addDays(3)->format('Y-m-d')] + $datos), 'futura');
        $this->falla(fn () => $svc->crear($this->tes, $d, ['detalle' => 'x'] + $datos), 'detalle');
        $e = $svc->crear($this->tes, $d->refresh(), ['detalle' => "Pago <script>alert(1)</script>\nsegunda línea"] + $datos);
        $html = $e->documento->html;
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('QUINIENTOS MIL PESOS', $html);
        $this->assertStringContainsString('Transferencia electrónica', $html);
        $this->assertStringContainsString('TRF-998877', $html);
        $paso = $e->documento->pasos->first();
        $this->assertSame('ambas', $paso->firma_permitida);
        $this->assertSame('en_firma', $e->fase());
        // firma SIMPLE elegida por la tesorera
        $res = app(DocumentoService::class)->firmar($e->documento->refresh(), $this->tes, null, 'simple');
        $this->assertSame('vigente', $res->estado);
        $this->assertSame('simple', $res->pasos->first()->firma_tipo);
        $this->assertSame('Egreso N° 01/'.now('America/Santiago')->year, $res->numeroFormateado());
        $this->assertSame('simple', $res->metadatos['firmantes'][0]['tipo_firma']);
        $this->assertStringContainsString('firma electrónica simple', mb_strtolower(app(DocumentoService::class)->leerPdf($res) === '' ? '' : 'firma electrónica simple'));
        $d->refresh()->load('egresos.documento');
        $this->assertSame('emitido', $d->fase());
        $this->assertSame(500000, $d->egresado());
        // segundo egreso por el saldo, firmado con firma AVANZADA (OTP)
        $e2 = $svc->crear($this->tes, $d, ['monto' => '750000'] + $datos);
        $this->falla(fn () => app(DocumentoService::class)->firmar($e2->documento->refresh(), $this->tes, null, 'avanzada'), 'otp');
        $res2 = app(DocumentoService::class)->firmar($e2->documento->refresh(), $this->tes, '111222', 'avanzada');
        $this->assertSame('vigente', $res2->estado);
        $this->assertSame('avanzada', $res2->pasos->first()->firma_tipo);
        $this->assertSame('Egreso N° 02/'.now('America/Santiago')->year, $res2->numeroFormateado());
        $this->assertSame('pagado', $d->refresh()->load('egresos.documento')->fase());
        $this->falla(fn () => $svc->crear($this->tes, $d, ['monto' => '1'] + $datos), 'excede el saldo');
    }

    public function test_la_firma_simple_solo_se_admite_donde_el_tipo_la_permite(): void
    {
        $d = $this->crear();
        $d = $this->decretos()->generar($this->conta, $d, Plantilla::query()->where('nombre', 'Decreto de pago (ejemplo)')->firstOrFail());
        $alc = Usuario::query()->where('rol', 'alcalde')->firstOrFail();
        $this->falla(fn () => app(DocumentoService::class)->definirFlujo($d->documento, 'agil', [], [$alc->id], [$alc->id => 'simple']), 'no admite firma electrónica simple');
        $cfg = app(PagoConfigService::class);
        $adm = $this->usuario('admin');
        $this->falla(fn () => $cfg->agregar($adm, 'decreto_pago', $alc->id, 'Alcalde', 'firma', 'simple'), 'solo admite firma electrónica avanzada');
    }

    public function test_anular_egreso_en_firma_y_decreto_sin_egresos(): void
    {
        $d = $this->decretos()->cargarPdf($this->conta, $this->crear(), $this->archivo('decreto.pdf', $this->pdfValido('dp')), '45', '2026-10-19');
        $datos = ['fecha' => now('America/Santiago')->format('Y-m-d'), 'medio_pago' => 'cheque', 'monto' => '1250000', 'detalle' => 'Cheque N° 100 al proveedor'];
        $e = app(EgresoService::class)->crear($this->tes, $d, $datos);
        $this->falla(fn () => $this->decretos()->anular($this->conta, $d->refresh(), 'Error en el proveedor'), 'egresos en curso');
        app(EgresoService::class)->anular($this->tes, $e, 'Cheque mal emitido');
        $this->assertSame('anulado', $e->refresh()->fase());
        $this->assertSame('anulado', $e->documento->refresh()->estado);
        $this->assertSame('emitido', $d->refresh()->load('egresos')->fase());
        $this->decretos()->anular($this->conta, $d, 'El proveedor fue mal identificado');
        $this->assertSame('anulado', $d->refresh()->fase());
        $this->falla(fn () => app(EgresoService::class)->crear($this->tes, $d, $datos), 'no está emitido');
    }

    public function test_un_decreto_firmado_no_se_anula_ni_un_egreso_emitido(): void
    {
        $d = $this->emitido();
        $this->falla(fn () => $this->decretos()->anular($this->conta, $d, 'Quiero anularlo'), 'dicte un decreto');
        $datos = ['fecha' => now('America/Santiago')->format('Y-m-d'), 'medio_pago' => 'efectivo', 'monto' => '1250000', 'detalle' => 'Pago en efectivo en Tesorería'];
        $e = app(EgresoService::class)->crear($this->tes, $d->refresh(), $datos);
        app(DocumentoService::class)->firmar($e->documento->refresh(), $this->tes, null, 'simple');
        $this->falla(fn () => app(EgresoService::class)->anular($this->tes, $e->refresh(), 'Ya no corresponde'), 'solo se anulan egresos');
    }

    public function test_configuracion_de_firmantes_y_correlativo(): void
    {
        $adm = $this->usuario('admin');
        $cfg = app(PagoConfigService::class);
        $this->falla(fn () => $cfg->agregar($this->conta, 'egreso', $this->tes->id, 'Tesorera', 'firma', 'ambas'), 'no tiene permiso');
        $sec = Usuario::query()->where('rol', 'secretario')->firstOrFail();
        $this->falla(fn () => $cfg->agregar($adm, 'decreto_pago', $sec->id, 'Secretaria', 'visacion', 'avanzada'), 'ya está en la lista');
        $this->falla(fn () => $cfg->agregar($adm, 'decreto_pago', $this->usuario('auditor')->id, 'Auditoría', 'visacion', 'avanzada'), 'solo funcionarios');
        $f = PagoFirmante::query()->where('documento', 'decreto_pago')->where('funcion', 'visacion')->firstOrFail();
        $cfg->mover($adm, $f, 'bajar');
        $this->assertSame('firma', PagoFirmante::query()->where('documento', 'decreto_pago')->orderBy('orden')->first()->funcion);
        $cfg->alternar($adm, PagoFirmante::query()->where('documento', 'decreto_pago')->where('funcion', 'firma')->firstOrFail()); // sin firmantes activos
        $this->falla(fn () => $cfg->flujo('decreto_pago'), 'configure');
        $cfg->guardarCorrelativo($adm, 'decreto_pago', ['folio_formato' => 'DP-{N}/{AAAA}', 'folio_digitos' => 4, 'folio_inicio' => 100, 'folio_reinicio_anual' => true]);
        $cfg->ajustarContador($adm, 'decreto_pago', (int) now('America/Santiago')->year, 150, 'Continuidad con el sistema anterior');
        $this->falla(fn () => $cfg->ajustarContador($adm, 'decreto_pago', (int) now('America/Santiago')->year, 10, 'Intento de bajar'), 'no puede disminuir');
        $this->assertSame('DP-{N}/{AAAA}', $cfg->tipo('decreto_pago')->folio_formato);
        $this->falla(fn () => $cfg->guardarCorrelativo($adm, 'egreso', ['folio_formato' => 'sin numero', 'folio_digitos' => 0, 'folio_inicio' => 1, 'folio_reinicio_anual' => true]), 'exactamente una vez');
    }

    public function test_pantallas_http(): void
    {
        $d = $this->crear();
        $this->comoFuncionario($this->conta)->get('/app/pagos')->assertOk()->assertSee('Decretos de pago')->assertSee('Constructora Los Aromos');
        $this->get("/app/pagos/{$d->id}")->assertOk()->assertSee('Generar con plantilla')->assertSee('Cargar el PDF ya emitido')->assertSee('UN MILLÓN DOSCIENTOS CINCUENTA MIL PESOS');
        $this->get('/app/pagos/nuevo')->assertOk()->assertSee('Nuevo decreto de pago');
        $this->get("/app/expedientes/{$this->pago->id}")->assertOk()->assertSee('Decreto de pago:');
        $this->post("/app/pagos/{$d->id}/cargar", ['archivo' => $this->archivo('decreto.pdf', $this->pdfValido('dp')), 'numero' => '45', 'fecha_decreto' => '2026-10-19'])->assertSessionHas('ok');
        $this->get("/app/pagos/{$d->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/app/pagos/configuracion')->assertForbidden();
        auth()->forgetGuards();
        $this->comoFuncionario($this->tes)->get("/app/pagos/{$d->id}")->assertOk()->assertSee('Generar egreso');
        $this->post("/app/pagos/{$d->id}/egresos", ['fecha' => now('America/Santiago')->format('Y-m-d'), 'medio_pago' => 'transferencia', 'monto' => '1250000', 'detalle' => 'Transferencia al proveedor'])->assertRedirect();
        $e = Egreso::query()->firstOrFail();
        $this->get("/app/pagos/egresos/{$e->id}")->assertOk()->assertSee('Es su turno de firmar')->assertSee('Simple (confirmación en el sistema)');
        $this->post("/app/pagos/egresos/{$e->id}/firmar", ['tipo_firma' => 'simple'])->assertSessionHas('error'); // sin confirmar
        $this->post("/app/pagos/egresos/{$e->id}/firmar", ['tipo_firma' => 'simple', 'confirmo' => 1])->assertSessionHas('ok');
        $this->assertSame('emitido', $e->refresh()->fase());
        $this->get("/app/pagos/egresos/{$e->id}/pdf")->assertOk();
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/pagos')->assertForbidden();
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/pagos/configuracion')->assertOk()->assertSee('Correlativo y firmantes')->assertSee('próximo folio');
        $this->get("/app/pagos/{$d->id}")->assertForbidden(); // el administrador configura, no ve los pagos
    }
}
