<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plataforma electrónica del catálogo del Decreto 11.
 *
 * @property int $id
 * @property string $nombre
 * @property string $tipo propia|externa
 * @property bool $priorizada
 * @property bool $activa
 */
class CalidadPlataforma extends Model
{
    protected $table = 'calidad_plataformas';

    protected $guarded = ['id'];

    protected $casts = ['priorizada' => 'boolean', 'activa' => 'boolean', 'linea_base_fecha' => 'date', 'vida_util_hasta' => 'date'];

    /** @return HasMany<CalidadIniciativa, $this> */
    public function iniciativas(): HasMany
    {
        return $this->hasMany(CalidadIniciativa::class, 'plataforma_id');
    }
}
