<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Decreto de pago: expediente de pago + proceso de compra + PDF del decreto (generado o cargado). Su egreso lo emite Tesorería.
 *
 * @property int $id
 * @property int $expediente_id
 * @property int|null $compra_expediente_id
 * @property string $proveedor_nombre
 * @property string|null $proveedor_run
 * @property string $concepto
 * @property int $monto
 * @property string|null $imputacion
 * @property string|null $orden_compra
 * @property string|null $factura
 * @property Carbon|null $fecha
 * @property string $estado abierto|anulado
 * @property string|null $origen generado|cargado
 * @property int|null $documento_id
 * @property int|null $archivo_id
 * @property string|null $numero_externo
 * @property string|null $anulado_motivo
 * @property int $creado_por
 */
class DecretoPago extends Model
{
    public const FASES = ['borrador' => 'Borrador', 'en_firma' => 'En visación y firma', 'emitido' => 'Emitido', 'pagado' => 'Pagado', 'anulado' => 'Anulado'];

    protected $table = 'decretos_pago';

    protected $guarded = ['id'];

    protected $casts = ['fecha' => 'date', 'monto' => 'integer'];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Expediente, $this> */
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'compra_expediente_id');
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** @return BelongsTo<Archivo, $this> */
    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /** @return HasMany<Egreso, $this> */
    public function egresos(): HasMany
    {
        return $this->hasMany(Egreso::class)->orderBy('id');
    }

    /** Monto ya egresado (egresos vigentes). */
    public function egresado(): int
    {
        return (int) $this->egresos->filter(fn ($e) => $e->fase() === 'emitido')->sum('monto');
    }

    /** Monto comprometido por egresos no anulados (en curso o emitidos): no se puede egresar más que el decreto. */
    public function comprometido(): int
    {
        return (int) $this->egresos->where('estado', 'abierto')->sum('monto');
    }

    public function emitido(): bool
    {
        return $this->estado === 'abierto' && ($this->origen === 'cargado' ? $this->archivo_id !== null : $this->documento?->estado === 'vigente');
    }

    /** borrador | en_firma | emitido | pagado | anulado */
    public function fase(): string
    {
        if ($this->estado === 'anulado') {
            return 'anulado';
        }
        if (! $this->emitido()) {
            return $this->documento?->estado === 'en_flujo' ? 'en_firma' : 'borrador';
        }

        return $this->egresado() >= $this->monto ? 'pagado' : 'emitido';
    }

    /** Folio del decreto: el del documento firmado o el número informado al cargarlo. */
    public function folio(): ?string
    {
        return $this->origen === 'cargado' ? ($this->numero_externo ? 'Decreto de pago N° '.$this->numero_externo : null) : $this->documento?->numeroFormateado();
    }
}
