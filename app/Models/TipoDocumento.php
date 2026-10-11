<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $prefijo
 * @property bool $firma_alcalde
 * @property int $firmas_agil
 * @property int $firmas_clasico
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';

    protected $guarded = ['id'];

    protected $casts = ['firma_alcalde' => 'boolean', 'activo' => 'boolean'];
}
