<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $expediente_id
 * @property int|null $documento_id
 * @property int $solicitud_id
 * @property string $canal
 * @property string $estado
 * @property string $destinatario_run
 * @property string|null $destinatario_correo
 * @property string|null $evidencia_tipo
 * @property string|null $evidencia
 * @property Carbon|null $evidencia_en
 * @property int $intentos
 * @property string|null $ultimo_error
 * @property int $creado_por
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $guarded = ['id'];

    protected $casts = ['evidencia_en' => 'datetime'];

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}
