<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $direccion
 * @property int|null $expediente_id
 * @property int|null $documento_id
 * @property string|null $institucion_codigo
 * @property string|null $institucion_nombre
 * @property string $asunto
 * @property string $estado
 * @property string|null $evidencia_tipo
 * @property string|null $evidencia
 * @property Carbon|null $evidencia_en
 * @property int $intentos
 * @property string|null $ultimo_error
 * @property int|null $archivo_id
 * @property int|null $unidad_id
 * @property int|null $creado_por
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ComunicacionOficial extends Model
{
    protected $table = 'comunicaciones_oficiales';

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
