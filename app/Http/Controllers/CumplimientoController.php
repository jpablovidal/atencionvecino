<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\CalidadIniciativa;
use App\Models\CalidadPlataforma;
use App\Services\Admin\CumplimientoService;
use App\Support\NormasTecnicas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cumplimiento normativo (Decretos 10 y 11): política de gestión documental, coordinador(a), catálogo de plataformas y plan de mejora continua. */
class CumplimientoController extends Controller
{
    public function __construct(private readonly CumplimientoService $svc) {}

    public function index()
    {
        $this->svc->sembrarCatalogo();

        return view('app.admin.cumplimiento', [
            'politica' => $this->svc->politica(), 'estadoPolitica' => $this->svc->estadoPolitica(), 'coordinador' => $this->svc->coordinador(), 'subcar' => $this->svc->subcaracteristicas(),
            'seguridad' => $this->svc->seguridad(), 'estadoSeguridad' => $this->svc->estadoSeguridad(),
            'plataformas' => CalidadPlataforma::query()->withCount(['iniciativas' => fn ($q) => $q->where('estado', '!=', 'descartada')])->orderByDesc('priorizada')->orderBy('nombre')->get(),
            'iniciativas' => CalidadIniciativa::query()->with('plataforma')->orderBy('etapa')->orderBy('plazo')->orderBy('id')->get(), 'resumen' => NormasTecnicas::resumen(), 'puedeEditar' => request()->user()->tienePermiso('cumplimiento.gestionar'),
        ]);
    }

    public function politica(Request $r)
    {
        $d = $r->validate(['decreto' => 'required|string|max:190', 'fecha_aprobacion' => 'required|date_format:Y-m-d|before_or_equal:today', 'revision_anios' => 'required|integer|between:1,3', 'responsable' => 'nullable|string|max:150', 'notas' => 'nullable|string|max:1000']);
        $this->svc->guardarPolitica($d, $r->user());

        return back()->with('ok', 'Política de Gestión Documental registrada.');
    }

    public function seguridad(Request $r)
    {
        $d = $r->validate(['decreto' => 'required|string|max:190', 'fecha_aprobacion' => 'required|date_format:Y-m-d|before_or_equal:today', 'revision_anios' => 'required|integer|between:1,3',
            'responsable_seguridad' => 'nullable|string|max:150', 'responsable_activos' => 'nullable|string|max:150', 'diagnostico_fecha' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'diagnostico_resumen' => 'nullable|string|max:2000', 'notas' => 'nullable|string|max:1000']);
        $this->svc->guardarSeguridad($d, $r->user());

        return back()->with('ok', 'Política de Seguridad de la Información y Ciberseguridad registrada.');
    }

    public function coordinador(Request $r)
    {
        $d = $r->validate(['titular' => 'required|string|max:150', 'subrogante' => 'nullable|string|max:150', 'decreto' => 'nullable|string|max:190', 'fecha' => 'nullable|date_format:Y-m-d', 'comunicado_en' => 'nullable|date_format:Y-m-d']);
        $this->svc->guardarCoordinador($d, $r->user());

        return back()->with('ok', 'Designación del Coordinador(a) de Transformación Digital registrada.');
    }

    public function plataforma(?CalidadPlataforma $plataforma = null)
    {
        return view('app.admin.cumplimiento_plataforma', ['p' => $plataforma]);
    }

    public function guardarPlataforma(Request $r, ?CalidadPlataforma $plataforma = null)
    {
        $d = $r->validate(['nombre' => 'required|string|min:3|max:150', 'descripcion' => 'nullable|string|max:1000', 'tipo' => ['required', Rule::in(['propia', 'externa'])], 'responsable' => 'nullable|string|max:120', 'linea_base' => 'nullable|string|max:2000',
            'linea_base_fecha' => 'nullable|date_format:Y-m-d', 'priorizada' => 'nullable|boolean', 'vida_util_hasta' => 'nullable|date_format:Y-m-d', 'activa' => 'nullable|boolean']);
        $this->svc->guardarPlataforma($d + ['priorizada' => $r->boolean('priorizada'), 'activa' => $r->boolean('activa')], $r->user(), $plataforma);

        return redirect()->route('app.admin.cumplimiento')->with('ok', 'Plataforma guardada.');
    }

    public function iniciativa(?CalidadIniciativa $iniciativa = null)
    {
        return view('app.admin.cumplimiento_iniciativa', ['i' => $iniciativa, 'plataformas' => CalidadPlataforma::query()->where('activa', true)->orderBy('nombre')->get()]);
    }

    public function guardarIniciativa(Request $r, ?CalidadIniciativa $iniciativa = null)
    {
        $d = $r->validate(['plataforma_id' => 'required|integer|exists:calidad_plataformas,id', 'descripcion' => 'required|string|min:5|max:2000', 'objetivo' => 'nullable|string|max:1000', 'etapa' => ['required', Rule::in(array_keys(CalidadIniciativa::ETAPAS))],
            'subcaracteristica' => 'nullable|string|max:120', 'metrica' => 'nullable|string|max:190', 'umbral_exitoso' => 'nullable|string|max:120', 'umbral_aceptable' => 'nullable|string|max:120', 'umbral_insuficiente' => 'nullable|string|max:120',
            'responsable' => 'nullable|string|max:120', 'plazo' => 'nullable|date_format:Y-m-d', 'costo_estimado' => 'nullable|integer|min:0|max:100000000000', 'riesgos' => 'nullable|string|max:2000', 'resultado' => 'nullable|string|max:3000',
            'estado' => ['required', Rule::in(array_keys(CalidadIniciativa::ESTADOS))]]);
        try {
            $this->svc->guardarIniciativa($d, $r->user(), $iniciativa);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.admin.cumplimiento')->with('ok', 'Iniciativa guardada.');
    }

    /** Informe del plan de mejora continua (vista imprimible con el contenido que pide el Decreto 11, art. 9 letra c). */
    public function informe()
    {
        return view('app.admin.cumplimiento_informe', ['plataformas' => CalidadPlataforma::query()->with(['iniciativas' => fn ($q) => $q->orderBy('etapa')->orderBy('id')])->where('activa', true)->orderBy('nombre')->get(),
            'coordinador' => $this->svc->coordinador(), 'politica' => $this->svc->politica(), 'estadoPolitica' => $this->svc->estadoPolitica(), 'subcar' => $this->svc->subcaracteristicas()]);
    }
}
