<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\DecretoPago;
use App\Models\Egreso;
use App\Models\Expediente;
use App\Models\PagoFirmante;
use App\Models\Plantilla;
use App\Models\Usuario;
use App\Services\AccesoRestringidoService;
use App\Services\AuditoriaService;
use App\Services\DecretoPagoService;
use App\Services\DocumentoService;
use App\Services\EgresoService;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use App\Services\FolioService;
use App\Services\PagoConfigService;
use Illuminate\Http\Request;

/** Módulo Decreto de pago: expediente de pago + proceso de compra, decreto (generado o cargado), egreso de Tesorería y configuración. */
class PagosController extends Controller
{
    public function __construct(
        private readonly DecretoPagoService $decretos, private readonly EgresoService $egresos, private readonly PagoConfigService $config,
        private readonly AuditoriaService $audit, private readonly AccesoRestringidoService $restringidos,
    ) {}

    private function hecho(callable $fn, string $ok, ?string $destino = null)
    {
        try {
            $fn();
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return ($destino ? redirect($destino) : back())->with('ok', $ok);
    }

    // ---------------------------------------------------------------- decretos
    public function index(Request $r)
    {
        $f = $r->validate(['fase' => 'nullable|in:'.implode(',', array_keys(DecretoPago::FASES)), 'q' => 'nullable|string|max:60']);
        $q = DecretoPago::query()->with('expediente:id,codigo', 'documento', 'egresos.documento')->orderByDesc('id');
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('proveedor_nombre', 'like', $like)->orWhere('proveedor_run', 'like', $like)->orWhere('factura', 'like', $like)->orWhere('orden_compra', 'like', $like)
                ->orWhereHas('expediente', fn ($e) => $e->where('codigo', 'like', $like)));
        }
        $todos = $q->limit(300)->get();
        $porFase = $todos->groupBy(fn ($d) => $d->fase())->map->count();
        $lista = empty($f['fase']) ? $todos : $todos->filter(fn ($d) => $d->fase() === $f['fase'])->values();

