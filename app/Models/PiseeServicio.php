<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $institucion_codigo
 * @property string $ruta
 * @property string $metodo
 * @property string|null $param_run
 * @property string|null $descripcion
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class PiseeServicio extends Model
{
    protected $table = 'pisee_servicios';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];
}
