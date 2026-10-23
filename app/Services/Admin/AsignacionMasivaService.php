<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\Rol;
use App\Models\UnidadInterna;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Permisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Asignación masiva: dirección, rol principal, roles adicionales, permisos individuales (las «vistas» del menú siguen a los permisos) y estado.
 *
 * Cada persona se procesa por separado con las MISMAS reglas que la edición individual (separación de funciones, techo de permisos, último
 * administrador, autoprotección, superadministradores intocables): las que no pueden cambiarse se omiten con su motivo y el resto se aplica.
 */
class AsignacionMasivaService
{
    public const ACCIONES = ['unidad', 'rol', 'roles_agregar', 'roles_quitar', 'permisos', 'estado'];

    /** Tope de personas por operación (evita agotar el tiempo de la solicitud en hosting compartido). */
    public const MAXIMO = 500;

    public function __construct(private readonly PermisosUsuarioService $permisos, private readonly AuditoriaService $audit) {}

    /** Consulta de usuarios según los filtros de la pantalla. */
    public function consulta(array $f): Builder
    {
        $q = Usuario::query()->with('unidad')->orderBy('apellidos')->orderBy('nombres');
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('nombres', 'like', $like)->orWhere('apellidos', 'like', $like)->orWhere('run', 'like', $like)->orWhere('cargo', 'like', $like)->orWhere('correo', 'like', $like));
        }
        if (! empty($f['rol'])) {
            $rol = Rol::query()->where('clave', $f['rol'])->first();
            $q->where(fn ($w) => $w->where('rol', $f['rol'])->when($rol, fn ($x) => $x->orWhereIn('id', DB::table('usuario_roles')->where('rol_id', $rol->id)->select('usuario_id'))));
        }
        if (($f['unidad'] ?? '') === 'sin') {
            $q->whereNull('unidad_id');
        } elseif (! empty($f['unidad'])) {
            $q->where('unidad_id', (int) $f['unidad']);
        }
        if (! empty($f['estado'])) {
            $q->where('activo', $f['estado'] === 'activos');
        }
        if (! empty($f['origen'])) {
            $q->where('directorio', $f['origen'] === 'directorio');
        }

        return $q;
    }

    /**
     * Aplica la acción a las personas indicadas.
     *
     * @param  list<int>  $ids
     * @param  array<string,mixed>  $p  parámetros de la acción
     * @return array{aplicados:int,omitidos:list<array{id:int,nombre:string,motivo:string}>}
     *
     * @throws FlujoException
     */
    public function aplicar(string $accion, array $ids, array $p, Usuario $actor): array
    {
        if (! in_array($accion, self::ACCIONES, true)) {
            throw new FlujoException('Acción desconocida.');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            throw new FlujoException('Elija al menos una persona.');
        }
        if (count($ids) > self::MAXIMO) {
            throw new FlujoException('Se pueden modificar hasta '.self::MAXIMO.' personas por vez: acote el filtro o repita la operación.');
        }
        $params = $this->validar($accion, $p, $actor);
        $aplicados = 0;
        $omitidos = [];
        foreach (Usuario::query()->whereIn('id', $ids)->get() as $u) {
            try {
                $this->una($accion, $u, $params, $actor);
                $aplicados++;
            } catch (FlujoException $e) {
                $omitidos[] = ['id' => $u->id, 'nombre' => $u->nombreCompleto(), 'motivo' => $e->getMessage()];
            }
        }
        $this->audit->registrar('usuario.masivo', null, null, ['accion' => $accion, 'aplicados' => $aplicados, 'omitidos' => count($omitidos), 'detalle' => $this->resumenParams($accion, $params)], true);

        return ['aplicados' => $aplicados, 'omitidos' => $omitidos];
    }

    /** @return array<string,mixed> */
    private function validar(string $accion, array $p, Usuario $actor): array
    {
        switch ($accion) {
            case 'unidad':
                $unidad = isset($p['unidad_id']) && $p['unidad_id'] !== '' ? (int) $p['unidad_id'] : null;
                if ($unidad !== null && ! DB::table('unidades')->where('id', $unidad)->exists()) {
                    throw new FlujoException('La dirección elegida no existe.');
                }
                $dep = ! empty($p['departamento_id']) ? (int) $p['departamento_id'] : null;
                $sub = ! empty($p['subunidad_id']) ? (int) $p['subunidad_id'] : null;
                foreach ([[$dep, 'departamento'], [$sub, 'unidad']] as [$id, $tipo]) {
                    if ($id !== null && ($unidad === null || ! UnidadInterna::query()->whereKey($id)->where('tipo', $tipo)->where('unidad_id', $unidad)->exists())) {
                        throw new FlujoException('El departamento y la unidad deben pertenecer a la dirección elegida.');
                    }
                }

                return ['unidad_id' => $unidad, 'departamento_id' => $dep, 'subunidad_id' => $sub];
            case 'rol':
                $rol = Rol::query()->where('clave', (string) ($p['rol'] ?? ''))->where('activo', true)->first();
                if (! $rol) {
                    throw new FlujoException('Elija un rol principal válido.');
                }
                if ($rol->rol_base === 'admin' && ! $actor->tienePermiso('roles.gestionar')) {
                    throw new FlujoException('Solo quien administra roles puede asignar un rol de administración.');
                }

                return ['rol' => $rol->clave, 'base' => $rol->rol_base];
            case 'roles_agregar':
            case 'roles_quitar':
                $claves = array_values(array_unique(array_filter(array_map('strval', (array) ($p['roles'] ?? [])))));
                if ($claves === []) {
                    throw new FlujoException('Elija al menos un rol.');
                }
                if (Rol::query()->where('activo', true)->whereIn('clave', $claves)->count() !== count($claves)) {
                    throw new FlujoException('Alguno de los roles elegidos no existe o está inactivo.');
                }

                return ['roles' => $claves];
            case 'permisos':
                $cat = Permisos::catalogo();
                $ajustes = [];
                foreach ((array) ($p['perm'] ?? []) as $perm => $efecto) {
                    if (! in_array($efecto, ['conceder', 'denegar', 'limpiar'], true)) {
                        continue;
                    }
                    if (! isset($cat[$perm])) {
                        throw new FlujoException('Permiso desconocido.');
                    }
                    $ajustes[$perm] = $efecto;
                }
                if ($ajustes === []) {
                    throw new FlujoException('Marque al menos un permiso con «conceder», «denegar» o «quitar el ajuste».');
                }

                return ['perm' => $ajustes];
            default: // estado
                return ['activo' => ($p['activo'] ?? '') === '1' || ($p['activo'] ?? false) === true];
        }
    }

    private function una(string $accion, Usuario $u, array $p, Usuario $actor): void
    {
        if ($u->esSuperadmin() && ! $actor->esSuperadmin()) {
            throw new FlujoException('Es superadministrador(a): solo otro superadministrador puede modificarla.');
        }
        if ($u->id === $actor->id && in_array($accion, ['unidad', 'rol', 'estado'], true)) {
            throw new FlujoException('No puede modificar su propia '.['unidad' => 'unidad', 'rol' => 'rol', 'estado' => 'cuenta'][$accion].' (separación de funciones).');
        }
        match ($accion) {
            'unidad' => $this->unidad($u, $p),
            'rol' => $this->rol($u, $p),
            'estado' => $this->estado($u, $p['activo']),
            default => $this->roles($u, $accion, $p, $actor),
        };
    }

    private function unidad(Usuario $u, array $p): void
    {
        $u->forceFill($p)->save();
    }

    private function rol(Usuario $u, array $p): void
    {
        if ($u->rol === $p['rol']) {
            return;
        }
        if (in_array($p['base'], PermisosUsuarioService::BASES_AISLADAS, true) && $u->rolesAdicionales() !== []) {
            throw new FlujoException('Tiene roles adicionales y «'.$p['rol'].'» no se combina con otros: quítelos antes.');
        }
        if ($u->activo && $u->rolBase() === 'admin' && $p['base'] !== 'admin' && ! $this->quedaAdmin($u)) {
            throw new FlujoException('Es la última persona administradora activa.');
        }
        DB::transaction(function () use ($u, $p) {
            $u->rol = $p['rol'];
            if (in_array($p['base'], ['admin', 'auditor', 'autoridad', 'oirs'], true)) {
                $u->puede_firmar = false;
            }
            $u->save();
            DB::table('usuario_roles')->where('usuario_id', $u->id)->where('rol_id', Rol::query()->where('clave', $p['rol'])->value('id'))->delete();
        });
        DB::table('sessions')->where('user_id', $u->id)->delete();
    }

    private function estado(Usuario $u, bool $activo): void
    {
        if ($u->activo === $activo) {
            return;
        }
        if (! $activo && $u->rolBase() === 'admin' && ! $this->quedaAdmin($u)) {
            throw new FlujoException('Es la última persona administradora activa.');
        }
        $u->activo = $activo;
        $u->save();
        if (! $activo) {
            DB::table('sessions')->where('user_id', $u->id)->delete();
        }
    }

    private function roles(Usuario $u, string $accion, array $p, Usuario $actor): void
    {
        $actuales = $u->rolesAdicionales();
        $nuevos = match ($accion) {
            'roles_agregar' => array_values(array_unique(array_merge($actuales, array_values(array_filter($p['roles'] ?? [], fn ($c) => $c !== $u->rol))))),
            'roles_quitar' => array_values(array_diff($actuales, $p['roles'] ?? [])),
            default => $actuales,
        };
        $ind = $u->permisosIndividuales();
        if ($accion === 'permisos') {
            foreach ($p['perm'] as $perm => $efecto) {
                if ($efecto === 'limpiar') {
                    unset($ind[$perm]);
                } else {
                    $ind[$perm] = $efecto;
                }
            }
        }
        if ($accion !== 'permisos' && $nuevos === $actuales) {
            return; // nada que cambiar
        }
        $this->permisos->guardar($u, $nuevos, $ind, $actor);
    }

    /** ¿Queda otra persona administradora activa además de esta? */
    private function quedaAdmin(Usuario $u): bool
    {
        $claves = Rol::query()->where('rol_base', 'admin')->where('activo', true)->pluck('clave');

        return Usuario::query()->where('activo', true)->where('id', '!=', $u->id)->whereIn('rol', $claves)->exists();
    }

    private function resumenParams(string $accion, array $p): array
    {
        return match ($accion) {
            'permisos' => ['perm' => $p['perm']],
            default => $p,
        };
    }
}
