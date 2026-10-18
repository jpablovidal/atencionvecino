<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $expediente_id
 * @property int $tipo_documento_id
 * @property string $titulo
 * @property string|null $resumen
 * @property string|null $palabras_clave
 * @property string $nivel_acceso
 * @property bool $sensible
 * @property string $estado
 * @property string $flujo
 * @property int $version_actual
 * @property string $html
 * @property int|null $numero
 * @property int|null $anio
 * @property Carbon|null $fecha_firma
 * @property string|null $codigo_validacion
 * @property string|null $pdf_ruta
 * @property string|null $pdf_sha256
 * @property array<mixed>|null $metadatos
 * @property int $creado_por
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $pdf_firmado_ruta
 * @property string|null $pdf_firmado_sha256
 * @property string|null $folio
 */
class Documento extends Model
{
    protected $table = 'documentos';

    protected $guarded = ['id'];

    protected $casts = ['sensible' => 'boolean', 'metadatos' => 'array', 'fecha_firma' => 'datetime', 'restringido' => 'boolean', 'restringido_en' => 'datetime'];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /** @return HasMany<DocumentoPaso, $this> */
    public function pasos(): HasMany
    {
        return $this->hasMany(DocumentoPaso::class)->orderBy('orden');
    }

    /** @return HasMany<DocumentoVersion, $this> */
    public function versiones(): HasMany
    {
        return $this->hasMany(DocumentoVersion::class)->orderBy('version');
    }

    /** @return HasMany<Archivo, $this> */
    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    public function editable(): bool
    {
        return $this->estado === 'borrador';
    }

    public function numeroFormateado(): ?string
    {
        return $this->folio ?? ($this->numero ? sprintf('%s N° %02d/%d', $this->tipo->prefijo, $this->numero, $this->anio) : null);
    }

    public function esPublico(): bool
    {
        return $this->estado === 'vigente' && $this->nivel_acceso === 'publico' && ! $this->sensible && ! $this->restringido && ! $this->expediente?->restringido;
    }
}
