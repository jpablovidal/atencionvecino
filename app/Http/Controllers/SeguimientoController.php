<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\Unidad;
use App\Services\AuditoriaService;
use App\Services\SeguimientoService;
use App\Support\Plazos;
use App\Support\Run;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Seguimiento de solicitudes (solo lectura) para la autoridad (consulta con filtros) y la OIRS (responsables y plazos). */
class SeguimientoController extends Controller
{
    public function __construct(private readonly SeguimientoService $svc, private readonly AuditoriaService $audit) {}

    /** @return array<string,mixed> filtros validados y normalizados */
    private function filtros(Request $r): array
    {
        $d = $r->validate([
            'run' => 'nullable|string|max:15', 'folio' => 'nullable|string|max:30', 'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d',
            'unidad_id' => 'nullable|integer|exists:unidades,id', 'tramite_id' => 'nullable|integer|exists:tramites,id',
            'etapa' => 'nullable|in:'.implode(',', array_keys(SeguimientoService::ETAPAS)), 'plazo' => 'nullable|in:'.implode(',', array_keys(SeguimientoService::PLAZOS)),
        ]);
        $d = array_filter($d, fn ($v) => $v !== null && $v !== '');
        if (isset($d['run'])) {
            $run = Run::normalizar($d['run']);
            if (! $run) {
                throw ValidationException::withMessages(['run' => 'El RUN no es válido (revise el dígito verificador).']);
            }
            $d['run'] = $run;
        }
        if (isset($d['desde'], $d['hasta'])) {
            if ($d['hasta'] < $d['desde']) {
                throw ValidationException::withMessages(['hasta' => 'La fecha final no puede ser anterior a la inicial.']);
            }
            if (strtotime($d['hasta']) - strtotime($d['desde']) > 366 * 5 * 86400) {
                throw ValidationException::withMessages(['hasta' => 'El rango no puede superar 5 años.']);
            }
        }

        return $d;
    }

    private function auditar(string $accion, array $f, int $n): void
    {
        $this->audit->registrar($accion, null, null, ['filtros' => array_keys($f), 'run' => isset($f['run']) ? Run::enmascarar($f['run']) : null, 'resultados' => $n], true);
    }

