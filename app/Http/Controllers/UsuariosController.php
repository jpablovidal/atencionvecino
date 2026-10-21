<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Rol;
use App\Models\Unidad;
use App\Models\UnidadInterna;
use App\Models\Usuario;
use App\Services\Admin\AreaService;
use App\Services\AuditoriaService;
use App\Support\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Administración de usuarios funcionarios: roles dinámicos, búsqueda y resguardos (último administrador, autoprotección). */
class UsuariosController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit, private readonly AreaService $areas) {}

    public function index(Request $r)
    {
        $f = $r->validate(['q' => 'nullable|string|max:80', 'rol' => 'nullable|string|max:20', 'estado' => 'nullable|in:activo,inactivo']);
        $q = Usuario::query()->with('unidad')->orderBy('apellidos')->orderBy('nombres');
        if (! empty($f['q'])) {
            $t = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('nombres', 'like', $t)->orWhere('apellidos', 'like', $t)->orWhere('correo', 'like', $t)->orWhere('run', 'like', $t));
        }
        if (! empty($f['rol'])) {
            $q->where('rol', $f['rol']);
        }
        if (! empty($f['estado'])) {
            $q->where('activo', $f['estado'] === 'activo');
        }

        return view('app.admin.usuarios', ['filas' => $q->paginate(30)->withQueryString(), 'f' => $f + ['q' => null, 'rol' => null, 'estado' => null], 'roles' => Rol::query()->orderBy('nombre')->get()->keyBy('clave')]);
    }

    public function crear()
    {
        return view('app.admin.usuario_form', $this->datos(null));
    }

    public function editar(Usuario $usuario)
    {
        return view('app.admin.usuario_form', $this->datos($usuario));
    }

    /** @return array<string,mixed> */
    private function datos(?Usuario $u): array
    {
        return ['fila' => $u, 'roles' => Rol::query()->where(fn ($w) => $w->where('activo', true)->when($u, fn ($x) => $x->orWhere('clave', $u->rol)))->orderBy('nombre')->get(),
            'unidades' => Unidad::query()->where('activo', true)->orderBy('nombre')->get(),
            'internas' => UnidadInterna::query()->where('activo', true)->orderBy('nombre')->get(['id', 'unidad_id', 'tipo', 'nombre']),
            'areas' => $this->areas->habilitadas(), 'areaIds' => $u ? $u->areas()->pluck('areas.id')->all() : []];
    }

    public function guardar(Request $r, ?Usuario $usuario = null)
    {
        $run = Run::normalizar((string) $r->input('run'));
        if ($run === null) {
            abort(422, 'RUN inválido');
        }
        $r->merge(['run' => $run]);
        $d = $r->validate([
            'run' => ['required', Rule::unique('usuarios', 'run')->ignore($usuario?->id)],
            'nombres' => 'required|string|max:120', 'apellidos' => 'required|string|max:120', 'correo' => 'required|email|max:190',
            'cargo' => 'nullable|string|max:120', 'rol' => ['required', Rule::exists('roles', 'clave')->where('activo', true)],
            'unidad_id' => 'nullable|integer|exists:unidades,id', 'departamento_id' => 'nullable|integer|exists:unidades_internas,id', 'subunidad_id' => 'nullable|integer|exists:unidades_internas,id', 'puede_firmar' => 'nullable|boolean', 'areas' => 'nullable|array', 'areas.*' => 'integer',
        ]);
        foreach (['departamento_id' => 'departamento', 'subunidad_id' => 'unidad'] as $campo => $tipo) { // el departamento y la unidad deben pertenecer a la dirección elegida
            if (! empty($d[$campo]) && ! UnidadInterna::query()->whereKey($d[$campo])->where('tipo', $tipo)->where('unidad_id', (int) ($d['unidad_id'] ?? 0))->exists()) {
                return back()->withInput()->with('error', 'El departamento y la unidad deben pertenecer a la dirección elegida.');
            }
        }
        $areaIds = array_map('intval', (array) ($d['areas'] ?? []));
        unset($d['areas']);
        $d = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $d);
        $base = (string) Rol::query()->where('clave', $d['rol'])->value('rol_base');
        $actor = $r->user();
        if ($usuario && $usuario->id === $actor->id && ($usuario->rol !== $d['rol'] || (int) $usuario->unidad_id !== (int) ($d['unidad_id'] ?? 0) || (bool) $usuario->puede_firmar !== (bool) ($d['puede_firmar'] ?? false))) {
            return back()->withInput()->with('error', 'No puede modificar su propio rol, unidad ni habilitación de firma: pídalo a otra persona administradora (separación de funciones).');
        }
        if ($base === 'admin' && ! $actor->tienePermiso('roles.gestionar') && (! $usuario || $usuario->rol !== $d['rol'])) {
            return back()->withInput()->with('error', 'Solo quien administra roles puede asignar un rol de administración.');
        }
        $d['puede_firmar'] = in_array($base, ['admin', 'auditor', 'autoridad', 'oirs'], true) ? false : (bool) ($d['puede_firmar'] ?? false);
        if ($usuario && $usuario->activo && ! $this->quedaAdmin($usuario, $base, true)) {
            return back()->withInput()->with('error', 'Debe existir al menos un administrador activo: no puede cambiar el rol del último.');
        }
        $cambioRol = $usuario && $usuario->rol !== $d['rol'];
        $u = $usuario ?? new Usuario(['activo' => true]);
        $u->fill($d)->save();
        if ($actor->esSuperadmin() && $r->has('superadmin_form')) { // solo un superadministrador otorga o quita la condición (y no a sí mismo)
            $quiere = $r->boolean('superadmin') && $base === 'admin';
            if ($u->id !== $actor->id && (bool) $u->superadmin !== $quiere) {
                $u->forceFill(['superadmin' => $quiere])->save();
                $this->audit->registrar('usuario.superadmin', 'usuario', $u->id, ['superadmin' => $quiere, 'via' => 'pantalla'], true);
            }
        }
        try {
            $this->areas->asignarUsuario($u, $areaIds);
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        if ($cambioRol) {
            DB::table('sessions')->where('user_id', $u->id)->delete(); // el nuevo rol rige desde un ingreso nuevo
        }
        $this->audit->registrar('usuario.'.($usuario ? 'editar' : 'crear'), 'usuario', $u->id, ['rol' => $u->rol] + ($cambioRol ? ['cambio_rol' => true] : []), true);

        return redirect()->route('app.admin.index', 'usuarios')->with('ok', 'Usuario guardado.');
    }

    public function alternar(Request $r, Usuario $usuario)
    {
        if ($usuario->activo) {
            if ($usuario->id === $r->user()->id) {
                return back()->with('error', 'No puede desactivar su propia cuenta.');
            }
            if (! $this->quedaAdmin($usuario, $usuario->rolBase(), false)) {
                return back()->with('error', 'Debe existir al menos un administrador activo: no puede desactivar al último.');
            }
        }
        $usuario->activo = ! $usuario->activo;
        $usuario->save();
        if (! $usuario->activo) {
            DB::table('sessions')->where('user_id', $usuario->id)->delete();
        }
        $this->audit->registrar('usuario.activo', 'usuario', $usuario->id, ['activo' => $usuario->activo], true);

        return back()->with('ok', 'Estado actualizado.');
    }

    /** ¿Seguirá habiendo al menos un administrador activo tras el cambio? ($sigueActivo: el usuario permanece activo con $nuevaBase) */
    private function quedaAdmin(Usuario $u, string $nuevaBase, bool $sigueActivo): bool
    {
        if ($u->rolBase() !== 'admin') {
            return true;
        }
        $claves = Rol::query()->where('rol_base', 'admin')->where('activo', true)->pluck('clave');
        $otros = Usuario::query()->where('activo', true)->where('id', '!=', $u->id)->whereIn('rol', $claves)->count();

        return $otros > 0 || ($sigueActivo && $nuevaBase === 'admin');
    }
}
