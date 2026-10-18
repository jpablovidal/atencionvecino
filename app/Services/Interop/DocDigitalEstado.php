<?php

namespace App\Services\Interop;

use App\Models\ConfigSitio;

/** Último resultado de la sincronización con DocDigital (para el panel). Solo datos operativos: nunca credenciales ni contenido. */
class DocDigitalEstado
{
    private const CLAVE = 'docdigital_estado';

    /** @return array{ultima_sync:?string,nuevas:int,omitidas:int,error:?string,ultimo_ok:?string} */
    public static function leer(): array
    {
        try {
            $j = json_decode((string) ConfigSitio::query()->where('clave', self::CLAVE)->value('valor'), true);
        } catch (\Throwable) {
            $j = null;
        }
        $j = is_array($j) ? $j : [];

        return ['ultima_sync' => $j['ultima_sync'] ?? null, 'nuevas' => (int) ($j['nuevas'] ?? 0), 'omitidas' => (int) ($j['omitidas'] ?? 0), 'error' => $j['error'] ?? null, 'ultimo_ok' => $j['ultimo_ok'] ?? null];
    }

    /** @param array{nuevas?:int,omitidas?:int}|null $res */
    public static function registrar(?array $res, ?string $error = null): void
    {
        $prev = self::leer();
        $ahora = now()->toDateTimeString();
        $nuevo = ['ultima_sync' => $ahora, 'nuevas' => (int) ($res['nuevas'] ?? 0), 'omitidas' => (int) ($res['omitidas'] ?? 0), 'error' => $error ? mb_substr($error, 0, 300) : null,
            'ultimo_ok' => $error ? $prev['ultimo_ok'] : $ahora];
        try {
            ConfigSitio::query()->updateOrCreate(['clave' => self::CLAVE], ['valor' => (string) json_encode($nuevo)]);
        } catch (\Throwable) {
            // el panel es informativo: no debe romper la sincronización
        }
    }
}
