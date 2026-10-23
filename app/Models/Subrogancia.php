<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $titular_id
 * @property int $subrogante_id
 * @property Carbon $desde
 * @property Carbon $hasta
 * @property string|null $motivo
 * @property bool $activo
 * @property Carbon|null $terminada_en
 */
class Subrogancia extends Model
{
    protected $table = 'subrogancias';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean', 'desde' => 'datetime', 'hasta' => 'datetime', 'terminada_en' => 'datetime'];

    /** @return BelongsTo<Usuario, $this> */
    public function titular(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'titular_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function subrogante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subrogante_id');
    }

    /** Vigente ahora: no terminada y entre su fecha y hora de inicio y de término. */
    public function scopeVigentes($q)
    {
        $ahora = now();

        return $q->where('activo', true)->where('desde', '<=', $ahora)->where('hasta', '>=', $ahora);
    }

    /** programada | vigente | vencida | terminada */
    public function estado(): string
    {
        if (! $this->activo) {
            return 'terminada';
        }
        $ahora = now();

        return $this->hasta < $ahora ? 'vencida' : ($this->desde > $ahora ? 'programada' : 'vigente');
    }
}
