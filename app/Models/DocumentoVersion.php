<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Histórico inmutable: no se actualiza ni se borra. */
/**
 * @property int $id
 * @property int $documento_id
 * @property int $version
 * @property string $html
 * @property string $html_sha256
 * @property string|null $pdf_ruta
 * @property string|null $pdf_sha256
 * @property int $creado_por
 * @property string $created_at
 */
class DocumentoVersion extends Model
{
    public $timestamps = false;

    protected $table = 'documento_versiones';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Las versiones son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Las versiones son inmutables.'));
    }
}
