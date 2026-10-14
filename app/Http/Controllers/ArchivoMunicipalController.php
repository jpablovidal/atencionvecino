<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Microforma;
use App\Models\Unidad;
use App\Services\ArchivoMunicipalService;
use Illuminate\Http\Request;

/** Archivo municipal: microformas. Los permisos se exigen en las rutas y de nuevo en el servicio. */
class ArchivoMunicipalController extends Controller
{
    public function __construct(private readonly ArchivoMunicipalService $svc) {}

    private function micro(int $id): Microforma
    {
        return Microforma::query()->findOrFail($id);
    }

    /** @param callable(): mixed $accion */
    private function ejecutar(callable $accion, string $ok, ?string $destino = null)
    {
        try {
            $accion();
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return ($destino ? redirect($destino) : back())->with('ok', $ok);
    }

    public function index(Request $r)
    {
        $f = $r->validate(['q' => 'nullable|string|max:100', 'estado' => 'nullable|in:'.implode(',', array_keys(Microforma::ESTADOS)), 'soporte' => 'nullable|in:'.implode(',', array_keys(Microforma::SOPORTES)),
            'unidad' => 'nullable|integer|min:1']);
        $q = Microforma::query()->with('unidad');
        if (! empty($f['estado'])) {
            $q->where('estado', $f['estado']);
        }
        if (! empty($f['soporte'])) {
            $q->where('tipo_soporte', $f['soporte']);
        }
        if (! empty($f['unidad'])) {
            $q->where('unidad_id', (int) $f['unidad']);
        }
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('codigo', 'like', $like)->orWhere('titulo', 'like', $like)->orWhere('serie', 'like', $like)->orWhere('ubicacion', 'like', $like)->orWhere('descripcion', 'like', $like));
        }
        $conteo = Microforma::query()->selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado');

