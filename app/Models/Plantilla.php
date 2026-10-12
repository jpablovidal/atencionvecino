<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $tipo_documento_id
 * @property string $nombre
 * @property string $contenido_html
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $descripcion
 * @property string $alcance
 * @property int $version
 * @property int|null $actualizado_por
 */
class Plantilla extends Model
{
    protected $table = 'plantillas';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    /** @return BelongsToMany<Unidad, $this> */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidad::class, 'plantilla_unidades', 'plantilla_id', 'unidad_id');
    }

    /** Plantillas visibles para un usuario: las de toda la municipalidad y las de su unidad (el auditor y el administrador ven todas). */
    public function scopeVisiblesPara($q, Usuario $u)
    {
        if ($u->esRol('admin', 'auditor')) {
            return $q;
        }

        return $q->where(function ($w) use ($u) {
            $w->where('alcance', 'todas');
            if ($u->unidad_id) {
                $w->orWhereIn('id', fn ($s) => $s->from('plantilla_unidades')->select('plantilla_id')->where('unidad_id', $u->unidad_id));
            }
        });
    }
}
