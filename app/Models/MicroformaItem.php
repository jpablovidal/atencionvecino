<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Documento indexado dentro de una microforma (referencia, fojas y posición en el soporte). */
class MicroformaItem extends Model
{
    public $timestamps = false;

    protected $table = 'archivo_microforma_items';

    protected $guarded = ['id'];

    protected $casts = ['fecha_documento' => 'date'];
}
