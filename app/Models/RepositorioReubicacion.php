<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ruta_origen
 * @property string $ruta_destino
 * @property string $md5
 * @property string $migrado_en
 */
class RepositorioReubicacion extends Model
{
    public $timestamps = false;

    protected $table = 'repositorio_reubicaciones';

    protected $guarded = ['id'];
}
