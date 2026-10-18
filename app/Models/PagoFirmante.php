<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Firmante o visador configurado para el decreto de pago o el egreso.
 *
 * @property int $id
 * @property string $documento decreto_pago|egreso
 * @property int $usuario_id
 * @property string $cargo
 * @property string $funcion visacion|firma
 * @property string $firma_tipo avanzada|simple|ambas
 * @property int $orden
 * @property bool $activo
 */
class PagoFirmante extends Model
{
    public const DOCUMENTOS = ['decreto_pago' => 'Decreto de pago', 'egreso' => 'Egreso'];

    public const FUNCIONES = ['visacion' => 'Visa', 'firma' => 'Firma'];

    public const FIRMAS = ['avanzada' => 'Firma electrónica avanzada (FirmaGob)', 'simple' => 'Firma electrónica simple', 'ambas' => 'Simple o avanzada, a elección de quien firma'];

    protected $table = 'pago_firmantes';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
