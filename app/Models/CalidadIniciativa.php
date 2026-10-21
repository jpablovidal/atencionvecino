<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Iniciativa de mejora del Plan de Mejora Continua (Decreto 11).
 *
 * @property int $id
 * @property int $plataforma_id
 * @property int $etapa
 * @property string $estado
 */
class CalidadIniciativa extends Model
{
    protected $table = 'calidad_iniciativas';

    protected $guarded = ['id'];

    protected $casts = ['plazo' => 'date', 'etapa' => 'integer'];

    public const ETAPAS = [1 => 'Plan de Mejora Continua', 2 => 'Formulación presupuestaria', 3 => 'Implementación de mejoras', 4 => 'Evaluación de la calidad', 5 => 'Ajustes y consolidación'];

    public const ESTADOS = ['planificada' => 'Planificada', 'en_curso' => 'En curso', 'terminada' => 'Terminada', 'descartada' => 'Descartada'];

    /** @return BelongsTo<CalidadPlataforma, $this> */
    public function plataforma(): BelongsTo
    {
        return $this->belongsTo(CalidadPlataforma::class, 'plataforma_id');
    }
}
