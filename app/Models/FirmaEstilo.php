<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property string $texto_encabezado
 * @property bool $mostrar_nombre
 * @property bool $mostrar_cargo
 * @property bool $mostrar_run
 * @property bool $mostrar_fecha
 * @property bool $mostrar_codigo
 * @property string $color_borde
 * @property string $color_fondo
 * @property string $color_texto
 * @property int $tamano_fuente
 * @property int $ancho_mm
 * @property int $alto_mm
 * @property string $borde_estilo
 * @property string $alineacion_texto
 * @property bool $defecto
 * @property bool $activo
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class FirmaEstilo extends Model
{
    protected $table = 'firma_estilos';

    protected $guarded = ['id'];

    protected $casts = ['mostrar_nombre' => 'boolean', 'mostrar_cargo' => 'boolean', 'mostrar_run' => 'boolean', 'mostrar_fecha' => 'boolean', 'mostrar_codigo' => 'boolean', 'defecto' => 'boolean', 'activo' => 'boolean'];
}