        return view('app.pagos.index', ['lista' => $lista->take(100), 'porFase' => $porFase, 'f' => ['fase' => $f['fase'] ?? '', 'q' => $f['q'] ?? ''], 'fases' => DecretoPago::FASES]);
    }

    public function nuevo(Request $r)
    {
        $u = $r->user();
        $ocupados = DecretoPago::query()->where('estado', 'abierto')->pluck('expediente_id');
        $abiertos = Expediente::query()->with('solicitud.tramite:id,nombre', 'unidad:id,nombre')->where('estado', 'abierto')->whereNotIn('id', $ocupados)->orderByDesc('id')->limit(300)->get()
            ->filter(fn ($e) => $u->can('operar', $e))->take(100)->values();

        return view('app.pagos.nuevo', ['abiertos' => $abiertos, 'preexp' => (int) $r->query('expediente')]);
    }

    private function reglasDatos(): array
    {
        return ['proveedor_nombre' => 'required|string|max:200', 'proveedor_run' => 'nullable|string|max:14', 'concepto' => 'required|string|max:600', 'monto' => 'required|string|max:14', 'imputacion' => 'nullable|string|max:200',
            'orden_compra' => 'nullable|string|max:80', 'factura' => 'nullable|string|max:80', 'fecha' => 'nullable|date_format:Y-m-d'];
    }

    public function crear(Request $r)
    {
        $d = $r->validate($this->reglasDatos() + ['expediente_id' => 'required|integer|min:1', 'compra_codigo' => 'nullable|string|max:30']);
        $pago = Expediente::query()->findOrFail((int) $d['expediente_id']);
        $compra = ! empty($d['compra_codigo']) ? Expediente::query()->where('codigo', trim($d['compra_codigo']))->first() : null;
        if (! empty($d['compra_codigo']) && ! $compra) {
            return back()->withInput()->with('error', 'No existe un expediente con el código del proceso de compra indicado.');
        }
        try {
            $dec = $this->decretos->crear($r->user(), $pago, $compra, $d);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.pagos.ver', $dec)->with('ok', 'Decreto de pago creado. Continúe con el PDF del decreto: genérelo con la plantilla o cargue el firmado.');
    }

    public function ver(Request $r, DecretoPago $decreto)
    {
        $u = $r->user();
        $decreto->load('expediente.unidad', 'compra', 'documento.pasos.usuario', 'archivo', 'egresos.documento.pasos.usuario', 'creador');
        $tipo = $this->config->tipo('decreto_pago');
        $svcE = app(ExpedienteService::class);
        $puedeG = $u->tienePermiso('decretopago.gestionar') && $u->can('operar', $decreto->expediente) && $decreto->estado === 'abierto';
        try {
            $flujo = $this->config->flujo('decreto_pago');
            $flujoOk = null;
        } catch (FlujoException $e) {
            $flujo = null;
            $flujoOk = $e->getMessage();
        }

        return view('app.pagos.show', [
            'd' => $decreto, 'fase' => $decreto->fase(), 'fases' => DecretoPago::FASES, 'puedeG' => $puedeG, 'puedeE' => $u->tienePermiso('egresos.gestionar'),
            'plantillas' => $puedeG ? Plantilla::query()->visiblesPara($u)->where('activo', true)->where('tipo_documento_id', $tipo->id)->orderBy('nombre')->get() : collect(),
            'flujoErr' => $flujoOk, 'firmantes' => $this->config->firmantes('decreto_pago', true), 'medios' => Egreso::MEDIOS,
            'indicePago' => $u->can('ver', $decreto->expediente) ? $svcE->indice($decreto->expediente, $u) : null,
            'indiceCompra' => $decreto->compra && $u->can('ver', $decreto->compra) ? $svcE->indice($decreto->compra, $u) : null, 'saldo' => $decreto->monto - $decreto->comprometido(),
        ]);
    }

    public function actualizar(Request $r, DecretoPago $decreto)
    {
        $d = $r->validate($this->reglasDatos());

        return $this->hecho(fn () => $this->decretos->actualizar($r->user(), $decreto, $d), 'Datos del decreto guardados.');
    }

    public function generar(Request $r, DecretoPago $decreto)
    {
        $d = $r->validate(['plantilla_id' => 'required|integer|min:1']);
        $p = Plantilla::query()->visiblesPara($r->user())->find((int) $d['plantilla_id']);
        if (! $p) {
            return back()->with('error', 'Plantilla no disponible.');
        }

        return $this->hecho(fn () => $this->decretos->generar($r->user(), $decreto, $p), 'Decreto generado como borrador: revíselo y edítelo en el documento, y luego envíelo a visación y firma.');
    }

    public function enviarFirma(Request $r, DecretoPago $decreto)
    {
        return $this->hecho(fn () => $this->decretos->enviarAFirma($r->user(), $decreto), 'Decreto enviado a visación y firma con los firmantes configurados.');
    }

    public function cargar(Request $r, DecretoPago $decreto)
    {
        $d = $r->validate(['archivo' => 'required|file', 'numero' => 'required|string|max:60', 'fecha_decreto' => 'required|date_format:Y-m-d']);

        return $this->hecho(fn () => $this->decretos->cargarPdf($r->user(), $decreto, $r->file('archivo'), $d['numero'], $d['fecha_decreto']), 'Decreto de pago cargado.');
    }

    public function anular(Request $r, DecretoPago $decreto)
    {
        $d = $r->validate(['motivo' => 'required|string|max:400']);

        return $this->hecho(fn () => $this->decretos->anular($r->user(), $decreto, $d['motivo']), 'Decreto de pago anulado.');
    }

    public function pdf(Request $r, DecretoPago $decreto, DocumentoService $docs, FileVault $vault)
    {
        $decreto->load('documento', 'archivo');
        if ($decreto->documento) {
            $this->restringidos->exigirDocumento($r->user(), $decreto->documento, 'descargar_pdf');
            $this->audit->registrar('pago.pdf', 'decreto_pago', $decreto->id, [], true);

            return descarga($docs->leerPdf($decreto->documento), 'decreto-de-pago-'.$decreto->id.'.pdf', 'application/pdf', true);
        }
        abort_unless($decreto->archivo instanceof Archivo, 404);
        $this->restringidos->exigirArchivo($r->user(), $decreto->archivo, 'descargar');
        $this->audit->registrar('pago.pdf', 'decreto_pago', $decreto->id, [], true);

        return descarga($vault->get($decreto->archivo->ruta, $decreto->archivo->sha256), $decreto->archivo->nombre_original, 'application/pdf', true);
    }

    // ---------------------------------------------------------------- egresos (Tesorería)
    public function crearEgreso(Request $r, DecretoPago $decreto)
    {
        $d = $r->validate(['fecha' => 'required|date_format:Y-m-d', 'medio_pago' => 'required|in:'.implode(',', array_keys(Egreso::MEDIOS)), 'monto' => 'required|string|max:14', 'detalle' => 'required|string|max:1200',
            'referencia' => 'nullable|string|max:100', 'cuenta' => 'nullable|string|max:150']);
        try {
            $e = $this->egresos->crear($r->user(), $decreto, $d);
        } catch (FlujoException $ex) {
            return back()->withInput()->with('error', $ex->getMessage());
        }

        return redirect()->route('app.pagos.egreso', $e)->with('ok', 'Egreso generado y enviado a firma.');
    }

    public function egreso(Request $r, Egreso $egreso)
    {
        $egreso->load('decreto.expediente', 'documento.pasos.usuario', 'creador');
        $doc = $egreso->documento;
        $turno = $doc && $doc->estado === 'en_flujo' ? $doc->pasos->where('estado', 'pendiente')->sortBy('orden')->first() : null;

        return view('app.pagos.egreso', ['e' => $egreso, 'd' => $egreso->decreto, 'doc' => $doc, 'medios' => Egreso::MEDIOS, 'turno' => $turno, 'miTurno' => $turno && $turno->usuario_id === $r->user()->id && $turno->tipo === 'firma',
            'puedeE' => $r->user()->tienePermiso('egresos.gestionar')]);
    }

    public function egresoPdf(Request $r, Egreso $egreso, DocumentoService $docs)
    {
        abort_unless($egreso->documento, 404);
        $this->restringidos->exigirDocumento($r->user(), $egreso->documento, 'descargar_pdf');
        $this->audit->registrar('pago.pdf_egreso', 'egreso', $egreso->id, [], true);

        return descarga($docs->leerPdf($egreso->documento), 'egreso-'.$egreso->id.'.pdf', 'application/pdf', true);
    }

    public function firmarEgreso(Request $r, Egreso $egreso, DocumentoService $docs)
    {
        $d = $r->validate(['tipo_firma' => 'nullable|in:avanzada,simple', 'otp' => [($r->input('tipo_firma') === 'simple' ? 'nullable' : 'required'), 'string', 'regex:/^\d{6}$/'], 'confirmo' => 'nullable|boolean']);
        if (($d['tipo_firma'] ?? null) === 'simple' && (! $r->boolean('confirmo') || (time() - (int) $r->session()->get('login_at', 0)) > 1800)) {
            return back()->with('error', 'Para firmar con firma electrónica simple confirme su voluntad de firmar y haya ingresado al sistema en los últimos 30 minutos (vuelva a ingresar si es necesario).');
        }
        $doc = $egreso->documento ?? abort(404);
        $this->restringidos->exigirDocumento($r->user(), $doc, 'firmar');
        try {
            $res = $docs->firmar($doc, $r->user(), $d['otp'] ?? null, $d['tipo_firma'] ?? null);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', $res->estado === 'vigente' ? 'Egreso firmado y vigente: '.$res->numeroFormateado() : 'Firma registrada.');
    }

    public function anularEgreso(Request $r, Egreso $egreso)
    {
        $d = $r->validate(['motivo' => 'required|string|max:400']);

        return $this->hecho(fn () => $this->egresos->anular($r->user(), $egreso, $d['motivo']), 'Egreso anulado.', route('app.pagos.ver', $egreso->decreto_pago_id));
    }

    // ---------------------------------------------------------------- configuración (correlativo y firmantes)
    public function configuracion(Request $r)
    {
        $anio = (int) now('America/Santiago')->year;
        $folios = app(FolioService::class);
        $docs = [];
        foreach (PagoFirmante::DOCUMENTOS as $clave => $nombre) {
            $t = $this->config->tipo($clave);
            $cont = collect($folios->contadores($t));
            $ultimo = (int) ($cont->firstWhere('anio', $t->folio_reinicio_anual ? $anio : 0)['ultimo'] ?? max(0, $t->folio_inicio - 1));
            $docs[$clave] = ['nombre' => $nombre, 'tipo' => $t, 'proximo' => $folios->formatear($t, $ultimo + 1, $anio), 'contadores' => $cont, 'firmantes' => $this->config->firmantes($clave)];
        }

        return view('app.pagos.configuracion', ['docs' => $docs, 'anio' => $anio, 'usuarios' => Usuario::query()->where('activo', true)->operadores()->orderBy('apellidos')->get(), 'funciones' => PagoFirmante::FUNCIONES, 'firmas' => PagoFirmante::FIRMAS]);
    }

    public function guardarCorrelativo(Request $r, string $doc)
    {
        $d = $r->validate(['folio_formato' => 'required|string|max:80', 'folio_digitos' => 'required|integer|between:0,8', 'folio_inicio' => 'required|integer|min:1|max:99999999', 'folio_reinicio_anual' => 'nullable|boolean']);

        return $this->hecho(fn () => $this->config->guardarCorrelativo($r->user(), $doc, ['folio_reinicio_anual' => (bool) ($d['folio_reinicio_anual'] ?? false)] + $d), 'Correlativo guardado. Aplica a los documentos que se firmen desde ahora.');
    }

    public function ajustarContador(Request $r, string $doc)
    {
        $d = $r->validate(['anio' => 'required|integer|min:0|max:2200', 'ultimo' => 'required|integer|min:0|max:99999999', 'motivo' => 'required|string|min:5|max:255']);

        return $this->hecho(fn () => $this->config->ajustarContador($r->user(), $doc, (int) $d['anio'], (int) $d['ultimo'], $d['motivo']), 'Contador ajustado.');
    }

    public function agregarFirmante(Request $r, string $doc)
    {
        $d = $r->validate(['usuario_id' => 'required|integer', 'cargo' => 'required|string|max:160', 'funcion' => 'required|in:visacion,firma', 'firma_tipo' => 'required|in:avanzada,simple,ambas']);

        return $this->hecho(fn () => $this->config->agregar($r->user(), $doc, (int) $d['usuario_id'], $d['cargo'], $d['funcion'], $d['firma_tipo']), 'Participante agregado.');
    }

    public function accionFirmante(Request $r, PagoFirmante $firmante, string $accion)
    {
        abort_unless(in_array($accion, ['quitar', 'alternar', 'subir', 'bajar'], true), 404);

        return $this->hecho(fn () => match ($accion) {
            'quitar' => $this->config->quitar($r->user(), $firmante),
            'alternar' => $this->config->alternar($r->user(), $firmante),
            default => $this->config->mover($r->user(), $firmante, $accion),
        }, 'Lista actualizada.');
    }
}
