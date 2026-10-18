<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Respuesta final de notificación de una solicitud: quien gestionó indica qué entregar; quien responde la despacha.
 *
 * @property int $id
 * @property int $expediente_id
 * @property int $solicitud_id
 * @property string $estado instruida|despachada|anulada
 * @property string $canal casilla_unica|presencial
 * @property string|null $motivo_presencial
 * @property string|null $instruccion
 * @property int $instruida_por
 * @property int|null $despachada_por
 * @property Carbon|null $despachada_en
 * @property string|null $anulada_motivo
 * @property Carbon|null $created_at
 */
class RespuestaFinal extends Model
{
    protected $table = 'respuestas_finales';

    protected $guarded = ['id'];

    protected $casts = ['despachada_en' => 'datetime'];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'instruida_por');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function despachador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'despachada_por');
    }

    /** @return HasMany<RespuestaFinalItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RespuestaFinalItem::class, 'respuesta_id')->orderBy('id');
    }

    /** @return HasMany<Notificacion, $this> */
    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'respuesta_id')->orderBy('id');
    }

    /** Estado de la entrega: sin_despachar | en_curso (falta evidencia) | con_fallas | entregada | anulada. */
    public function entrega(): string
    {
        if ($this->estado === 'anulada') {
            return 'anulada';
        }
        if ($this->estado !== 'despachada') {
            return 'sin_despachar';
        }
        $n = $this->notificaciones;
        if ($n->isNotEmpty() && $n->every(fn ($x) => $x->estado === 'enviada')) {
            return 'entregada';
        }

        return $n->contains('estado', 'fallida') ? 'con_fallas' : 'en_curso';
    }
}
