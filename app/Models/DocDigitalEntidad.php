<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Entidad de DocDigital (destino de despachos).
 *
 * @property int $entidad_id
 * @property string $nombre
 * @property int|null $padre_id
 * @property bool $vigente
 */
class DocDigitalEntidad extends Model
{
    protected $table = 'docdigital_entidades';

    protected $guarded = ['id'];

    protected $casts = ['vigente' => 'boolean', 'sincronizado_en' => 'datetime'];
}
