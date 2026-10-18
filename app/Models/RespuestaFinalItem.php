<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento o archivo del expediente que se entrega en la respuesta final.
 *
 * @property int $id
 * @property int $respuesta_id
 * @property int|null $documento_id
 * @property int|null $archivo_id
 */
class RespuestaFinalItem extends Model
{
    public $timestamps = false;

    protected $table = 'respuesta_final_items';

    protected $guarded = ['id'];

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** @return BelongsTo<Archivo, $this> */
    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }
}
