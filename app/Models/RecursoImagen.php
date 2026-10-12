<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Imagen de la biblioteca del editor (re-codificada, cifrada en el repositorio). Inmutable y sin borrado:
 * los documentos ya emitidos pueden referenciarla.
 *
 * @property int $id
 * @property string $nombre
 * @property string $mime
 * @property int $ancho
 * @property int $alto
 * @property int $tamano
 * @property string $sha256
 * @property string $ruta
 * @property int|null $subido_por
 * @property string|null $created_at
 */
/**
 * @property int $id
 * @property string $nombre
 * @property string $mime
 * @property int $ancho
 * @property int $alto
 * @property int $tamano
 * @property string $sha256
 * @property string $ruta
 * @property int|null $subido_por
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class RecursoImagen extends Model
{
    public $timestamps = false;

    protected $table = 'recursos_imagen';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las imágenes son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las imágenes no se eliminan.'));
    }
}
