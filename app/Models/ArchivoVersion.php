<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial inmutable de versiones de un archivo de expediente.
 *
 * @property int $id
 * @property int $archivo_id
 * @property int $numero
 * @property string $nombre_original
 * @property string $mime
 * @property int $tamano
 * @property string $sha256
 * @property string $ruta
 * @property string|null $motivo
 * @property string|null $resumen_cambios
 * @property int|null $creado_por
 * @property string|null $autor_nombre
 * @property string|null $autor_run
 * @property string|null $firma_estado
 * @property string|null $firma_detalle
 * @property string $created_at
 */
class ArchivoVersion extends Model
{
    public $timestamps = false;

    protected $table = 'archivo_versiones';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las versiones son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las versiones son inmutables.'));
    }

    /** @return array<string,mixed>|null */
    public function resumen(): ?array
    {
        return $this->resumen_cambios ? json_decode($this->resumen_cambios, true) : null;
    }
}
