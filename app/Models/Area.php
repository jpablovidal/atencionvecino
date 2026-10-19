<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $habilitada
 * @property string|null $institucion_codigo
 * @property int $orden
 */
class Area extends Model
{
    protected $table = 'areas';

    protected $guarded = ['id'];

    protected $casts = ['habilitada' => 'boolean'];

    /** @return BelongsToMany<Usuario, $this> */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'usuario_areas', 'area_id', 'usuario_id');
    }
}
