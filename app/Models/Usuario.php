<?php

namespace App\Models;

use App\Support\Permisos;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\Access\Authorizable;

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

    public const ROLES = ['admin', 'funcionario', 'alcalde', 'secretario', 'auditor', 'autoridad', 'oirs', 'transparencia'];

    protected $table = 'usuarios';

    protected $guarded = ['id'];

    protected $casts = ['puede_firmar' => 'boolean', 'activo' => 'boolean'];

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    private ?string $rolBaseCache = null;

    /** @var array<int,string>|null */
    private ?array $permisosCache = null;

    /** Rol base (admin|funcionario|alcalde|secretario|auditor|autoridad|oirs): los roles personalizados heredan las invariantes de su base. */
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

    public function esRol(string ...$roles): bool
    {
        return in_array($this->rolBase(), $roles, true);
    }

    /** ¿Tiene el permiso? Roles del sistema: los de su base. Personalizados: los asignados, acotados por el techo de su base. */
    public function tienePermiso(string $permiso): bool
    {
        if (! $this->activo) {
            return false;
        }
        if ($this->permisosCache === null) {
            $base = $this->rolBase();
            $techo = Permisos::techo($base);
            if (in_array($this->rol, self::ROLES, true)) {
                $this->permisosCache = Permisos::deBase($base);
            } else {
                $rol = Rol::query()->where('clave', $this->rol)->where('activo', true)->first();
                $this->permisosCache = $rol ? array_values(array_intersect($rol->permisos(), $techo)) : [];
            }
        }

        return in_array($permiso, $this->permisosCache, true);
    }

    /** Usuarios que operan expedientes (roles base funcionario/alcalde/secretario, incluidos los roles personalizados). */
    public function scopeOperadores($q)
    {
        return $q->whereIn('rol', Rol::query()->whereIn('rol_base', ['funcionario', 'alcalde', 'secretario', 'transparencia'])->where('activo', true)->pluck('clave')->all());
    }

    /** Roles que operan expedientes (admin, auditor, autoridad y OIRS no). */
    public function operaExpedientes(): bool
    {
        return $this->esRol('funcionario', 'alcalde', 'secretario', 'transparencia');
    }

    public function getAuthPassword(): string
    {
        return ''; // sin contraseñas: solo ClaveÚnica
    }
}
