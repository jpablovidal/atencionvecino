<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $clave
 * @property string $valor
 * @property int|null $actualizado_por
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ConfigSitio extends Model
{
    protected $table = 'config_sitio';

    protected $guarded = ['id'];
}
