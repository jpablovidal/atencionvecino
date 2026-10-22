<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Opción de un listado.
 *
 * @property int $lista_id
 * @property string $valor
 * @property string $etiqueta
 * @property int $orden
 * @property bool $activo
 */
class ListaOpcionItem extends Model
{
    protected $table = 'lista_opciones_items';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];
}
