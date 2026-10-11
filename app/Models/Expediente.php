<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property int|null $solicitud_id
 * @property int $unidad_id
 * @property int|null $responsable_id
 * @property string $estado
 * @property array<mixed> $metadatos
 * @property string|null $motivo_reapertura
 * @property Carbon|null $cerrado_en
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string $origen
 * @property int|null $comunicacion_id
 */
class Expediente extends Model
{
    protected $table = 'expedientes';

    protected $guarded = ['id'];

    protected $casts = ['metadatos' => 'array', 'cerrado_en' => 'datetime'];

    /** @return BelongsTo<Solicitud, $this> */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /** @return HasMany<Documento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class)->orderBy('id');
    }

    /** @return HasMany<Archivo, $this> */
    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    /** @return HasMany<Comentario, $this> */
    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class)->orderBy('id');
    }

    /** @return HasMany<Derivacion, $this> */
    public function derivaciones(): HasMany
    {
        return $this->hasMany(Derivacion::class)->orderBy('id');
    }

    /** @return HasMany<Notificacion, $this> */
    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }
}
