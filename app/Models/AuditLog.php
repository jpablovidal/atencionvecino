<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    /** Fecha y hora del registro en UTC+00:00 (Decretos 9 art. 13, 10 art. 9 y 12 art. 9): se guarda con la hora de Chile y se convierte aquí. */
    public function tsUtc(): string
    {
        try {
            return Carbon::parse(substr((string) $this->getRawOriginal('ts'), 0, 26), config('app.timezone'))->utc()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return '';
        }
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('audit_log es append-only.'));
        static::deleting(fn () => throw new \LogicException('audit_log es append-only.'));
    }
}
