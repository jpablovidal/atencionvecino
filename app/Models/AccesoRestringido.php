<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Autorización para ver un expediente restringido (completo o un archivo/documento en particular).
 *
 * @property int $id
 * @property int $expediente_id
 * @property int|null $archivo_id
 * @property int|null $documento_id
 * @property int $usuario_id
 * @property int $otorgado_por
 * @property string $motivo
 * @property Carbon|null $vence_en
 * @property Carbon|null $revocado_en
 */
class AccesoRestringido extends Model
{
    protected $table = 'acceso_restringido';

    protected $guarded = ['id'];

    protected $casts = ['vence_en' => 'datetime', 'revocado_en' => 'datetime'];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function otorgadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'otorgado_por');
    }

    public function vigente(): bool
    {
        return $this->revocado_en === null && ($this->vence_en === null || $this->vence_en->isFuture());
    }

    public function scopeVigentes($q)
    {
        return $q->whereNull('revocado_en')->where(fn ($w) => $w->whereNull('vence_en')->orWhere('vence_en', '>', now()));
    }
}
