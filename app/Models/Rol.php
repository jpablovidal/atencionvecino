<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $clave
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $rol_base
 * @property bool $sistema
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Rol extends Model
{
    protected $table = 'roles';

    protected $guarded = ['id'];

    protected $casts = ['sistema' => 'boolean', 'activo' => 'boolean'];

    /** @return array<int,string> */
    public function permisos(): array
    {
        return DB::table('rol_permisos')->where('rol_id', $this->id)->pluck('permiso')->all();
    }

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol', 'clave');
    }
}
