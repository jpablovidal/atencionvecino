<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tipo_documento_id
 * @property string $nombre
 * @property string $contenido_html
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Plantilla extends Model
{
    protected $table = 'plantillas';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }
}
