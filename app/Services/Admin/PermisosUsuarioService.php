<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Permisos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Roles adicionales y permisos individuales de un usuario, y la matriz de permisos por usuario.
 *
 * Reglas (separación de funciones): el ROL PRINCIPAL (`usuarios.rol`) fija el rol base y sus invariantes. Quien tiene un rol base de administración o
 * de auditoría no recibe roles adicionales, y estos no pueden ser de esas bases: así el administrador nunca opera expedientes ni el personal
 * operativo obtiene la lectura total o la administración. Lo concedido individualmente nunca supera el techo de sus roles ni incluye los permisos
 * reservados al sistema. Siempre debe quedar al menos una persona con «Roles y permisos».
 */
class PermisosUsuarioService
{
    /** Bases que no se combinan con otras. */
    public const BASES_AISLADAS = ['admin', 'auditor'];

    public function __construct(private readonly AuditoriaService $audit) {}

    /**
     * Roles que se pueden agregar como adicionales a este usuario.
     *
     * @return Collection<int,Rol>
     */
    public function rolesDisponibles(Usuario $u): Collection
    {
        if (in_array($u->rolBase(), self::BASES_AISLADAS, true)) {
            return collect();
        }

        return Rol::query()->where('activo', true)->whereNotIn('rol_base', self::BASES_AISLADAS)->where('clave', '!=', $u->rol)->orderBy('nombre')->get();
    }

    /**
     * Matriz del usuario agrupada por tema.
     *
     * @return array<string,list<array{clave:string,etiqueta:string,efectivo:bool,origen:string,individual:?string,concedible:bool,reservado:bool}>>
     */
    public function matriz(Usuario $u): array
    {
        $u = $u->fresh() ?? $u;
        $nombres = Rol::query()->pluck('nombre', 'clave');
        $principal = Usuario::permisosDeRol($u->rol);
        $adicionales = [];
        foreach ($u->rolesAdicionales() as $c) {
            $adicionales[$c] = Usuario::permisosDeRol($c);
        }
        $techo = $u->techoPermisos();
        $ind = $u->permisosIndividuales();
        $super = $u->esSuperadmin();
        $out = [];
        foreach (Permisos::catalogo() as $clave => $d) {
            $reservado = ! empty($d[3]);
            $origen = '—';
            if ($super) {
                $origen = 'Superadministrador(a): todos';
            } elseif (($ind[$clave] ?? null) === 'denegar') {
                $origen = 'Denegado individualmente';
            } elseif (in_array($clave, $principal, true)) {
                $origen = 'Rol «'.($nombres[$u->rol] ?? $u->rol).'»';
            } elseif ($c = collect($adicionales)->search(fn ($p) => in_array($clave, $p, true))) {
                $origen = 'Rol adicional «'.($nombres[$c] ?? $c).'»';
            } elseif (($ind[$clave] ?? null) === 'conceder' && in_array($clave, $techo, true) && ! $reservado) {
                $origen = 'Concedido individualmente';
            } elseif (! in_array($clave, $techo, true)) {
                $origen = 'Fuera del alcance de sus roles';
            }
            $out[$d[1]][] = ['clave' => $clave, 'etiqueta' => $d[0], 'efectivo' => $u->tienePermiso($clave), 'origen' => $origen, 'individual' => $ind[$clave] ?? null,
                'concedible' => in_array($clave, $techo, true) && ! $reservado, 'reservado' => $reservado];
        }

        return $out;
    }

