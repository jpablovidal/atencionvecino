<?php

namespace App\Models;

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
 */
class Usuario extends Model implements AuthenticatableContract
{
    use Authenticatable, Authorizable;

    public const ROLES = ['admin', 'funcionario', 'alcalde', 'secretario', 'auditor'];

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

    public function esRol(string ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }

    /** Roles que operan expedientes (el admin no). */
    public function operaExpedientes(): bool
    {
        return $this->esRol('funcionario', 'alcalde', 'secretario');
    }

    public function getAuthPassword(): string
    {
        return ''; // sin contraseñas: solo ClaveÚnica
    }
}