    /** @return array<string,mixed> */
    private function comunes(): array
    {
        return ['unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']), 'tramites' => Tramite::query()->orderBy('nombre')->get(['id', 'nombre']),
            'etapas' => SeguimientoService::ETAPAS, 'plazos' => SeguimientoService::PLAZOS];
    }

    public function index(Request $r)
    {
        $this->permiso('seguimiento.consultar');
        $f = $this->filtros($r);
        $buscando = $f !== [];
        $pag = $buscando ? $this->svc->consulta($f)->paginate(20)->withQueryString() : null;
        $filas = $pag ? $this->svc->filas($pag->getCollection()) : collect();
        if ($buscando) {
            $this->auditar('seguimiento.consulta', $f, $pag->total());
        }

        return view('app.seguimiento.index', $this->comunes() + ['f' => $f, 'pag' => $pag, 'filas' => $filas, 'buscando' => $buscando]);
    }

    public function oirs(Request $r)
    {
        $this->permiso('oirs.ver');
        $vista = in_array($r->query('vista'), ['vencidas', 'por_vencer', 'devueltas', 'por_asignar', 'todas'], true) ? $r->query('vista') : 'vencidas';
        $f = $this->filtros($r);
        $base = match ($vista) {
            'vencidas' => ['plazo' => 'vencido'], 'por_vencer' => ['plazo' => 'por_vencer'], 'devueltas' => ['etapa' => 'devuelta'], 'por_asignar' => ['etapa' => 'por_asignar'], default => [],
        };
        $q = $this->svc->consulta($f + $base);
        if (in_array($vista, ['vencidas', 'por_vencer'], true)) {
            $q->reorder()->orderBy('solicitudes.created_at'); // las más antiguas primero
        }
        $pag = $q->paginate(20)->withQueryString();
        $filas = $this->svc->filas($pag->getCollection());
        $this->auditar('oirs.consulta', $f + ['vista' => $vista], $pag->total());
        $snap = $this->svc->instantanea();
        $activas = $snap->filter(fn ($x) => ! in_array($x['etapa'], ['terminada'], true));
        $kpis = [
            ['icono' => 'alert', 'valor' => $snap->where('plazo', 'vencido')->count(), 'etiqueta' => 'Plazos vencidos', 'nivel' => $snap->where('plazo', 'vencido')->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.oirs', ['vista' => 'vencidas'])],
            ['icono' => 'clock', 'valor' => $snap->where('plazo', 'por_vencer')->count(), 'etiqueta' => 'Por vencer (≤ '.config('sgd.plazos.aviso_dias').' días)', 'nivel' => $snap->where('plazo', 'por_vencer')->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.oirs', ['vista' => 'por_vencer'])],
            ['icono' => 'inbox', 'valor' => $snap->where('etapa', 'por_asignar')->count(), 'etiqueta' => 'Sin asignar', 'nivel' => 'info', 'url' => route('app.oirs', ['vista' => 'por_asignar'])],
            ['icono' => 'mail', 'valor' => $snap->where('etapa', 'devuelta')->count(), 'etiqueta' => 'Devueltas al solicitante', 'nivel' => 'info', 'url' => route('app.oirs', ['vista' => 'devueltas'])],
            ['icono' => 'folder', 'valor' => $activas->count(), 'etiqueta' => 'Solicitudes activas', 'nivel' => 'ok', 'url' => route('app.oirs', ['vista' => 'todas'])],
        ];

        return view('app.seguimiento.oirs', $this->comunes() + ['f' => $f, 'pag' => $pag, 'filas' => $filas, 'vista' => $vista, 'kpis' => $kpis]);
    }

    public function detalle(Request $r, string $folio)
    {
        $u = $r->user();
        abort_unless($u->tienePermiso('seguimiento.consultar') || $u->tienePermiso('oirs.ver'), 403);
        $s = Solicitud::query()->where('folio', $folio)->firstOrFail();
        $this->svc->cargar(new Collection([$s]));
        $s->load('expediente.derivaciones.de:id,nombres,apellidos', 'expediente.notificaciones', 'expediente.documentos.tipo:id,nombre', 'expediente.documentos.pasos.usuario:id,nombres,apellidos', 'devoluciones');
        $fila = $this->svc->fila($s);
        $e = $s->expediente;
        $eventos = collect([['ts' => $s->created_at, 'txt' => 'Solicitud ingresada por el solicitante.']]);
        if ($e) {
            $eventos->push(['ts' => $e->created_at, 'txt' => 'Expediente '.$e->codigo.' creado en '.($e->unidad?->nombre ?? 'la dirección').'.'.($e->responsable ? ' Responsable: '.$e->responsable->nombreCompleto().'.' : '')]);
            foreach ($e->derivaciones as $d) {
                $eventos->push(['ts' => $d->created_at, 'txt' => 'Derivado de '.$d->de?->nombreCompleto().' a '.$d->a?->nombreCompleto().'.']);
            }
            foreach ($e->documentos as $doc) {
                foreach ($doc->pasos as $p) {
                    $eventos->push(['ts' => $p->completado_en ?? $p->created_at, 'txt' => ($p->tipo === 'firma' ? 'Firma' : 'Visación').' de un documento ('.$doc->tipo?->nombre.'): '.$p->usuario?->nombreCompleto().' — '.$p->estado.'.']);
                }
            }
            foreach ($e->notificaciones as $n) {
                $eventos->push(['ts' => $n->evidencia_en ?? $n->created_at, 'txt' => 'Notificación al solicitante por '.str_replace('_', ' ', $n->canal).': '.$n->estado.'.']);
            }
            if ($e->cerrado_en) {
                $eventos->push(['ts' => $e->cerrado_en, 'txt' => 'Expediente cerrado: trámite terminado.']);
            }
        }
        foreach ($s->devoluciones as $dv) {
            $eventos->push(['ts' => $dv->created_at, 'txt' => 'Devuelta al solicitante para completar antecedentes (plazo hasta el '.$dv->vence_en->setTimezone(Plazos::TZ)->format('d-m-Y').').']);
            if ($dv->respondida_en) {
                $eventos->push(['ts' => $dv->respondida_en, 'txt' => 'El solicitante respondió la devolución.']);
            }
        }
        $this->audit->registrar('seguimiento.detalle', 'solicitud', $s->folio, [], true);

        return view('app.seguimiento.detalle', ['fila' => $fila, 'eventos' => $eventos->sortBy('ts')->values(), 'volver' => $u->tienePermiso('seguimiento.consultar') ? route('app.seguimiento') : route('app.oirs')]);
    }
}
