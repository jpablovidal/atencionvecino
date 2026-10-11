<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $expediente_id
 * @property int $servicio_id
 * @property int $usuario_id
 * @property string $run_consultado
 * @property string $finalidad
 * @property string $estado
 * @property int|null $http_status
 * @property string|null $error
 * @property int|null $archivo_id
 * @property Carbon $created_at
 */
class PiseeConsulta extends Model
{
    protected $table = 'pisee_consultas';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    /** @return BelongsTo<PiseeServicio, $this> */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(PiseeServicio::class, 'servicio_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
