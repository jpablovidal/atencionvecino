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
 * @property string $almacen
 * @property string|null $firma_estado
 * @property string|null $firma_detalle
 * @property string|null $firma_analizada_en
 */
class Archivo extends Model
{
    protected $table = 'archivos';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(fn (self $a) => $a->almacen = str_starts_with((string) $a->ruta, 'gd:') ? 'drive' : 'local');
    }

    protected $casts = ['sensible' => 'boolean', 'restringido' => 'boolean', 'restringido_en' => 'datetime'];

    /** @return array<string,mixed>|null resultado del análisis de firma electrónica */
    public function firma(): ?array
    {
        return $this->firma_detalle ? json_decode($this->firma_detalle, true) : null;
    }
}
