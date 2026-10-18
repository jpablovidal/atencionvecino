<?php

namespace App\Support;

/** Límite real de una subida en este servidor (post_max_size): el navegador lo usa para avisar antes de enviar. */
class Subidas
{
    public static function maximoBytes(): int
    {
        return self::aBytes((string) ini_get('post_max_size'));
    }

    public static function aBytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '0') {
            return 0; // sin límite
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
