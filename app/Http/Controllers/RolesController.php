<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Roles y permisos. Los 5 roles del sistema son de solo lectura. Un rol personalizado hereda las invariantes de su rol base
 * y sus permisos nunca superan el techo de esa base (ni incluyen los reservados al sistema: roles, delegaciones).
 */
class RolesController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function index()
    {
        return view('app.admin.roles', [
            'roles' => Rol::query()->orderByDesc('sistema')->orderBy('nombre')->get(),
            'usuarios' => Usuario::query()->selectRaw('rol, count(*) as n')->groupBy('rol')->pluck('n', 'rol'),
            'catalogo' => Permisos::catalogo(),
        ]);
    }

    public function crear()
    {
        return view('app.admin.rol_form', $this->datos(null));
    }

    public function editar(Rol $rol)
    {
        return view('app.admin.rol_form', $this->datos($rol));
    }

    /** @return array<string,mixed> */
    private function datos(?Rol $rol): array
    {
        $ceil = [];
        foreach (['admin', 'funcionario', 'alcalde', 'secretario', 'auditor'] as $b) {
            $ceil[$b] = array_values(array_filter(Permisos::deBase($b), fn ($p) => empty(Permisos::catalogo()[$p][3])));
        }

        return ['rol' => $rol, 'catalogo' => Permisos::catalogo(), 'techo' => $ceil, 'sel' => $rol ? $rol->permisos() : [], 'asignados' => $rol ? $rol->usuarios()->count() : 0];
    }

    public function guardar(Request $r, ?Rol $rol = null)
    {
        abort_if($rol?->sistema, 403, 'Los roles del sistema no se pueden modificar.');
        $bases = Usuario::ROLES;
        $d = $r->validate([
            'clave' => [$rol ? 'nullable' : 'required', 'regex:/^[a-z][a-z0-9_]{2,19}$/', Rule::notIn(Usuario::ROLES), Rule::unique('roles', 'clave')],
            'nombre' => 'required|string|min:3|max:80', 'descripcion' => 'nullable|string|max:300',
            'rol_base' => ['required', Rule::in($bases)], 'permisos' => 'nullable|array', 'permisos.*' => ['string', Rule::in(array_keys(Permisos::catalogo()))],
        ]);
        $asignados = $rol ? $rol->usuarios()->count() : 0;
        if ($rol && $d['rol_base'] !== $rol->rol_base && $asignados > 0) {
            return back()->withInput()->with('error', 'No se puede cambiar el rol base de un rol con usuarios asignados; reasigne a los usuarios primero.');
        }
        $techo = array_filter(Permisos::deBase($d['rol_base']), fn ($p) => empty(Permisos::catalogo()[$p][3]));
        $fuera = array_diff($d['permisos'] ?? [], $techo);
        if ($fuera) {
            return back()->withInput()->with('error', 'Permisos no admitidos por el rol base «'.$d['rol_base'].'»: '.implode(', ', $fuera).'.');
        }
        DB::transaction(function () use ($d, &$rol) {
            if ($rol) {
                $rol->update(['nombre' => trim($d['nombre']), 'descripcion' => $d['descripcion'] ?? null, 'rol_base' => $d['rol_base']]);
            } else {
                $rol = Rol::query()->create(['clave' => $d['clave'], 'nombre' => trim($d['nombre']), 'descripcion' => $d['descripcion'] ?? null, 'rol_base' => $d['rol_base'], 'sistema' => false, 'activo' => true]);
            }
            DB::table('rol_permisos')->where('rol_id', $rol->id)->delete();
            foreach (array_values(array_unique($d['permisos'] ?? [])) as $p) {
                DB::table('rol_permisos')->insert(['rol_id' => $rol->id, 'permiso' => $p]);
            }
            $this->audit->registrar('rol.guardar', 'rol', $rol->id, ['clave' => $rol->clave, 'base' => $rol->rol_base, 'permisos' => count($d['permisos'] ?? [])], true);
        });
        $this->cerrarSesionesDelRol($rol->clave); // los permisos cambian: reautenticar a quienes lo tienen

        return redirect()->route('app.admin.roles')->with('ok', 'Rol guardado.');
    }

    public function alternar(Rol $rol)
    {
        abort_if($rol->sistema, 403, 'Los roles del sistema no se pueden desactivar.');
        if ($rol->activo && $rol->usuarios()->where('activo', true)->exists()) {
            return back()->with('error', 'El rol tiene usuarios activos asignados: reasígnelos antes de desactivarlo.');
        }
        $rol->update(['activo' => ! $rol->activo]);
        $this->audit->registrar('rol.activo', 'rol', $rol->id, ['activo' => $rol->activo], true);

        return back()->with('ok', 'Estado del rol actualizado.');
    }

    public function eliminar(Rol $rol)
    {
        abort_if($rol->sistema, 403, 'Los roles del sistema no se pueden eliminar.');
        if ($rol->usuarios()->exists()) {
            return back()->with('error', 'El rol tiene usuarios asignados (incluso inactivos): no se puede eliminar; desactívelo.');
        }
        DB::table('rol_permisos')->where('rol_id', $rol->id)->delete();
        $rol->delete();
        $this->audit->registrar('rol.eliminar', 'rol', $rol->id, ['clave' => $rol->clave], true);

        return redirect()->route('app.admin.roles')->with('ok', 'Rol eliminado.');
    }

    private function cerrarSesionesDelRol(string $clave): void
    {
        $ids = Usuario::query()->where('rol', $clave)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
        }
    }
}
