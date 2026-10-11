<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $expediente_id
 * @property int|null $documento_id
 * @property int $usuario_id
 * @property string $tipo
 * @property string $texto
 * @property string $created_at
 */
class Comentario extends Model
{
    protected $table = 'comentarios';

    protected $guarded = ['id'];

    public $timestamps = false;

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
