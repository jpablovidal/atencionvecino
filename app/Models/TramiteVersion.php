<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Instantánea inmutable de un trámite (contenido completo) en cada cambio de contenido o de estado.
 *
 * @property int $id
 * @property int $tramite_id
 * @property int $version
 * @property string $estado
 * @property string $evento
 * @property string $datos
 * @property int|null $creado_por
 * @property Carbon|null $created_at
 */
class TramiteVersion extends Model
{
    public $timestamps = false;

    protected $table = 'tramite_versiones';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las versiones de trámite son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las versiones de trámite son inmutables.'));
    }

    /** @return array<string,mixed> */
    public function contenido(): array
    {
        return (array) json_decode($this->datos, true);
    }
}
