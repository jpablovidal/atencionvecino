<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $expediente_id
 * @property int $de_usuario_id
 * @property int $a_usuario_id
 * @property string $motivo
 * @property string $created_at
 */
class Derivacion extends Model
{
    protected $table = 'derivaciones';

    protected $guarded = ['id'];

    public $timestamps = false;

    /** @return BelongsTo<Usuario, $this> */
    public function de(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'de_usuario_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function a(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'a_usuario_id');
    }
}
