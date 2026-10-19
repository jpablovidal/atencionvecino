<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $comunicacion_id
 * @property int|null $archivo_id
 * @property string $nombre
 * @property string|null $id_externo
 * @property string $estado enviado|recibido|omitido
 * @property string|null $motivo
 * @property int|null $tamano
 */
class ComunicacionAnexo extends Model
{
    protected $table = 'comunicacion_anexos';

    protected $guarded = ['id'];

    /** @return BelongsTo<Archivo, $this> */
    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }
}
