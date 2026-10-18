<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Notificacion;
use App\Models\RespuestaFinal;
use App\Models\RespuestaFinalItem;
use App\Services\AuditoriaService;
use App\Services\DocumentoService;
use App\Services\FileVault;
use App\Services\RespuestaFinalService;
use Illuminate\Http\Request;

/** Respuesta final de notificación: instrucción (quien gestionó) y despacho (quien responde, p. ej. Oficina de Partes). */
class RespuestasController extends Controller
{
    public function __construct(private readonly RespuestaFinalService $svc, private readonly AuditoriaService $audit) {}

    private function hecho(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', $ok);
    }

    // ---------------------------------------------------------------- quien gestionó el expediente
    public function instruir(Request $r, Expediente $expediente)
    {
        $d = $r->validate(['documentos' => 'nullable|array|max:50', 'documentos.*' => 'integer', 'archivos' => 'nullable|array|max:50', 'archivos.*' => 'integer',
            'canal' => 'required|in:casilla_unica,presencial', 'motivo_presencial' => 'nullable|string|max:400', 'instruccion' => 'nullable|string|max:800', 'confirmo' => 'nullable|boolean']);
        $expediente->load('solicitud');

        return $this->hecho(fn () => $this->svc->instruir($r->user(), $expediente, $d['documentos'] ?? [], $d['archivos'] ?? [], $d['canal'], $d['motivo_presencial'] ?? null, $d['instruccion'] ?? null, $r->boolean('confirmo')),
            'Respuesta final indicada. Quien responde (p. ej. Oficina de Partes) ya la ve en su bandeja.');
    }

    public function anular(Request $r, RespuestaFinal $respuesta)
    {
        $d = $r->validate(['motivo' => 'required|string|max:400']);

        return $this->hecho(fn () => $this->svc->anular($r->user(), $respuesta, $d['motivo']), 'Instrucción anulada.');
    }

    // ---------------------------------------------------------------- quien responde (Oficina de Partes)
    public function bandeja(Request $r)
    {
        $f = $r->validate(['estado' => 'nullable|in:instruida,despachada,anulada,todas', 'q' => 'nullable|string|max:40']);
        $estado = $f['estado'] ?? 'instruida';
        $q = RespuestaFinal::query()->with('solicitud:id,folio,nombres,apellidos,run,correo', 'expediente:id,codigo,estado,unidad_id', 'expediente.unidad:id,nombre', 'instructor:id,nombres,apellidos', 'notificaciones')->withCount('items')->orderByDesc('id');
        if ($estado !== 'todas') {
            $q->where('estado', $estado);
        }
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->whereHas('solicitud', fn ($s) => $s->where('folio', 'like', $like)->orWhere('run', 'like', $like))->orWhereHas('expediente', fn ($e) => $e->where('codigo', 'like', $like)));
        }
        $cuenta = fn (string $e) => RespuestaFinal::query()->where('estado', $e)->count();

        return view('app.respuestas.index', ['pag' => $q->paginate(20)->withQueryString(), 'f' => ['estado' => $estado, 'q' => $f['q'] ?? ''], 'porDespachar' => $cuenta('instruida'), 'despachadas' => $cuenta('despachada'), 'canales' => RespuestaFinalService::CANALES]);
    }

    public function ver(RespuestaFinal $respuesta)
    {
        $respuesta->load('solicitud.tramite', 'expediente.unidad', 'expediente.responsable', 'instructor', 'despachador', 'items.documento.tipo', 'items.archivo', 'notificaciones.documento', 'notificaciones.archivo');

        return view('app.respuestas.show', ['r' => $respuesta, 'canales' => RespuestaFinalService::CANALES]);
    }

    public function despachar(Request $r, RespuestaFinal $respuesta)
    {
        $d = $r->validate(['acta' => 'nullable|string|max:400']);

        return $this->hecho(fn () => $this->svc->despachar($r->user(), $respuesta, $d['acta'] ?? null), $respuesta->canal === 'presencial'
            ? 'Entrega física registrada.' : 'Respuesta despachada: Casilla Única y copia al correo. Registre la evidencia de cada notificación cuando exista.');
    }

    public function evidencia(Request $r, RespuestaFinal $respuesta, Notificacion $notificacion)
    {
        $d = $r->validate(['valor' => 'required|string|max:250']);

        return $this->hecho(fn () => $this->svc->registrarEvidencia($r->user(), $respuesta, $notificacion, $d['valor']), 'Evidencia registrada.');
    }

    public function reintentar(Request $r, RespuestaFinal $respuesta)
    {
        try {
            $n = $this->svc->reintentar($r->user(), $respuesta);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', $n ? "Se reintentaron $n envíos." : 'No hay envíos para reintentar automáticamente (en modo outbox se registra la evidencia a mano).');
    }

    /** Documento o archivo de la respuesta, para imprimirlo en una entrega física o revisarlo antes de despachar. Queda en la auditoría. */
    public function item(Request $r, RespuestaFinal $respuesta, RespuestaFinalItem $item, FileVault $vault, DocumentoService $docs)
    {
        abort_unless((int) $item->respuesta_id === $respuesta->id && in_array($respuesta->estado, ['instruida', 'despachada'], true), 404);
        if ($item->documento_id) {
            $d = Documento::query()->findOrFail($item->documento_id);
            $this->audit->registrar('respuesta.descarga', 'documento', $d->id, ['respuesta' => $respuesta->id], true);

            return descarga($docs->leerPdf($d), 'documento-'.$d->numero.'-'.$d->anio.'.pdf', 'application/pdf');
        }
        $a = Archivo::query()->findOrFail($item->archivo_id);
        $this->audit->registrar('respuesta.descarga', 'archivo', $a->id, ['respuesta' => $respuesta->id], true);

        return descarga($vault->get($a->ruta, $a->sha256), $a->nombre_original, $a->mime);
    }
}
