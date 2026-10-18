<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property int $unidad_id
 * @property string $instruccion
 * @property int $plazo_dias
 * @property Carbon $vence_en
 * @property string $estado
 * @property string|null $respuesta
 * @property Carbon|null $respondido_en
 */
class TransparenciaRequerimiento extends Model
{
    protected $table = 'transparencia_requerimientos';

    protected $guarded = ['id'];

    protected $casts = ['vence_en' => 'datetime', 'respondido_en' => 'datetime'];

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function respondidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'respondido_por');
    }

    public function vencido(): bool
    {
        return $this->estado === 'pendiente' && now()->greaterThan($this->vence_en);
    }
}
