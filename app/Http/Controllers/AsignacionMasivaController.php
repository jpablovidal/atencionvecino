<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Rol;
use App\Models\Unidad;
use App\Models\UnidadInterna;
use App\Services\Admin\AsignacionMasivaService;
use App\Support\Permisos;
use Illuminate\Http\Request;

/** Asignación masiva de dirección, roles, permisos (y con ellos las vistas del menú) y estado a muchas personas a la vez. */
class AsignacionMasivaController extends Controller
{
    private const FILTROS = ['q' => 'nullable|string|max:80', 'rol' => 'nullable|string|max:30', 'unidad' => 'nullable|string|max:10', 'estado' => 'nullable|in:activos,inactivos', 'origen' => 'nullable|in:directorio,manual'];

    public function __construct(private readonly AsignacionMasivaService $svc) {}

    public function indice(Request $r)
    {
        $f = $r->validate(self::FILTROS);
        $pag = $this->svc->consulta($f)->paginate(50)->withQueryString();
        $grupos = [];
        foreach (Permisos::catalogo() as $clave => $d) {
            if (empty($d[3])) { // los permisos reservados al sistema no se conceden a mano
                $grupos[$d[1]][$clave] = $d[0];
            }
        }

        return view('app.admin.asignacion_masiva', [
            'pag' => $pag, 'f' => $f, 'unidades' => Unidad::query()->orderBy('nombre')->get(['id', 'nombre']),
            'internas' => UnidadInterna::query()->where('activo', true)->orderBy('nombre')->get(['id', 'unidad_id', 'tipo', 'nombre']),
            'roles' => Rol::query()->where('activo', true)->orderBy('nombre')->get(), 'nombres' => Rol::query()->pluck('nombre', 'clave'), 'grupos' => $grupos,
            'coincidencias' => $pag->total(), 'maximo' => AsignacionMasivaService::MAXIMO,
        ]);
    }

    public function aplicar(Request $r)
    {
        $d = $r->validate(['accion' => 'required|string|max:20', 'alcance' => 'required|in:marcados,filtro', 'ids' => 'nullable|array', 'ids.*' => 'integer'] + self::FILTROS);
        $ids = $d['alcance'] === 'filtro'
            ? $this->svc->consulta(array_intersect_key($d, self::FILTROS))->limit(AsignacionMasivaService::MAXIMO + 1)->pluck('id')->all()
            : (array) ($d['ids'] ?? []);
        $params = $r->only(['unidad_id', 'departamento_id', 'subunidad_id', 'roles', 'perm', 'activo']) + ['rol' => $r->input('rol_principal')];
        try {
            $res = $this->svc->aplicar($d['accion'], $ids, $params, $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $msg = 'Se aplicó el cambio a '.$res['aplicados'].' persona'.($res['aplicados'] === 1 ? '' : 's').'.'.($res['omitidos'] ? ' Se omitieron '.count($res['omitidos']).' (detalle abajo).' : '').' Los cambios de rol y permisos rigen desde el próximo ingreso de cada persona.';

        return redirect()->route('app.admin.asignacion', array_filter(array_intersect_key($d, self::FILTROS), fn ($v) => $v !== null && $v !== ''))->with($res['aplicados'] ? 'ok' : 'error', $msg)->with('omitidos', $res['omitidos']);
    }
}
