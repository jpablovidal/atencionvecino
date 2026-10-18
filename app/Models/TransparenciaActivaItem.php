<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property int|null $unidad_id
 * @property int $dia_limite
 * @property string|null $url_publicacion
 * @property bool $activo
 */
class TransparenciaActivaItem extends Model
{
    protected $table = 'transparencia_activa_items';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return HasMany<TransparenciaActivaRegistro, $this> */
    public function registros(): HasMany
    {
        return $this->hasMany(TransparenciaActivaRegistro::class, 'item_id')->orderByDesc('periodo')->orderByDesc('id');
    }
}
