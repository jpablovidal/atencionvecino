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
        $f = $request->validate(['q' => 'nullable|string|max:100', 'direccion' => 'nullable|integer|min:1']);
        $q = Tramite::query()->publicados()->with('propietaria:id,nombre');
        if (! empty($f['direccion'])) {
            $q->where('unidad_propietaria_id', (int) $f['direccion']);
        }
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('nombre', 'like', $like)->orWhere('descripcion', 'like', $like)->orWhere('instrucciones', 'like', $like));
        }
        $direcciones = Unidad::query()->whereIn('id', Tramite::query()->publicados()->whereNotNull('unidad_propietaria_id')->select('unidad_propietaria_id'))
            ->withCount(['propietariaDe as publicados_count' => fn ($c) => $c->publicados()])->orderBy('nombre')->get(['id', 'nombre']);
        $this->audit->registrar('publico.tramites', null, null, [], false, ['run' => null, 'rol' => 'publico']);

        return view('publico.tramites', [
            'tramites' => $q->orderBy('nombre')->paginate(12)->withQueryString(), 'direcciones' => $direcciones, 'f' => $f + ['q' => null, 'direccion' => null],
        ]);
    }

    /**
     * «Realizar este trámite»: con sesión de ciudadano va directo al formulario; sin sesión recuerda SOLO el id del trámite
     * (entero, de un solo uso, 30 min) y envía a ClaveÚnica. Al volver, AuthController lo retoma.
     */
    public function iniciar(Request $request, int $id)
    {
        $t = Tramite::query()->publicados()->findOrFail($id);
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

        return view('publico.tramite', ['tramite' => $t]);
    }
}
