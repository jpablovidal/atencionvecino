<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $nombre
 * @property int|null $tipo_documento_id
 * @property int $orden_firma
 * @property string $pagina
 * @property string $alineacion
 * @property int $margen_superior_mm
 * @property int|null $x_mm
 * @property int|null $y_mm
 * @property int|null $estilo_id
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class FirmaPosicion extends Model
{
    protected $table = 'firma_posiciones';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    /** @return BelongsTo<FirmaEstilo, $this> */
    public function estilo(): BelongsTo
    {
        return $this->belongsTo(FirmaEstilo::class, 'estilo_id');
    }
}
