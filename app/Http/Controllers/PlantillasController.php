<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Plantilla;
use App\Models\PlantillaVersion;
use App\Models\TipoDocumento;
use App\Models\Unidad;
use App\Services\AuditoriaService;
use App\Services\PdfService;
use App\Services\PlantillaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Catálogo de formatos (visible para toda la municipalidad según su alcance) y mantenedor de plantillas. */
class PlantillasController extends Controller
{
    public function __construct(private readonly PlantillaService $svc, private readonly AuditoriaService $audit) {}

    // ---------------------------------------------------------------- catálogo (todo el personal)
    public function catalogo(Request $r)
    {
        $this->permiso('plantillas.ver');
        $f = $r->validate(['q' => 'nullable|string|max:80', 'tipo' => 'nullable|integer', 'unidad' => 'nullable|integer']);
        $q = Plantilla::query()->visiblesPara($r->user())->where('activo', true)->with(['tipo', 'unidades']);
        if (! empty($f['q'])) {
            $t = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('nombre', 'like', $t)->orWhere('descripcion', 'like', $t));
        }
        if (! empty($f['tipo'])) {
            $q->where('tipo_documento_id', $f['tipo']);
        }
        if (! empty($f['unidad'])) {
            $q->where(fn ($w) => $w->where('alcance', 'todas')->orWhereIn('id', fn ($s) => $s->from('plantilla_unidades')->select('plantilla_id')->where('unidad_id', $f['unidad'])));
        }

        return view('app.formatos', [
            'filas' => $q->orderBy('nombre')->get()->groupBy(fn ($p) => $p->tipo->nombre), 'f' => $f + ['q' => null, 'tipo' => null, 'unidad' => null],
            'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('nombre')->get(), 'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(),
            'puedeGestionar' => $r->user()->tienePermiso('plantillas.gestionar'),
        ]);
    }

    public function ver(Request $r, Plantilla $plantilla)
    {
        $this->permiso('plantillas.ver');
        $this->autorizar($r, $plantilla);
        $plantilla->load(['tipo', 'unidades']);

        return view('app.formato', ['p' => $plantilla, 'html' => $this->svc->conMuestra($plantilla->contenido_html), 'variables' => PlantillaService::VARIABLES,
            'puedeGestionar' => $r->user()->tienePermiso('plantillas.gestionar')]);
    }

    public function pdf(Request $r, Plantilla $plantilla, PdfService $pdf)
    {
        $this->permiso('plantillas.ver');
        $this->autorizar($r, $plantilla);
        $bin = $pdf->html($this->svc->conMuestra($plantilla->contenido_html), 'Vista previa de formato — '.$plantilla->nombre.' (datos de ejemplo, sin validez)');

        return response($bin, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="formato-'.$plantilla->id.'.pdf"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    private function autorizar(Request $r, Plantilla $p): void
    {
        $gestor = $r->user()->tienePermiso('plantillas.gestionar');
        abort_unless($this->svc->visible($p, $r->user()) && ($p->activo || $gestor), 404);
    }

    // ---------------------------------------------------------------- mantenedor (administración)
    public function index(Request $r)
    {
        $f = $r->validate(['q' => 'nullable|string|max:80', 'tipo' => 'nullable|integer', 'estado' => 'nullable|in:activo,inactivo', 'alcance' => 'nullable|in:todas,unidades']);
        $q = Plantilla::query()->with(['tipo', 'unidades'])->orderBy('nombre');
        if (! empty($f['q'])) {
            $q->where('nombre', 'like', '%'.addcslashes($f['q'], '%_\\').'%');
        }
        foreach (['tipo' => 'tipo_documento_id', 'alcance' => 'alcance'] as $k => $col) {
            if (! empty($f[$k])) {
                $q->where($col, $f[$k]);
            }
        }
        if (! empty($f['estado'])) {
            $q->where('activo', $f['estado'] === 'activo');
        }

        return view('app.admin.plantillas', ['filas' => $q->paginate(30)->withQueryString(), 'f' => $f + ['q' => null, 'tipo' => null, 'estado' => null, 'alcance' => null],
            'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('nombre')->get()]);
    }

    public function crear()
    {
        return view('app.admin.plantilla_form', $this->datosForm(null));
    }

    public function editar(Plantilla $plantilla)
    {
        return view('app.admin.plantilla_form', $this->datosForm($plantilla));
    }

    /** @return array<string,mixed> */
    private function datosForm(?Plantilla $p): array
    {
        return ['p' => $p, 'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('nombre')->get(), 'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(),
            'sel' => $p ? $p->unidades()->pluck('unidades.id')->all() : [], 'variables' => PlantillaService::VARIABLES];
    }

    public function guardar(Request $r, ?Plantilla $plantilla = null)
    {
        $d = $r->validate([
            'nombre' => 'required|string|min:3|max:150', 'descripcion' => 'nullable|string|max:255', 'tipo_documento_id' => ['required', 'integer', Rule::exists('tipos_documento', 'id')],
            'contenido_html' => 'required|string|max:400000', 'alcance' => 'required|in:todas,unidades', 'unidades' => 'nullable|array', 'unidades.*' => ['integer', Rule::exists('unidades', 'id')],
        ]);
        try {
            $p = $this->svc->guardar($plantilla, $d, $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.admin.plantillas.editar', $p)->with('ok', 'Plantilla guardada (versión '.$p->version.').');
    }

    public function alternar(Plantilla $plantilla)
    {
        $plantilla->update(['activo' => ! $plantilla->activo]);
        $this->audit->registrar('plantilla.activo', 'plantilla', $plantilla->id, ['activo' => $plantilla->activo]);

        return back()->with('ok', 'Estado actualizado.');
    }

    public function duplicar(Request $r, Plantilla $plantilla)
    {
        $c = $this->svc->duplicar($plantilla, $r->user());

        return redirect()->route('app.admin.plantillas.editar', $c)->with('ok', 'Copia creada (inactiva). Revísela y actívela cuando esté lista.');
    }

    public function versiones(Plantilla $plantilla)
    {
        return view('app.admin.plantilla_versiones', ['p' => $plantilla, 'versiones' => PlantillaVersion::query()->where('plantilla_id', $plantilla->id)->orderByDesc('version')->get()]);
    }

    public function verVersion(Plantilla $plantilla, int $version)
    {
        $v = PlantillaVersion::query()->where('plantilla_id', $plantilla->id)->where('version', $version)->firstOrFail();

        return view('app.admin.plantilla_version', ['p' => $plantilla, 'v' => $v, 'html' => $this->svc->conMuestra($v->contenido_html)]);
    }

    public function restaurar(Request $r, Plantilla $plantilla, int $version)
    {
        $p = $this->svc->restaurar($plantilla, $version, $r->user());

        return redirect()->route('app.admin.plantillas.editar', $p)->with('ok', 'Versión '.$version.' restaurada como versión '.$p->version.'.');
    }
}
