<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use App\Models\Unidad;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Catálogo público de trámites y servicios: SOLO los publicados por las direcciones (estado=publicado y activo). */
class CatalogoTramitesController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function index(Request $request)
    {
        $f = $request->validate(['q' => 'nullable|string|max:100', 'direccion' => 'nullable|integer|min:1', 'orden' => 'nullable|in:nombre,populares,recientes']);
        $orden = $f['orden'] ?? 'nombre';
        $q = Tramite::query()->publicados()->with('propietaria:id,nombre')->withCount('solicitudes');
        if (! empty($f['direccion'])) {
            $q->where('unidad_propietaria_id', (int) $f['direccion']);
        }
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('nombre', 'like', $like)->orWhere('descripcion', 'like', $like)->orWhere('instrucciones', 'like', $like));
        }
        match ($orden) {
            'populares' => $q->orderByDesc('solicitudes_count')->orderBy('nombre'),
            'recientes' => $q->orderByDesc('publicado_en')->orderByDesc('id'),
            default => $q->orderBy('nombre'),
        };
        $direcciones = Unidad::query()->whereIn('id', Tramite::query()->publicados()->whereNotNull('unidad_propietaria_id')->select('unidad_propietaria_id'))
            ->withCount(['propietariaDe as publicados_count' => fn ($c) => $c->publicados()])->orderBy('nombre')->get(['id', 'nombre']);
        $this->audit->registrar('publico.tramites', null, null, [], false, ['run' => null, 'rol' => 'publico']);

        return view('publico.tramites', [
            'tramites' => $q->paginate(12)->withQueryString(), 'direcciones' => $direcciones, 'f' => $f + ['q' => null, 'direccion' => null, 'orden' => 'nombre'], 'total' => Tramite::query()->publicados()->count(),
        ]);
    }

    /** Sugerencias del buscador: solo trámites publicados (nombre, dirección y enlace); mínimo 2 letras, máximo 8 resultados. */
    public function sugerencias(Request $request)
    {
        $d = $request->validate(['q' => 'required|string|min:2|max:100']);
        $like = '%'.addcslashes($d['q'], '%_\\').'%';
        $r = Tramite::query()->publicados()->with('propietaria:id,nombre')->where(fn ($w) => $w->where('nombre', 'like', $like)->orWhere('descripcion', 'like', $like))
            ->orderByRaw('case when nombre like ? then 0 else 1 end', [$like])->orderBy('nombre')->limit(8)->get()
            ->map(fn ($t) => ['id' => $t->id, 'nombre' => $t->nombre, 'direccion' => $t->propietaria?->nombre, 'url' => route('catalogo.ficha', [$t->id, Str::slug($t->nombre)])]);

        return response()->json($r->values())->header('Cache-Control', 'no-store');
    }

    /**
     * «Realizar este trámite": con sesión de ciudadano va directo al formulario; sin sesión recuerda SOLO el id del trámite
     * (entero, de un solo uso, 30 min) y envía a ClaveÚnica. Al volver, AuthController lo retoma.
     */
    public function iniciar(Request $request, int $id)
    {
        $t = Tramite::query()->publicados()->findOrFail($id);
        if ($request->session()->has('ciudadano')) { // vecino(a), o funcionario(a) con sesión dual
            return redirect()->route('ciudadano.nueva', $t);
        }
        if ($request->user()) { // personal municipal con sesión: los trámites se solicitan como vecino(a)
            return redirect()->route('catalogo.ficha', [$t->id, Str::slug($t->nombre)])->with('aviso', 'Los trámites se solicitan como vecino(a), con ClaveÚnica. Cierre su sesión de funcionario para continuar.');
        }
        if ($request->session()->has('ciudadano')) {
            return redirect()->route('ciudadano.nueva', $t);
        }
        $request->session()->put('tramite_pendiente', ['id' => $t->id, 'exp' => time() + 1800]);

        return redirect()->route('ingreso')->with('ok', 'Ingrese con su ClaveÚnica para continuar con el trámite.');
    }

    public function ficha(int $id, ?string $slug = null)
    {
        $t = Tramite::query()->publicados()->with(['anexos', 'unidades:id,nombre', 'propietaria:id,nombre'])->findOrFail($id);
        $correcto = Str::slug($t->nombre);
        if ($slug !== $correcto) {
            return redirect()->route('catalogo.ficha', [$t->id, $correcto], 301); // URL canónica (el slug no es parte de la autorización)
        }
        $this->audit->registrar('publico.tramite', 'tramite', $t->id, [], false, ['run' => null, 'rol' => 'publico']);

        $relacionados = Tramite::query()->publicados()->where('id', '!=', $t->id)->where('unidad_propietaria_id', $t->unidad_propietaria_id)->orderBy('nombre')->limit(5)->get(['id', 'nombre']);

        return view('publico.tramite', ['tramite' => $t, 'relacionados' => $relacionados]);
    }
}
