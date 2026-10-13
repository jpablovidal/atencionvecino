<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo_interno
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $codigo_cpat
 * @property int $plazo_dias
 * @property bool $activo
 * @property string $estado
 * @property int|null $unidad_propietaria_id
 * @property string|null $instrucciones
 * @property int $version
 * @property string|null $publicado_en
 * @property int|null $actualizado_por
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $proceso_codigo
 */
class Tramite extends Model
{
    protected $table = 'tramites';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** Disponibles para la ciudadanía: publicados (activo y publicado van siempre juntos). */
    public function scopePublicados($q)
    {
        return $q->where('estado', 'publicado')->where('activo', true);
    }

    public function estaPublicado(): bool
    {
        return $this->estado === 'publicado' && $this->activo;
    }

    /** @return HasMany<TramiteVersion, $this> */
    public function versiones()
    {
        return $this->hasMany(TramiteVersion::class);
    }

    /** @return BelongsTo<Unidad, $this> */
    public function propietaria(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_propietaria_id');
    }

    /** @return BelongsToMany<Unidad, $this> */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidad::class, 'tramite_unidad');
    }

    /** @return HasMany<TramiteAnexo, $this> */
    public function anexos(): HasMany
    {
        return $this->hasMany(TramiteAnexo::class)->where('activo', true)->orderBy('orden')->orderBy('id');
    }
}
