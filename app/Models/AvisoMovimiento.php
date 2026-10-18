<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso al ciudadano por un movimiento: uno por canal (casilla_unica o correo).
 *
 * @property int $id
 * @property int $movimiento_id
 * @property string $canal
 * @property string $estado
 * @property string|null $evidencia_tipo
 * @property string|null $evidencia
 * @property int $intentos
 * @property string|null $ultimo_error
 */
class AvisoMovimiento extends Model
{
    protected $table = 'avisos_movimiento';

    protected $guarded = ['id'];

    protected $casts = ['evidencia_en' => 'datetime'];

    /** @return BelongsTo<SolicitudMovimiento, $this> */
    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(SolicitudMovimiento::class, 'movimiento_id');
    }
}
