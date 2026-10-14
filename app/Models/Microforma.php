<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Microforma del archivo municipal.
 *
 * @property int $id
 * @property string|null $codigo
 * @property string $titulo
 * @property string|null $descripcion
 * @property string $tipo_soporte
 * @property int|null $unidad_id
 * @property string|null $serie
 * @property int $n_documentos
 * @property int $n_imagenes
 * @property string $estado
 * @property int $creado_por
 * @property int|null $certificada_por
 * @property string|null $acta_numero
 * @property int|null $acta_archivo_id
 * @property string|null $sha256_conjunto
 * @property string $disposicion
 * @property string $disposicion_estado
 */
class Microforma extends Model
{
    public const SOPORTES = ['microfilm_16mm' => 'Microfilm 16 mm', 'microfilm_35mm' => 'Microfilm 35 mm', 'microficha' => 'Microficha', 'digital' => 'Microforma digital (imágenes)'];

    public const ESTADOS = ['borrador' => 'Borrador', 'en_revision' => 'En revisión', 'certificada' => 'Certificada', 'anulada' => 'Anulada'];

    protected $table = 'archivo_microformas';

    protected $guarded = ['id'];

    protected $casts = ['fecha_desde' => 'date', 'fecha_hasta' => 'date', 'certificada_en' => 'datetime', 'disposicion_en' => 'datetime'];

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function certificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'certificada_por');
    }

    /** @return BelongsToMany<Archivo, $this> archivos maestros, en orden */
    public function archivos(): BelongsToMany
    {
        return $this->belongsToMany(Archivo::class, 'archivo_microforma_archivos', 'microforma_id', 'archivo_id')->withPivot('orden')->orderByPivot('orden')->orderBy('archivos.id');
    }

    /** @return HasMany<MicroformaItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MicroformaItem::class, 'microforma_id')->orderBy('orden')->orderBy('id');
    }

    /** @return HasMany<MicroformaEvento, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(MicroformaEvento::class, 'microforma_id')->orderBy('id');
    }

    public function editable(): bool
    {
        return $this->estado === 'borrador';
    }
}
