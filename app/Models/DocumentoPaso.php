<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $documento_id
 * @property int $orden
 * @property string $tipo
 * @property int $usuario_id
 * @property string $estado
 * @property bool $simulada
 * @property string $firma_permitida
 * @property string|null $firma_tipo
 * @property string|null $checksum_original
 * @property string|null $comentario
 * @property Carbon|null $completado_en
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class DocumentoPaso extends Model
{
    protected $table = 'documento_pasos';

    protected $guarded = ['id'];

    protected $casts = ['simulada' => 'boolean', 'completado_en' => 'datetime'];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }
}
