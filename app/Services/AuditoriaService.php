<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Bitácora append-only con cadena HMAC-SHA256. Nunca registrar secretos ni datos sensibles en $detalle. */
class AuditoriaService
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private const SECRETOS = ['otp', 'password', 'secret', 'token', 'client_secret', 'api_token_key', 'private_key'];

    public function registrar(string $accion, ?string $entidad = null, string|int|null $entidadId = null, array $detalle = [], bool $sensible = false, ?array $actor = null): AuditLog
    {
        $actor ??= $this->actorActual();
        $detalle = $this->limpiar($detalle);

        return DB::transaction(function () use ($accion, $entidad, $entidadId, $detalle, $sensible, $actor) {
            $ultimo = AuditLog::query()->orderByDesc('id')->lockForUpdate()->first();
            $prev = $ultimo instanceof AuditLog ? $ultimo->hmac : self::GENESIS;
            $fila = [
                'ts' => now()->format('Y-m-d H:i:s.u'),
                'actor_run' => $actor['run'] ?? null,
                'actor_rol' => $actor['rol'] ?? null,
                'accion' => $accion,
                'entidad' => $entidad,
                'entidad_id' => $entidadId === null ? null : (string) $entidadId,
                'ip' => $this->ip(),
                'sensible' => $sensible,
                'detalle' => $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'prev_hmac' => $prev,
            ];
            $fila['hmac'] = $this->hmac($fila);

            return AuditLog::query()->create($fila);
        });
    }

    /** @return array{ok:bool,total:int,roto_en:?int} */
    public function verificar(): array
    {
        $prev = self::GENESIS;
        $total = 0;
        foreach (AuditLog::query()->orderBy('id')->cursor() as $r) {
            $total++;
            $fila = $r->only(['ts', 'actor_run', 'actor_rol', 'accion', 'entidad', 'entidad_id', 'ip', 'sensible', 'detalle']) + ['prev_hmac' => $r->prev_hmac];
            $fila['sensible'] = (bool) $fila['sensible'];
            $fila['ts'] = (string) $r->getRawOriginal('ts');
            if (! hash_equals($prev, $r->prev_hmac) || ! hash_equals($this->hmac($fila), $r->hmac)) {
                return ['ok' => false, 'total' => $total, 'roto_en' => $r->id];
            }
            $prev = $r->hmac;
        }

        return ['ok' => true, 'total' => $total, 'roto_en' => null];
    }

    private function hmac(array $fila): string
    {
        $key = (string) config('sgd.audit_hmac_key');
        if (strlen($key) < 32) {
            throw new RuntimeException('AUDIT_HMAC_KEY no configurada.');
        }
        $canon = json_encode([
            (string) $fila['ts'], $fila['actor_run'], $fila['actor_rol'], $fila['accion'], $fila['entidad'], $fila['entidad_id'],
            $fila['ip'], (bool) $fila['sensible'], $fila['detalle'], $fila['prev_hmac'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', (string) $canon, $key);
    }

    private function limpiar(array $d): array
    {
        foreach ($d as $k => $v) {
            if (in_array(strtolower((string) $k), self::SECRETOS, true)) {
                $d[$k] = '[REDACTADO]';
            } elseif (is_array($v)) {
                $d[$k] = $this->limpiar($v);
            } elseif (is_string($v) && mb_strlen($v) > 300) {
                $d[$k] = mb_substr($v, 0, 300).'…';
            }
        }

        return $d;
    }

    private function actorActual(): array
    {
        $req = request();
        $u = $req->user();
        if ($u) {
            return ['run' => $u->run, 'rol' => $u->rol];
        }
        $c = $req->hasSession() ? $req->session()->get('ciudadano') : null;

        return $c ? ['run' => $c['run'], 'rol' => 'ciudadano'] : ['run' => null, 'rol' => null];
    }

    private function ip(): ?string
    {
        return app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip();
    }
}
