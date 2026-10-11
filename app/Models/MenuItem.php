<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $area
 * @property string|null $clave
 * @property string $etiqueta
 * @property string|null $url
 * @property bool $nueva_pestana
 * @property int $orden
 * @property bool $activo
 * @property string|null $roles
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class MenuItem extends Model
{
    protected $table = 'menu_items';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean', 'nueva_pestana' => 'boolean'];
}
