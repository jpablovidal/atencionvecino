<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $direccion
 * @property int|null $expediente_id
 * @property int|null $documento_id
 * @property string|null $institucion_codigo
 * @property string|null $institucion_nombre
 * @property string $asunto
 * @property string $estado
 * @property string|null $evidencia_tipo
 * @property string|null $evidencia
 * @property Carbon|null $evidencia_en
 * @property int $intentos
 * @property string|null $ultimo_error
 * @property int|null $archivo_id
 * @property int|null $responde_a_id
 * @property string|null $acuse_estado
 * @property string|null $acuse_tipo
 * @property string|null $acuse_evidencia
 * @property string|null $acuse_en
 * @property int|null $acuse_por_id
 * @property string|null $dd_estado
 * @property int|null $unidad_id
 * @property int|null $creado_por
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ComunicacionOficial extends Model
{
    protected $table = 'comunicaciones_oficiales';

    protected $guarded = ['id'];

    protected $casts = ['evidencia_en' => 'datetime'];

    /** Estados de tramitación (estado_tramitacion de DocDigital) que ya no cambian: no se vuelven a consultar. */
    public const DD_FINALES = ['OPE_RECEPCION_TOTAL', 'OPE_DEVOLUCION_TOTAL', 'OPE_RECEPCION_TOTAL_CON_DEVOLUCION', 'OPS_RECHAZADO', 'RECHAZADO_VISACION', 'RECHAZADO_FIRMA', 'CANCELADO_ADMINISTRADOR'];

    public const DD_ETIQUETAS = [
        'OPS_PENDIENTE_DESPACHO' => 'Cargada en DocDigital; pendiente de despacho por la Oficina de Partes',
        'OPS_RESUELTO_ENVIADO' => 'Despachada; pendiente de acuse del destinatario',
        'OPS_RECHAZADO' => 'Rechazada en el despacho',
        'OPE_RECIBIDO_PARCIALMENTE' => 'Recibida por parte de los destinatarios',
        'OPE_DEVUELTO_PARCIALMENTE' => 'Devuelta por parte de los destinatarios',
        'OPE_RECIBIDO_PARCIALMENTE_CON_DEVOLUCION' => 'Recibida y devuelta por parte de los destinatarios',
        'OPE_RECEPCION_TOTAL' => 'Recibida y acusada por el destinatario',
        'OPE_DEVOLUCION_TOTAL' => 'Devuelta por el destinatario',
        'OPE_RECEPCION_TOTAL_CON_DEVOLUCION' => 'Recibida y devuelta',
        'DOCUMENTO_FIRMADO' => 'Firmada',
        'PENDIENTE_FIRMA' => 'Pendiente de firma en DocDigital',
        'PENDIENTE_VISACION' => 'Pendiente de visación en DocDigital',
        'BORRADOR' => 'Borrador en DocDigital',
        'RECHAZADO_VISACION' => 'Rechazada en la visación',
        'RECHAZADO_FIRMA' => 'Rechazada en la firma',
        'CANCELADO_ADMINISTRADOR' => 'Cancelada por el administrador de DocDigital',
    ];

    public function ddEtiqueta(): ?string
    {
        return $this->dd_estado ? (self::DD_ETIQUETAS[$this->dd_estado] ?? ucfirst(strtolower(str_replace('_', ' ', $this->dd_estado)))) : null;
    }

    /** @return HasMany<ComunicacionAnexo, $this> */
    public function anexos(): HasMany
    {
        return $this->hasMany(ComunicacionAnexo::class, 'comunicacion_id')->orderBy('id');
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}
