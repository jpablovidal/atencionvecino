<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Unidad extends Model
{
    protected $table = 'unidades';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** @return HasMany<Tramite, $this> */
    public function propietariaDe()
    {
        return $this->hasMany(Tramite::class, 'unidad_propietaria_id');
    }
}
