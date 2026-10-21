<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Departamento o unidad dentro de una dirección municipal (organigrama del directorio de funcionarios).
 *
 * @property int $id
 * @property int $unidad_id
 * @property int|null $padre_id
 * @property string $tipo departamento|unidad
 * @property string $nombre
 * @property bool $activo
 */
class UnidadInterna extends Model
{
    protected $table = 'unidades_internas';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** @return BelongsTo<Unidad, $this> */
    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }
}
