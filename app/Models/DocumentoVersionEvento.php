<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quién editó, visó, rechazó o firmó cada versión de un documento. Inmutable.
 *
 * @property int $id
 * @property int $documento_id
 * @property int $version
 * @property string $evento editado|visado|rechazado|firmado
 * @property int $usuario_id
 * @property string|null $detalle
 * @property string $created_at
 */
class DocumentoVersionEvento extends Model
{
    public $timestamps = false;

    protected $table = 'documento_version_eventos';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El historial es inmutable.'));
        static::deleting(fn () => throw new \LogicException('El historial es inmutable.'));
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