    /**
     * Guarda roles adicionales y permisos individuales (atómico y auditado).
     *
     * @param  array<int,string>  $adicionales  claves de rol
     * @param  array<string,string>  $individuales  permiso => conceder|denegar (otro valor = sin ajuste)
     *
     * @throws FlujoException
     */
    public function guardar(Usuario $u, array $adicionales, array $individuales, Usuario $actor): void
    {
        $adicionales = array_values(array_unique(array_filter(array_map('strval', $adicionales))));
        $roles = Rol::query()->where('activo', true)->whereIn('clave', $adicionales)->get();
        if ($roles->count() !== count($adicionales)) {
            throw new FlujoException('Alguno de los roles elegidos no existe o está inactivo.');
        }
        if ($adicionales !== [] && in_array($u->rolBase(), self::BASES_AISLADAS, true)) {
            throw new FlujoException('Un rol de '.($u->rolBase() === 'admin' ? 'administración' : 'auditoría').' no se combina con otros roles (separación de funciones).');
        }
        foreach ($roles as $r) {
            if (in_array($r->rol_base, self::BASES_AISLADAS, true) || $r->clave === $u->rol) {
                throw new FlujoException('El rol «'.$r->nombre.'» no puede asignarse como adicional.');
            }
        }
        $techo = Permisos::techo($u->rolBase());
        foreach ($roles as $r) {
            $techo = array_merge($techo, Permisos::techo($r->rol_base));
        }
        $cat = Permisos::catalogo();
        $ajustes = [];
        foreach ($individuales as $perm => $efecto) {
            if (! in_array($efecto, ['conceder', 'denegar'], true)) {
                continue;
            }
            if (! isset($cat[$perm])) {
                throw new FlujoException('Permiso desconocido.');
            }
            if ($efecto === 'conceder' && (! in_array($perm, $techo, true) || ! empty($cat[$perm][3]))) {
                throw new FlujoException('«'.$cat[$perm][0].'» no puede concederse a este usuario: supera lo que permiten sus roles o está reservado al sistema.');
            }
            $ajustes[$perm] = $efecto;
        }
        $teniaGestion = $u->tienePermiso('roles.gestionar');
        $antes = ['roles' => $u->rolesAdicionales(), 'ind' => $u->permisosIndividuales()];
        DB::transaction(function () use ($u, $roles, $ajustes, $teniaGestion) {
            DB::table('usuario_roles')->where('usuario_id', $u->id)->delete();
            $ahora = now();
            foreach ($roles as $r) {
                DB::table('usuario_roles')->insert(['usuario_id' => $u->id, 'rol_id' => $r->id, 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
            DB::table('usuario_permisos')->where('usuario_id', $u->id)->delete();
            foreach ($ajustes as $perm => $efecto) {
                DB::table('usuario_permisos')->insert(['usuario_id' => $u->id, 'permiso' => $perm, 'efecto' => $efecto, 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
            if ($teniaGestion && ! ($u->fresh() ?? $u)->tienePermiso('roles.gestionar') && ! $this->otraPersonaGestiona($u)) {
                throw new FlujoException('Debe quedar al menos una persona con el permiso «Roles y permisos»: no puede quitárselo a la última.');
            }
        });
        $this->audit->registrar('usuario.permisos', 'usuario', $u->id, [
            'roles_adicionales' => $adicionales, 'concedidos' => array_keys(array_filter($ajustes, fn ($e) => $e === 'conceder')), 'denegados' => array_keys(array_filter($ajustes, fn ($e) => $e === 'denegar')),
            'antes' => ['roles_adicionales' => $antes['roles'], 'individuales' => array_keys($antes['ind'])], 'propio' => $actor->id === $u->id,
        ], true);
        if ($actor->id !== $u->id) {
            DB::table('sessions')->where('user_id', $u->id)->delete(); // los cambios rigen desde un ingreso nuevo
        }
    }

    private function otraPersonaGestiona(Usuario $u): bool
    {
        $claves = Rol::query()->where('activo', true)->where('rol_base', 'admin')->pluck('clave')->push('admin')->unique()->all();

        return Usuario::query()->where('activo', true)->where('id', '!=', $u->id)->whereIn('rol', $claves)->get()->contains(fn (Usuario $x) => $x->tienePermiso('roles.gestionar'));
    }

    /**
     * Resumen para la lista de usuarios: roles adicionales y cantidad de permisos efectivos.
     *
     * @return array{adicionales:array<int,string>,efectivos:int,concedidos:int,denegados:int}
     */
    public function resumen(Usuario $u): array
    {
        $ind = $u->permisosIndividuales();
        $total = $u->esSuperadmin() ? count(Permisos::catalogo()) : count($u->permisosEfectivos());

        return ['adicionales' => $u->rolesAdicionales(), 'efectivos' => $total, 'concedidos' => count(array_filter($ind, fn ($e) => $e === 'conceder')), 'denegados' => count(array_filter($ind, fn ($e) => $e === 'denegar'))];
    }

    /** Evita que una celda se interprete como fórmula al abrir el CSV en una planilla. */
    public static function celda(string $v): string
    {
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }
}
