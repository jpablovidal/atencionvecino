<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de la cadena de custodia de una microforma. Solo se agregan filas.
 *
 * @property int $id
 * @property int $microforma_id
 * @property string $tipo
 * @property string|null $detalle
 * @property int|null $usuario_id
 */
class MicroformaEvento extends Model
{
    public $timestamps = false;

    protected $table = 'archivo_eventos';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La cadena de custodia es de solo escritura.'));
        static::deleting(fn () => throw new \LogicException('La cadena de custodia es de solo escritura.'));
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
