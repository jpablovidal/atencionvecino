<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property int $creado_por
 * @property string $observacion
 * @property int $plazo_dias
 * @property \Illuminate\Support\Carbon $vence_en
 * @property string|null $respuesta
 * @property \Illuminate\Support\Carbon|null $respondida_en
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $guarded = ['id'];

    protected $casts = ['vence_en' => 'datetime', 'respondida_en' => 'datetime'];
}
