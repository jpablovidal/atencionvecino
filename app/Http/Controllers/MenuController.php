<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\MenuItem;
use App\Models\Usuario;
use App\Services\Admin\MenuService;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct(private readonly MenuService $svc) {}

    public function index()
    {
        return view('app.admin.menu', ['sistema' => $this->svc->sistema(), 'overrides' => MenuItem::query()->whereNotNull('clave')->get()->keyBy('clave'), 'propios' => MenuItem::query()->whereNull('clave')->orderBy('area')->orderBy('orden')->get(), 'roles' => Usuario::ROLES]);
    }

    public function sistema(Request $r, string $clave)
    {
        return $this->aplicar(fn () => $this->svc->guardarSistema($clave, ['etiqueta' => $r->input('etiqueta'), 'orden' => $r->input('orden'), 'activo' => $r->boolean('activo')], $r->user()), 'Ítem actualizado.');
    }

    public function restablecer(string $clave)
    {
        return $this->aplicar(fn () => $this->svc->restablecerSistema($clave), 'Ítem restablecido.');
    }

    public function propio(Request $r, ?MenuItem $item = null)
    {
        $d = $r->validate(['area' => 'required|in:publico,ciudadano,app', 'etiqueta' => 'required|string|max:40', 'url' => 'required|string|max:255', 'orden' => 'required|integer|between:0,999', 'roles' => 'nullable|array', 'roles.*' => 'string']);
        $d += ['nueva_pestana' => $r->boolean('nueva_pestana'), 'activo' => $r->boolean('activo')];

        return $this->aplicar(fn () => $this->svc->guardarPropio($d, $item, $r->user()), 'Enlace guardado.');
    }

    public function eliminar(MenuItem $item)
    {
        return $this->aplicar(fn () => $this->svc->eliminarPropio($item), 'Enlace eliminado.');
    }

    private function aplicar(callable $f, string $ok)
    {
        try {
            $f();
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', $ok);
    }
}
