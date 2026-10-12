<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $run
 * @property string $tipo
 * @property string $nombres
 * @property string $apellidos
 * @property string $password
 * @property bool $activo
 * @property string|null $ultimo_ingreso
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class CuentaPrueba extends Model
{
    protected $table = 'cuentas_prueba';

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected $casts = ['activo' => 'boolean'];
}
