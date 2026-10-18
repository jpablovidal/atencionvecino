<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Movimiento de una solicitud (lo que ve el ciudadano en su historial y por lo que se le avisa).
 *
 * @property int $id
 * @property int $solicitud_id
 * @property int|null $expediente_id
 * @property string $tipo
 * @property string $descripcion
 * @property int|null $unidad_id
 * @property string|null $created_at
 */
class SolicitudMovimiento extends Model
{
    public $timestamps = false;

    protected $table = 'solicitud_movimientos';

    protected $guarded = ['id'];

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return HasMany<AvisoMovimiento, $this> */
    public function avisos(): HasMany
    {
        return $this->hasMany(AvisoMovimiento::class, 'movimiento_id');
    }
}
