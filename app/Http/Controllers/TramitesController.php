<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\ListaOpciones;
use App\Models\ProcesoCodificador;
use App\Models\Tramite;
use App\Models\Unidad;
use App\Services\TramiteService;
use Illuminate\Http\Request;

/** Trámites y servicios de cada dirección municipal (crear, editar, publicar, deshabilitar). */
class TramitesController extends Controller
{
    public function __construct(private readonly TramiteService $svc) {}

    /** El trámite se resuelve SIEMPRE dentro del ámbito del usuario (anti-IDOR: otra dirección = 404). */
    private function propio(Request $r, int $id): Tramite
    {
        return $this->svc->gestionables($r->user())->findOrFail($id);
    }

    public function index(Request $r)
    {
        $this->permiso('tramites.gestionar');
        $q = $this->svc->gestionables($r->user())->with('propietaria');
        if (in_array($r->query('estado'), ['borrador', 'publicado', 'deshabilitado'], true)) {
            $q->where('estado', $r->query('estado'));
        }

        return view('app.tramites.index', ['tramites' => $q->orderBy('nombre')->get(), 'u' => $r->user(), 'filtro' => $r->query('estado')]);
    }

    public function crear(Request $r)
    {
        $this->permiso('tramites.gestionar');

        return view('app.tramites.form', $this->datos($r, null));
    }

    public function guardar(Request $r)
    {
        $this->permiso('tramites.gestionar');
        try {
            $t = $this->svc->crear($r->user(), $this->entrada($r));
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.tramites.editar', $t)->with('ok', 'Trámite guardado como borrador. Cuando esté listo, publíquelo para ponerlo a disposición de la ciudadanía.');
    }

    public function editar(Request $r, int $id)
    {
        $t = $this->propio($r, $id)->load('anexos', 'unidades');

        return view('app.tramites.form', $this->datos($r, $t));
    }

    public function actualizar(Request $r, int $id)
    {
        $t = $this->propio($r, $id);
        try {
            $this->svc->actualizar($t, $r->user(), $this->entrada($r));
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.tramites.editar', $t)->with('ok', 'Cambios guardados.');
    }

    public function vista(Request $r, int $id)
    {
        $t = $this->propio($r, $id)->load('anexos', 'unidades');

        return view('app.tramites.vista', ['campos' => $t->campos()->get(), 'hoy' => now('America/Santiago')->format('Y-m-d'), 'tramite' => $t, 'problemas' => $this->svc->problemasParaPublicar($t), 'u' => $r->user()]);
    }

    public function historial(Request $r, int $id)
    {
        $t = $this->propio($r, $id);

        return view('app.tramites.historial', ['tramite' => $t, 'versiones' => $t->versiones()->orderByDesc('version')->get()]);
    }

    public function publicar(Request $r, int $id)
    {
        $t = $this->propio($r, $id);
        try {
            $adv = $this->svc->publicar($t, $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Trámite publicado: ya está disponible para la ciudadanía.'.($adv ? ' '.implode(' ', $adv) : ''));
    }

    public function deshabilitar(Request $r, int $id)
    {
        $t = $this->propio($r, $id);
        try {
            $this->svc->deshabilitar($t, $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Trámite deshabilitado. Las solicitudes ya ingresadas siguen su curso.');
    }

    public function duplicar(Request $r, int $id)
    {
        $t = $this->propio($r, $id);
        try {
            $c = $this->svc->duplicar($t, $r->user());
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('app.tramites.editar', $c)->with('ok', 'Copia creada como borrador.');
    }

    /** @return array<string,mixed> */
    private function entrada(Request $r): array
    {
        $d = $r->validate([
            'nombre' => 'required|string|max:300', 'descripcion' => 'nullable|string|max:5000', 'instrucciones' => 'nullable|string|max:8000', 'plazo_dias' => 'required|integer',
            'anexos' => 'nullable|array|max:40', 'anexos.*.nombre' => 'nullable|string|max:300', 'anexos.*.descripcion' => 'nullable|string|max:600', 'anexos.*.id' => 'nullable|integer', 'anexos.*.obligatorio' => 'nullable',
            'campos' => 'nullable|array|max:80', 'campos.*' => 'array', 'campos.*.id' => 'nullable|integer', 'campos.*.clave' => 'nullable|string|max:60', 'campos.*.etiqueta' => 'nullable|string|max:400', 'campos.*.tipo' => 'nullable|string|max:20',
            'campos.*.ayuda' => 'nullable|string|max:600', 'campos.*.obligatorio' => 'nullable', 'campos.*.eliminar' => 'nullable', 'campos.*.formula' => 'nullable|string|max:1000', 'campos.*.opciones' => 'nullable|string|max:30000',
            'campos.*.lista_id' => 'nullable|integer', 'campos.*.maxlen' => 'nullable|string|max:10', 'campos.*.max_elegidas' => 'nullable|string|max:5', 'campos.*.min' => 'nullable|string|max:20', 'campos.*.max' => 'nullable|string|max:20', 'campos.*.decimales' => 'nullable|string|max:3',
            'campos.*.prefijo' => 'nullable|string|max:40', 'campos.*.sufijo' => 'nullable|string|max:40', 'campos.*.fecha_min' => 'nullable|string|max:12', 'campos.*.fecha_max' => 'nullable|string|max:12',
            'unidad_propietaria_id' => 'nullable|integer', 'unidades' => 'nullable|array', 'unidades.*' => 'integer', 'codigo_interno' => 'nullable|string|max:40',
            'es_transparencia' => 'nullable|boolean', 'plazo_habiles' => 'nullable|boolean', 'codigo_cpat' => ['nullable', 'regex:'.MantenedorController::CPAT], 'proceso_codigo' => 'nullable|string|max:40|exists:procesos_codificador,codigo',
        ]);
        if ($r->boolean('campos_enviados')) {
            $d['campos'] = $d['campos'] ?? []; // el editor siempre envía esta marca: sin filas = se quitaron todos los campos
        }
        // los campos de administración solo se aceptan si el usuario ve todas las direcciones
        if (! TramiteService::veTodas($r->user())) {
            unset($d['unidad_propietaria_id'], $d['unidades'], $d['codigo_interno'], $d['codigo_cpat'], $d['proceso_codigo'], $d['es_transparencia'], $d['plazo_habiles']);
        }

        return $d;
    }

    /** @return array<string,mixed> */
    private function datos(Request $r, ?Tramite $t): array
    {
        $u = $r->user();

        return [
            'tramite' => $t, 'u' => $u, 'admin' => TramiteService::veTodas($u),
            'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(), 'unidad' => $u->unidad_id ? Unidad::query()->find($u->unidad_id) : null,
            'sel' => $t ? $t->unidades()->pluck('unidades.id')->all() : [], 'puedeEditar' => ! $t || TramiteService::puedeEditar($u, $t),
            'procesos' => ProcesoCodificador::query()->where('activo', true)->orderBy('nombre')->get(),
            'listas' => ListaOpciones::query()->withCount('items')->where('activa', true)->orderBy('nombre')->get(),
            'camposFilas' => $t ? $t->campos()->get()->map(fn ($c) => ['id' => $c->id, 'clave' => $c->clave, 'etiqueta' => $c->etiqueta, 'tipo' => $c->tipo, 'ayuda' => $c->ayuda, 'obligatorio' => $c->obligatorio, 'config' => $c->config])->all() : [],
        ];
    }
}
