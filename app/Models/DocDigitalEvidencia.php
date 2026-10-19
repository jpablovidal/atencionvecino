<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $creado_en
 * @property string $ambiente
 * @property string $metodo
 * @property string $ruta
 * @property string|null $consulta
 * @property string|null $solicitud
 * @property int|null $http_status
 * @property string|null $respuesta
 * @property int|null $duracion_ms
 * @property int|null $usuario_id
 */
class DocDigitalEvidencia extends Model
{
    protected $table = 'docdigital_evidencias';

    public $timestamps = false;

    protected $guarded = ['id'];
}
