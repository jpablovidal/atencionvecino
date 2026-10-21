<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $expediente_id
 * @property int $de_usuario_id
 * @property int $a_usuario_id
 * @property string $motivo
 * @property string $created_at
 * @property string|null $accion
 * @property string|null $estado
 */
class Derivacion extends Model
{
    protected $table = 'derivaciones';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['plazo' => 'date', 'resuelto_en' => 'datetime'];

    /** Acciones que se pueden pedir al derivar. */
    public const ACCIONES = [
        'responder' => 'Preparar y enviar la respuesta', 'informar' => 'Informar o emitir antecedentes', 'revisar' => 'Revisar y emitir opinión', 'conocimiento' => 'Tomar conocimiento',
        'redactar' => 'Redactar un documento (decreto u oficio)', 'archivar' => 'Archivar o cerrar', 'otra' => 'Otra (ver el motivo)',
    ];

    public const ESTADOS = ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'cumplida' => 'Cumplida', 'devuelta' => 'Devuelta (no corresponde)'];

    public function abierta(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_proceso'], true);
    }

    public function vencida(): bool
    {
        return $this->abierta() && $this->plazo !== null && $this->plazo->lt(now('America/Santiago')->startOfDay());
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function de(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'de_usuario_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function a(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'a_usuario_id');
    }
}
