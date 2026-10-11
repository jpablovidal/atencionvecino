<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Unidad extends Model
{
    protected $table = 'unidades';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];
}
