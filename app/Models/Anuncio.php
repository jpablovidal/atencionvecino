<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Anuncio que se muestra en un modal al cargar el sitio.
 *
 * @property int $id
 * @property string $titulo
 * @property string|null $descripcion
 * @property string $audiencia
 * @property string|null $medio_tipo
 * @property string|null $medio_mime
 * @property string|null $medio_ruta
 * @property string|null $medio_sha256
 * @property int|null $medio_tamano
 * @property string|null $medio_alt
 * @property string|null $enlace_url
 * @property string|null $enlace_texto
 * @property string|null $inicia_en
 * @property string|null $termina_en
 * @property bool $activo
 * @property int $orden
 * @property int|null $creado_por
 */
class Anuncio extends Model
{
    protected $table = 'anuncios';

    protected $guarded = ['id'];

    protected $casts = ['activo' => 'boolean'];

    /** Vigentes ahora para una audiencia (vecinos o funcionarios); «todos» llega a ambas. */
    public function scopeVigentes($q, string $audiencia)
    {
        $ahora = now()->format('Y-m-d H:i:s');

        return $q->where('activo', true)->whereIn('audiencia', [$audiencia, 'todos'])
            ->where(fn ($w) => $w->whereNull('inicia_en')->orWhere('inicia_en', '<=', $ahora))
            ->where(fn ($w) => $w->whereNull('termina_en')->orWhere('termina_en', '>=', $ahora));
    }

    public function vigente(): bool
    {
        return $this->activo && (! $this->inicia_en || $this->inicia_en <= now()->format('Y-m-d H:i:s')) && (! $this->termina_en || $this->termina_en >= now()->format('Y-m-d H:i:s'));
    }
}
