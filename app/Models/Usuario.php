<?php

namespace App\Models;

use App\Support\Permisos;
use App\Support\PlantillaEstructura;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $run
 * @property string $nombres
 * @property string $apellidos
 * @property string $correo
 * @property string|null $cargo
 * @property string $rol
 * @property int|null $unidad_id
 * @property bool $puede_firmar
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $ultimo_acceso
 */
class Usuario extends Model implements AuthenticatableContract
{
    use Authenticatable, Authorizable;

    public const ROLES = ['admin', 'funcionario', 'alcalde', 'secretario', 'auditor', 'autoridad', 'oirs', 'transparencia', 'administrador_municipal'];

    protected $table = 'usuarios';

    protected $guarded = ['id', 'superadmin']; // el superadministrador solo se asigna con forceFill (comando o pantalla de usuarios), nunca por asignación masiva

    protected $casts = ['puede_firmar' => 'boolean', 'activo' => 'boolean', 'superadmin' => 'boolean', 'directorio' => 'boolean'];

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsToMany<Area, $this> */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'usuario_areas', 'usuario_id', 'area_id')->orderBy('areas.orden');
    }

    /** Iniciales (primer nombre y apellidos): se usan en la cadena de responsabilidad de los documentos. */
    public function iniciales(): string
    {
        return PlantillaEstructura::iniciales($this);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    private ?string $rolBaseCache = null;

    /** @var array<int,string>|null */
    private ?array $permisosCache = null;

    /** @var array<int,string>|null */
    private ?array $adicionalesCache = null;

    /** Rol base (admin|funcionario|alcalde|secretario|auditor|autoridad|oirs): los roles personalizados heredan las invariantes de su base. Es el del ROL PRINCIPAL. */
    public function rolBase(): string
    {
        if ($this->rolBaseCache !== null) {
            return $this->rolBaseCache;
        }
        if (in_array($this->rol, self::ROLES, true)) {
            return $this->rolBaseCache = $this->rol;
        }

        return $this->rolBaseCache = (string) (Rol::query()->where('clave', $this->rol)->where('activo', true)->value('rol_base') ?? 'sin-rol');
    }

    /**
     * Claves de los roles ADICIONALES activos (además del principal).
     *
     * @return array<int,string>
     */
    public function rolesAdicionales(): array
    {
        if ($this->adicionalesCache !== null) {
            return $this->adicionalesCache;
        }
        if (! $this->exists) {
            return $this->adicionalesCache = [];
        }

        return $this->adicionalesCache = array_values(array_diff(
            DB::table('usuario_roles')->join('roles', 'roles.id', '=', 'usuario_roles.rol_id')->where('usuario_roles.usuario_id', $this->id)->where('roles.activo', true)->orderBy('roles.nombre')->pluck('roles.clave')->all(),
            [$this->rol]
        ));
    }

    /**
     * Roles base de todos sus roles (principal y adicionales).
     *
     * @return array<int,string>
     */
    public function basesRol(): array
    {
        $bases = [$this->rolBase()];
        foreach ($this->rolesAdicionales() as $clave) {
            $bases[] = in_array($clave, self::ROLES, true) ? $clave : (string) (Rol::query()->where('clave', $clave)->where('activo', true)->value('rol_base') ?? 'sin-rol');
        }

        return array_values(array_unique($bases));
    }

    /** ¿Alguno de sus roles (principal o adicionales) tiene alguno de estos roles base? */
    public function esRol(string ...$roles): bool
    {
        return array_intersect($this->basesRol(), $roles) !== [];
    }

    /**
     * Permisos que le da UN rol: los de su base si es un rol base del sistema; si es personalizado, los asignados acotados por el techo de su base.
     *
     * @return array<int,string>
     */
    public static function permisosDeRol(string $clave): array
    {
        if (in_array($clave, self::ROLES, true)) {
            return Permisos::deBase($clave);
        }
        $rol = Rol::query()->where('clave', $clave)->where('activo', true)->first();

        return $rol ? array_values(array_intersect($rol->permisos(), Permisos::techo($rol->rol_base))) : [];
    }

    /**
     * Permisos individuales: [permiso => conceder|denegar].
     *
     * @return array<string,string>
     */
    public function permisosIndividuales(): array
    {
        return $this->exists ? DB::table('usuario_permisos')->where('usuario_id', $this->id)->pluck('efecto', 'permiso')->all() : [];
    }

    /**
     * Alcance habitual de sus roles (unión de los techos de sus bases): sirve para señalar lo que se concede fuera de él; ya no limita los permisos individuales.
     *
     * @return array<int,string>
     */
    public function techoPermisos(): array
    {
        $out = [];
        foreach ($this->basesRol() as $b) {
            $out = array_merge($out, Permisos::techo($b));
        }

        return array_values(array_unique($out));
    }

    /**
     * Permisos efectivos: unión de los de sus roles, más los concedidos y menos los denegados individualmente. Lo concedido individualmente puede ser cualquier permiso que no esté reservado al sistema (no se limita al rol).
     *
     * @return array<int,string>
     */
    public function permisosEfectivos(): array
    {
        if ($this->permisosCache !== null) {
            return $this->permisosCache;
        }
        $p = self::permisosDeRol($this->rol);
        foreach ($this->rolesAdicionales() as $clave) {
            $p = array_merge($p, self::permisosDeRol($clave));
        }
        $cat = Permisos::catalogo();
        foreach ($this->permisosIndividuales() as $perm => $efecto) {
            if ($efecto === 'conceder' && isset($cat[$perm]) && empty($cat[$perm][3])) { // personalizado: cualquier permiso no reservado, aunque su rol no lo incluya
                $p[] = $perm;
            } elseif ($efecto === 'denegar') {
                $p = array_diff($p, [$perm]);
            }
        }

        return $this->permisosCache = array_values(array_unique($p));
    }

    /** ¿Tiene el permiso? Une sus roles (principal y adicionales) y los permisos individuales; el superadministrador tiene todos los del catálogo. */
    public function tienePermiso(string $permiso): bool
    {
        if (! $this->activo) {
            return false;
        }
        if ($this->esSuperadmin()) {
            return array_key_exists($permiso, Permisos::catalogo()); // todas las vistas y acciones del catálogo
        }

        return in_array($permiso, $this->permisosEfectivos(), true);
    }

    /** Usuarios que operan expedientes (roles base funcionario/alcalde/secretario, incluidos los roles personalizados). */
    public function scopeOperadores($q)
    {
        $roles = Rol::query()->whereIn('rol_base', ['funcionario', 'alcalde', 'secretario', 'transparencia', 'administrador_municipal'])->where('activo', true)->get(['id', 'clave']);

        return $q->where(fn ($w) => $w->whereIn('rol', $roles->pluck('clave')->all())->orWhereIn('id', DB::table('usuario_roles')->whereIn('rol_id', $roles->pluck('id')->all())->select('usuario_id')));
    }

    /** Roles que operan expedientes (admin, auditor, autoridad y OIRS no). */
    /** Administrador con acceso a todas las vistas del sistema (debe tener base «admin»: mantiene el segundo factor obligatorio y no ve lo restringido). */
    public function esSuperadmin(): bool
    {
        return (bool) $this->activo && (bool) $this->superadmin && $this->rolBase() === 'admin';
    }

    /** Lectura de todo el contenido: auditoría y superadministración. */
    public function veTodo(): bool
    {
        return $this->rolBase() === 'auditor' || $this->esSuperadmin();
    }

    /** Puede abrir las vistas de contenido (de su unidad si opera, o de todo si lo ve todo). */
    public function puedeVerContenido(): bool
    {
        return $this->operaExpedientes() || $this->veTodo();
    }

    public function operaExpedientes(): bool
    {
        return $this->esRol('funcionario', 'alcalde', 'secretario', 'transparencia', 'administrador_municipal');
    }

    public function getAuthPassword(): string
    {
        return ''; // sin contraseñas: solo ClaveÚnica
    }
}
