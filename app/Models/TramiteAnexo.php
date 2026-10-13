<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tramite_id
 * @property string $nombre
 * @property bool $obligatorio
 * @property string|null $descripcion
 * @property int $orden
 * @property bool $activo
 */
class TramiteAnexo extends Model
{
    protected $table = 'tramite_anexos';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['obligatorio' => 'boolean', 'activo' => 'boolean'];
}
