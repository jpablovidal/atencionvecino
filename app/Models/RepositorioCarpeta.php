<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $clave
 * @property string $drive_folder_id
 * @property string $created_at
 */
class RepositorioCarpeta extends Model
{
    protected $table = 'repositorio_carpetas';

    protected $guarded = ['id'];

    public $timestamps = false;
}
