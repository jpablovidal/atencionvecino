<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Asociación de otro expediente o de una microforma al expediente que se gestiona.
 *
 * @property int $id
 * @property int $expediente_id
 * @property string $tipo expediente|microforma
 * @property int|null $origen_expediente_id
 * @property int|null $microforma_id
 * @property string $motivo
 * @property int $creado_por
 * @property Carbon|null $created_at
 */
class ExpedienteVinculo extends Model
{
    protected $table = 'expediente_vinculos';

    protected $guarded = ['id'];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Expediente, $this> */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'origen_expediente_id');
    }

    /** @return BelongsTo<Microforma, $this> */
    public function microforma(): BelongsTo
    {
        return $this->belongsTo(Microforma::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
