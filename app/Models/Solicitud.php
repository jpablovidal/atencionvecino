<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $folio
 * @property string $run
 * @property string $nombres
 * @property string $apellidos
 * @property string $correo
 * @property int $tramite_id
 * @property string $detalle
 * @property string $canal_notificacion
 * @property string $estado
 * @property int|null $expediente_id
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Solicitud extends Model
{
    protected $table = 'solicitudes';

    protected $guarded = ['id'];

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return HasMany<Archivo, $this> */
    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    /** @return HasMany<Devolucion, $this> */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class)->orderBy('id');
    }

    /** @return HasMany<Notificacion, $this> */
    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    public function devolucionPendiente(): ?Devolucion
    {
        return $this->devoluciones()->whereNull('respondida_en')->latest('id')->first();
    }
}
