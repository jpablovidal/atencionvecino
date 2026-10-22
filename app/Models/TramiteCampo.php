<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Campo propio del formulario de un trámite.
 *
 * @property int $id
 * @property int $tramite_id
 * @property string $clave
 * @property string $etiqueta
 * @property string $tipo
 * @property string|null $ayuda
 * @property bool $obligatorio
 * @property int $orden
 * @property bool $activo
 * @property array<string,mixed> $config
 */
class TramiteCampo extends Model
{
    protected $table = 'tramite_campos';

    protected $guarded = ['id'];

    protected $casts = ['obligatorio' => 'boolean', 'activo' => 'boolean', 'config' => 'array'];

    public const TIPOS = ['texto' => 'Texto corto', 'parrafo' => 'Texto largo', 'numero' => 'Número', 'fecha' => 'Fecha', 'lista' => 'Lista de opciones (elige una)',
        'multiple' => 'Lista de opciones (elige varias)', 'si_no' => 'Sí / No', 'calculo' => 'Cálculo (fórmula)', 'titulo' => 'Título de sección'];

    /** @return BelongsTo<Tramite, $this> */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function conf(string $k, mixed $defecto = null): mixed
    {
        return ($this->config ?? [])[$k] ?? $defecto;
    }
}
