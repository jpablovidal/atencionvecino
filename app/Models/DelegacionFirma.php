<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tipo_documento_id
 * @property int $delegado_id
 * @property string $decreto_referencia
 * @property Carbon $vigente_desde
 * @property Carbon $vigente_hasta
 * @property int $creado_por
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class DelegacionFirma extends Model
{
    protected $table = 'delegaciones_firma';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean', 'vigente_desde' => 'date', 'vigente_hasta' => 'date'];

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function delegado(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'delegado_id');
    }
}
