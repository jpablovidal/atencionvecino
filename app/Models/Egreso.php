<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Egreso de Tesorería de un decreto de pago: fecha, medio de pago, monto y detalle; su documento se firma con firma simple o avanzada.
 *
 * @property int $id
 * @property int $decreto_pago_id
 * @property Carbon $fecha
 * @property string $medio_pago
 * @property int $monto
 * @property string $detalle
 * @property string|null $referencia
 * @property string|null $cuenta
 * @property string $estado abierto|anulado
 * @property int|null $documento_id
 * @property string|null $anulado_motivo
 * @property int $creado_por
 */
class Egreso extends Model
{
    public const MEDIOS = ['transferencia' => 'Transferencia electrónica', 'cheque' => 'Cheque', 'vale_vista' => 'Vale vista', 'efectivo' => 'Efectivo', 'otro' => 'Otro medio'];

    protected $table = 'egresos';

    protected $guarded = ['id'];

    protected $casts = ['fecha' => 'date', 'monto' => 'integer'];

    /** @return BelongsTo<DecretoPago, $this> */
    public function decreto(): BelongsTo
    {
        return $this->belongsTo(DecretoPago::class, 'decreto_pago_id');
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /** en_firma | emitido | anulado */
    public function fase(): string
    {
        if ($this->estado === 'anulado') {
            return 'anulado';
        }

        return $this->documento?->estado === 'vigente' ? 'emitido' : 'en_firma';
    }
}
