<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $proceso_codigo
 */
class Tramite extends Model
{
    protected $table = 'tramites';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** @return BelongsToMany<Unidad, $this> */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidad::class, 'tramite_unidad');
    }

    /** @return HasMany<TramiteAnexo, $this> */
    public function anexos(): HasMany
    {
        return $this->hasMany(TramiteAnexo::class)->where('activo', true);
    }
}
