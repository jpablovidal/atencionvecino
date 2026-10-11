<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bitácora append-only: ni el modelo ni (si hay privilegios) la BD permiten UPDATE/DELETE. */
/**
 * @property int $id
 * @property string $ts
 * @property string|null $actor_run
 * @property string|null $actor_rol
 * @property string $accion
 * @property string|null $entidad
 * @property string|null $entidad_id
 * @property string|null $ip
 * @property int $sensible
 * @property string|null $detalle
 * @property string $prev_hmac
 * @property string $hmac
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_log';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('audit_log es append-only.'));
        static::deleting(fn () => throw new \LogicException('audit_log es append-only.'));
    }
}
