<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $titulo
 * @property string $descripcion
 * @property string $severidad
 * @property string $estado
 * @property \Illuminate\Support\Carbon $detectado_en
 * @property bool $reportable_csirt
 * @property \Illuminate\Support\Carbon|null $alerta_temprana_en
 * @property \Illuminate\Support\Carbon|null $segundo_reporte_en
 * @property \Illuminate\Support\Carbon|null $informe_final_en
 * @property int $creado_por
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Incidente extends Model
{
    protected $table = 'incidentes';

    protected $guarded = ['id'];

    protected $casts = ['detectado_en' => 'datetime', 'reportable_csirt' => 'boolean', 'alerta_temprana_en' => 'datetime',
        'segundo_reporte_en' => 'datetime', 'informe_final_en' => 'datetime'];
}
