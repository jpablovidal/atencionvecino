<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $institucion_codigo
 * @property bool $activo
 * @property string $origen
 * @property \Illuminate\Support\Carbon|null $sincronizado_en
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ProcesoCodificador extends Model
{
    protected $table = 'procesos_codificador';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean', 'sincronizado_en' => 'datetime'];
}