        return view('app.archivo.index', [
            'micros' => $q->orderByDesc('id')->paginate(20)->withQueryString(), 'f' => $f + ['q' => null, 'estado' => null, 'soporte' => null, 'unidad' => null],
            'unidades' => Unidad::query()->orderBy('nombre')->get(['id', 'nombre']), 'conteo' => $conteo, 'pendDisp' => Microforma::query()->where('disposicion_estado', 'pendiente')->count(),
        ]);
    }

    public function crear()
    {
        return view('app.archivo.form', ['m' => null, 'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])]);
    }

    public function guardar(Request $r)
    {
        try {
            $m = $this->svc->crear($r->user(), $this->entrada($r));
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.archivo.ver', $m->id)->with('ok', 'Microforma registrada como borrador. Cargue sus archivos maestros e indexe los documentos.');
    }

    public function ver(Request $r, int $id)
    {
        $m = $this->micro($id)->load('unidad', 'creador', 'certificador', 'items', 'eventos.usuario');
        $u = $r->user();
        $acta = $m->acta_archivo_id ? Archivo::query()->find($m->acta_archivo_id) : null;

        return view('app.archivo.ver', ['m' => $m, 'archivos' => $m->archivos()->get(), 'acta' => $acta, 'u' => $u,
            'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']), 'esCreador' => (int) $m->creado_por === (int) $u->id,
            'esSolicitante' => (int) $m->disposicion_solicitada_por === (int) $u->id]);
    }

    public function actualizar(Request $r, int $id)
    {
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->actualizar($m, $r->user(), $this->entrada($r)), 'Cambios guardados.');
    }

    public function subir(Request $r, int $id)
    {
        $r->validate(['archivos' => 'required|array|max:40', 'archivos.*' => 'file']);
        $m = $this->micro($id);
        $n = 0;

        return $this->ejecutar(function () use ($r, $m, &$n) {
            $n = $this->svc->subirArchivos($m, $r->user(), (array) $r->file('archivos', []));
        }, 'Archivos cargados y cifrados.');
    }

    public function quitar(Request $r, int $id, int $archivo)
    {
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->quitarArchivo($m, $r->user(), $archivo), 'Archivo quitado.');
    }

    public function itemAgregar(Request $r, int $id)
    {
        $d = $r->validate(['referencia' => 'required|string|max:300', 'descripcion' => 'nullable|string|max:600', 'fojas' => 'nullable|string|max:60', 'posicion' => 'nullable|string|max:60', 'fecha_documento' => 'nullable|date']);
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->agregarItem($m, $r->user(), $d), 'Documento indexado.');
    }

    public function itemQuitar(Request $r, int $id, int $item)
    {
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->quitarItem($m, $r->user(), $item), 'Documento quitado del índice.');
    }

    public function enviar(Request $r, int $id)
    {
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->enviarARevision($m, $r->user()), 'Enviada a revisión del ministro de fe.');
    }

    public function devolver(Request $r, int $id)
    {
        $d = $r->validate(['motivo' => 'required|string|max:3000']);
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->devolver($m, $r->user(), $d['motivo']), 'Microforma devuelta a borrador.');
    }

    public function certificar(Request $r, int $id)
    {
        $d = $r->validate(['acta_numero' => 'required|string|max:200', 'acta' => 'required|file']);
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->certificar($m, $r->user(), $d['acta_numero'], $r->file('acta')), 'Microforma certificada. Los archivos quedaron inmutables y se fijó su hash.');
    }

    public function anular(Request $r, int $id)
    {
        $d = $r->validate(['motivo' => 'required|string|max:3000']);
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->anular($m, $r->user(), $d['motivo']), 'Microforma anulada.');
    }

    public function verificar(Request $r, int $id)
    {
        $m = $this->micro($id);
        try {
            $res = $this->svc->verificar($m, $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $res['ok'] ? back()->with('ok', 'Integridad verificada: '.$res['archivos'].' archivo(s) íntegros.')
            : back()->with('error', 'FALLA DE INTEGRIDAD: '.implode('; ', $res['problemas']));
    }

    public function archivo(Request $r, int $id, int $archivo)
    {
        $m = $this->micro($id);
        try {
            $f = $this->svc->descargar($m, $r->user(), $archivo);
        } catch (FlujoException $e) {
            abort(404, $e->getMessage());
        }

        return response($f['bytes'], 200, ['Content-Type' => $f['mime'], 'Content-Disposition' => 'attachment; filename="'.addcslashes($f['nombre'], '"\\').'"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    public function constancia(Request $r, int $id)
    {
        $m = $this->micro($id);
        try {
            $pdf = $this->svc->constanciaPdf($m, $r->user());
        } catch (FlujoException $e) {
            abort(403, $e->getMessage());
        }

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="constancia-'.$m->codigo.'.pdf"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    public function disposicionSolicitar(Request $r, int $id)
    {
        $d = $r->validate(['tipo' => 'required|in:eliminar,transferir', 'motivo' => 'required|string|max:4000']);
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->solicitarDisposicion($m, $r->user(), $d['tipo'], $d['motivo']), 'Solicitud de disposición registrada; debe autorizarla otra persona.');
    }

    public function disposicionResolver(Request $r, int $id)
    {
        $d = $r->validate(['decision' => 'required|in:autorizar,rechazar', 'acta' => 'nullable|string|max:200', 'motivo' => 'nullable|string|max:3000']);
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->resolverDisposicion($m, $r->user(), $d['decision'] === 'autorizar', (string) ($d['acta'] ?? ''), (string) ($d['motivo'] ?? '')),
            $d['decision'] === 'autorizar' ? 'Disposición autorizada.' : 'Disposición rechazada.');
    }

    public function disposicionEjecutar(Request $r, int $id)
    {
        $m = $this->micro($id);

        return $this->ejecutar(fn () => $this->svc->registrarEjecucion($m, $r->user()), 'Ejecución de la disposición registrada.');
    }

    /** @return array<string,mixed> */
    private function entrada(Request $r): array
    {
        return $r->validate(['titulo' => 'required|string|max:400', 'descripcion' => 'nullable|string|max:5000', 'tipo_soporte' => 'required|string|max:20', 'unidad_id' => 'nullable|integer',
            'serie' => 'nullable|string|max:300', 'ubicacion' => 'nullable|string|max:400', 'fecha_desde' => 'nullable|date', 'fecha_hasta' => 'nullable|date',
            'n_documentos' => 'nullable|integer|min:0', 'n_imagenes' => 'nullable|integer|min:0']);
    }
}
