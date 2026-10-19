<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Unidad;
use App\Services\Admin\AreaService;
use Illuminate\Http\Request;

/** Administrador: qué áreas tiene el municipio y a qué área pertenece cada unidad. */
class AreasController extends Controller
{
    public function __construct(private readonly AreaService $svc) {}

    public function index()
    {
        return view('app.admin.areas', ['areas' => $this->svc->todas(), 'habilitadas' => $this->svc->habilitadas(), 'unidades' => Unidad::query()->orderBy('nombre')->get()]);
    }

    public function guardar(Request $r)
    {
        $d = $r->validate(['area' => 'array', 'area.*.habilitada' => 'nullable|boolean', 'area.*.institucion_codigo' => 'nullable|string|max:30', 'unidad' => 'array', 'unidad.*' => 'nullable|integer']);
        try {
            $this->svc->guardar($r->user(), $d['area'] ?? []);
            foreach (Unidad::query()->get() as $un) {
                if (array_key_exists($un->id, $d['unidad'] ?? [])) {
                    $nuevo = $d['unidad'][$un->id] !== null ? (int) $d['unidad'][$un->id] : null;
                    if ($nuevo !== ($un->area_id ? (int) $un->area_id : null)) {
                        $this->svc->asignarUnidad($un, $nuevo);
                    }
                }
            }
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Áreas guardadas.');
    }
}
