<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $solicitud_id
 * @property int|null $expediente_id
 * @property int|null $documento_id
 * @property string $ambito
 * @property string $nombre_original
 * @property string $mime
 * @property int $tamano
 * @property string $sha256
 * @property string $ruta
 * @property string|null $titulo
 * @property string|null $resumen
 * @property string|null $palabras_clave
 * @property string $nivel_acceso
 * @property bool $sensible
 * @property string $subido_por
 * @property string $created_at
 */
class Archivo extends Model
{
    protected $table = 'archivos';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['sensible' => 'boolean'];
}
