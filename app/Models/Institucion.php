<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $tipo
 * @property string|null $codigo_padre
 * @property bool $activo
 * @property string $origen
 * @property Carbon|null $sincronizado_en
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Institucion extends Model
{
    protected $table = 'instituciones';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean', 'sincronizado_en' => 'datetime'];
}
