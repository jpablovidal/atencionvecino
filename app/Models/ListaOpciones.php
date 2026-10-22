<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Listado de opciones reutilizable en los campos de lista de los trámites.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property int|null $unidad_id
 * @property bool $activa
 */
class ListaOpciones extends Model
{
    protected $table = 'listas_opciones';

    protected $guarded = ['id'];

    protected $casts = ['activa' => 'boolean'];

    /** @return HasMany<ListaOpcionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ListaOpcionItem::class, 'lista_id')->where('activo', true)->orderBy('orden')->orderBy('id');
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }
}
