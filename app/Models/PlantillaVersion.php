<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Instantánea inmutable de una plantilla.
 *
 * @property int $id
 * @property int $plantilla_id
 * @property int $version
 * @property string $nombre
 * @property string $contenido_html
 * @property string $sha256
 * @property int|null $creado_por
 * @property string|null $created_at
 */
/**
 * @property int $id
 * @property int $plantilla_id
 * @property int $version
 * @property string $nombre
 * @property string $contenido_html
 * @property string $sha256
 * @property int|null $creado_por
 * @property Carbon|null $created_at
 */
class PlantillaVersion extends Model
{
    public $timestamps = false;

    protected $table = 'plantilla_versiones';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime', 'estructura' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las versiones de plantilla son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las versiones de plantilla son inmutables.'));
    }
}
