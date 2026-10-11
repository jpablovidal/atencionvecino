<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $servicio
 * @property bool $activo
 * @property string $valores
 * @property string|null $secretos
 * @property int|null $actualizado_por
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ApiConfig extends Model
{
    protected $table = 'api_config';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];
}
