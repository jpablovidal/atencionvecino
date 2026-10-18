<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\TransparenciaActivaItem;
use App\Models\TransparenciaFeriado;
use App\Models\TransparenciaRequerimiento;
use App\Models\Unidad;
use App\Services\AuditoriaService;
use App\Services\SeguimientoService;
use App\Services\TransparenciaReporte;
use App\Services\TransparenciaService;
use App\Support\Plazos;
use App\Support\Run;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Transparencia activa y pasiva (Ley 20.285): consultas, requerimientos, plazos, publicaciones y reportes. */
class TransparenciaController extends Controller
{
    public function __construct(private readonly TransparenciaService $svc, private readonly SeguimientoService $seg, private readonly AuditoriaService $audit) {}

    private function hecho(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', $ok);
    }

    // ---------------------------------------------------------------- consultas (transparencia pasiva)
    public function panel(Request $r)
    {
        $d = $r->validate(['run' => 'nullable|string|max:15', 'folio' => 'nullable|string|max:30', 'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d',
            'tramite_id' => 'nullable|integer|exists:tramites,id', 'unidad_id' => 'nullable|integer|exists:unidades,id',
            'etapa' => 'nullable|in:'.implode(',', array_keys(SeguimientoService::ETAPAS)), 'plazo' => 'nullable|in:'.implode(',', array_keys(SeguimientoService::PLAZOS))]);
        $f = array_filter($d, fn ($v) => $v !== null && $v !== '');
        if (isset($f['run'])) {
            $run = Run::normalizar($f['run']) ?? throw ValidationException::withMessages(['run' => 'El RUN no es válido (revise el dígito verificador).']);
            $f['run'] = $run;
        }
        if (isset($f['desde'], $f['hasta']) && $f['hasta'] < $f['desde']) {
            throw ValidationException::withMessages(['hasta' => 'La fecha final no puede ser anterior a la inicial.']);
        }
        $pag = $this->seg->consulta($f + ['transparencia' => 1])->paginate(20)->withQueryString();
        $filas = $this->seg->filas($pag->getCollection());
        $snap = $this->seg->instantanea()->whereIn('tramite_id', Tramite::query()->where('es_transparencia', true)->pluck('id'));
        $activas = $snap->where('etapa', '!=', 'terminada');
        $req = TransparenciaRequerimiento::query()->where('estado', 'pendiente')->get();
        $activa = collect($this->reporteActiva());
        $kpis = [
            ['icono' => 'folder', 'valor' => $activas->count(), 'etiqueta' => 'Consultas abiertas', 'nivel' => 'ok', 'url' => route('app.transparencia')],
            ['icono' => 'alert', 'valor' => $snap->where('plazo', 'vencido')->count(), 'etiqueta' => 'Con plazo vencido', 'nivel' => $snap->where('plazo', 'vencido')->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.transparencia', ['plazo' => 'vencido'])],
            ['icono' => 'clock', 'valor' => $snap->where('plazo', 'por_vencer')->count(), 'etiqueta' => 'Por vencer', 'nivel' => 'info', 'url' => route('app.transparencia', ['plazo' => 'por_vencer'])],
            ['icono' => 'send', 'valor' => $req->count(), 'etiqueta' => 'Requerimientos pendientes', 'nivel' => $req->isNotEmpty() ? 'info' : 'ok', 'url' => route('app.transparencia.requerimientos')],
            ['icono' => 'alert', 'valor' => $req->filter->vencido()->count(), 'etiqueta' => 'Requerimientos vencidos', 'nivel' => $req->filter->vencido()->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.transparencia.requerimientos')],
            ['icono' => 'globe', 'valor' => $activa->whereIn('estado', ['vencida', 'pendiente', 'por_vencer'])->count(), 'etiqueta' => 'Publicaciones activas por hacer', 'nivel' => $activa->where('estado', 'vencida')->isNotEmpty() ? 'aviso' : 'info', 'url' => route('app.transparencia.activa')],
        ];

        return view('app.transparencia.panel', ['f' => $f, 'pag' => $pag, 'filas' => $filas, 'kpis' => $kpis, 'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'tramites' => Tramite::query()->where('es_transparencia', true)->orderBy('nombre')->get(['id', 'nombre']), 'etapas' => SeguimientoService::ETAPAS, 'plazos' => SeguimientoService::PLAZOS]);
    }

    /** @return array<int,array<string,mixed>> */
    private function reporteActiva(): array
    {
        return app(TransparenciaReporte::class)->activa();
    }

    // ---------------------------------------------------------------- acciones sobre una solicitud (desde el expediente)
    private function solicitud(string $folio): Solicitud
    {
        return Solicitud::query()->with('tramite', 'expediente')->where('folio', $folio)->firstOrFail();
    }

    public function prorrogar(Request $r, string $folio)
    {
        $d = $r->validate(['motivo' => 'required|string|max:2000']);
        $s = $this->solicitud($folio);

        return $this->hecho(fn () => $this->svc->prorrogar($s, $r->user(), $d['motivo']), 'Plazo prorrogado. El solicitante ve la nueva fecha en su seguimiento.');
    }

    public function requerir(Request $r, string $folio)
    {
        $d = $r->validate(['unidad_id' => 'required|integer|exists:unidades,id', 'instruccion' => 'required|string|max:4000', 'plazo_dias' => 'nullable|integer|between:1,30']);
        $s = $this->solicitud($folio);

        return $this->hecho(fn () => $this->svc->requerir($s, $r->user(), (int) $d['unidad_id'], $d['instruccion'], isset($d['plazo_dias']) ? (int) $d['plazo_dias'] : null), 'Requerimiento enviado a la dirección.');
    }

    public function derivarOrgano(Request $r, string $folio)
    {
        $d = $r->validate(['organo' => 'required|string|max:300', 'motivo' => 'required|string|max:2000', 'fecha' => 'required|date_format:Y-m-d', 'canal' => 'required|in:'.implode(',', array_keys(TransparenciaService::CANALES)), 'referencia' => 'nullable|string|max:200']);
        $s = $this->solicitud($folio);
        try {
            $this->svc->derivarOtroOrgano($s, $r->user(), $d);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.expediente', $s->expediente_id)->with('ok', 'Derivación registrada y expediente cerrado. Notifique al solicitante con el documento de derivación.');
    }

    // ---------------------------------------------------------------- requerimientos a las direcciones
    public function requerimientos(Request $r)
    {
        $u = $r->user();
        $q = TransparenciaRequerimiento::query()->with(['solicitud:id,folio,detalle,tramite_id', 'solicitud.tramite:id,nombre', 'unidad:id,nombre', 'respondidoPor:id,nombres,apellidos']);
        $todos = $u->tienePermiso('transparencia.ver');
        if (! $todos) {
            $q->where('unidad_id', (int) $u->unidad_id);
        }
        $estado = in_array($r->query('estado'), ['pendiente', 'respondido'], true) ? $r->query('estado') : null;
        if ($estado) {
            $q->where('estado', $estado);
        }
        $pag = $q->orderByRaw("case when estado = 'pendiente' then 0 else 1 end")->orderBy('vence_en')->paginate(20)->withQueryString();

        return view('app.transparencia.requerimientos', ['pag' => $pag, 'estado' => $estado, 'todos' => $todos, 'u' => $u]);
    }

    public function responder(Request $r, TransparenciaRequerimiento $requerimiento)
    {
        $d = $r->validate(['respuesta' => 'required|string|max:8000']);

        return $this->hecho(fn () => $this->svc->responder($requerimiento, $r->user(), $d['respuesta']), 'Respuesta enviada a Transparencia.');
    }

    // ---------------------------------------------------------------- transparencia activa
    public function activa(Request $r)
    {
        $items = TransparenciaActivaItem::query()->with('unidad:id,nombre', 'registros')->orderBy('nombre')->get();
        $filas = $items->map(fn ($i) => ['i' => $i, 'e' => $this->svc->estadoActiva($i)]);
        $c = $filas->where('i.activo', true);

        return view('app.transparencia.activa', ['filas' => $filas, 'periodo' => $this->svc->periodoVigente(), 'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'resumen' => ['al_dia' => $c->where('e.estado', 'al_dia')->count(), 'pendiente' => $c->whereIn('e.estado', ['pendiente', 'por_vencer'])->count(), 'vencida' => $c->where('e.estado', 'vencida')->count()],
            'defectoDia' => $this->svc->param('activa_dia_limite')]);
    }

    public function activaRegistrar(Request $r, TransparenciaActivaItem $item)
    {
        $d = $r->validate(['periodo' => 'required|string|max:7', 'publicado_en' => 'required|date_format:Y-m-d', 'url' => 'nullable|string|max:300', 'observacion' => 'nullable|string|max:300']);

        return $this->hecho(fn () => $this->svc->registrarActiva($r->user(), $item, $d['periodo'], $d['publicado_en'], $d['url'] ?? null, $d['observacion'] ?? null), 'Publicación registrada.');
    }

    public function activaGuardar(Request $r, ?TransparenciaActivaItem $item = null)
    {
        $d = $r->validate(['codigo' => 'nullable|string|max:30', 'nombre' => 'required|string|max:300', 'unidad_id' => 'nullable|integer|exists:unidades,id', 'dia_limite' => 'required|integer|between:1,28', 'url_publicacion' => 'nullable|string|max:300', 'activo' => 'nullable|boolean']);

        return $this->hecho(fn () => $this->svc->guardarItem($r->user(), $d, $item), 'Ítem guardado.');
    }

    // ---------------------------------------------------------------- plazos y feriados
    public function plazos(Request $r)
    {
        $params = [];
        foreach (TransparenciaService::PARAMETROS as $k => [$etq, , $min, $max]) {
            $params[$k] = ['etiqueta' => $etq, 'valor' => $this->svc->param($k), 'min' => $min, 'max' => $max];
        }

        return view('app.transparencia.plazos', ['params' => $params, 'feriados' => TransparenciaFeriado::query()->orderBy('fecha')->get(), 'hoy' => now(Plazos::TZ),
            'tramites' => Tramite::query()->where('es_transparencia', true)->get(['id', 'nombre', 'plazo_dias', 'plazo_habiles'])]);
    }

    public function guardarParametros(Request $r)
    {
        return $this->hecho(fn () => $this->svc->guardarParametros($r->user(), $r->only(array_keys(TransparenciaService::PARAMETROS))), 'Parámetros guardados.');
    }

    public function feriadoAgregar(Request $r)
    {
        $d = $r->validate(['fecha' => 'required|date_format:Y-m-d', 'nombre' => 'required|string|max:200']);

        return $this->hecho(fn () => $this->svc->agregarFeriado($r->user(), $d['fecha'], $d['nombre']), 'Feriado agregado.');
    }

    public function feriadoQuitar(Request $r, TransparenciaFeriado $feriado)
    {
        return $this->hecho(fn () => $this->svc->quitarFeriado($r->user(), $feriado), 'Feriado eliminado.');
    }

    // ---------------------------------------------------------------- reportes
    public function reportes(Request $r, TransparenciaReporte $rep)
    {
        $d = $r->validate(['desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d', 'unidad_id' => 'nullable|integer|exists:unidades,id', 'tipo' => 'nullable|in:pasiva,activa', 'formato' => 'nullable|in:csv']);
        if (! empty($d['desde']) && ! empty($d['hasta']) && $d['hasta'] < $d['desde']) {
            throw ValidationException::withMessages(['hasta' => 'La fecha final no puede ser anterior a la inicial.']);
        }
        $tipo = $d['tipo'] ?? 'pasiva';
        $pasiva = $tipo === 'pasiva' ? $rep->pasiva($d['desde'] ?? null, $d['hasta'] ?? null, isset($d['unidad_id']) ? (int) $d['unidad_id'] : null) : null;
        $activa = $tipo === 'activa' ? $rep->activa() : null;
        if (($d['formato'] ?? null) === 'csv') {
            $filas = $pasiva['filas'] ?? $activa;
            $this->audit->registrar('transparencia.reporte_csv', null, null, ['tipo' => $tipo, 'filas' => count($filas)], true);
            $cab = $tipo === 'pasiva'
                ? ['Folio', 'Fecha de ingreso', 'Trámite', 'Etapa', 'Responsable', 'Vencimiento', 'Prorrogada', 'Fecha de término', 'Días hábiles', 'Resultado', 'Derivada a', 'Requerimientos', 'Requerimientos pendientes', 'Requerimientos vencidos']
                : ['Código', 'Información', 'Dirección responsable', 'Periodo', 'Estado', 'Fecha límite', 'Publicada el', 'Enlace'];

            return response(TransparenciaReporte::csv($cab, $filas), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="transparencia-'.$tipo.'-'.now(Plazos::TZ)->format('Ymd').'.csv"', 'X-Content-Type-Options' => 'nosniff']);
        }
        $this->audit->registrar('transparencia.reporte', null, null, ['tipo' => $tipo], true);

        return view('app.transparencia.reportes', ['f' => $d, 'tipo' => $tipo, 'pasiva' => $pasiva, 'activa' => $activa, 'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']), 'puedeCsv' => $r->user()->tienePermiso('transparencia.reportes')]);
    }
}
