<?php

namespace App\Support;

/**
 * País de una IP con la base local resources/geoip (DB-IP «IP to Country Lite», CC BY 4.0). Sin consultas externas.
 * Devuelve el código ISO-2, 'LAN' para direcciones privadas/locales o null si se desconoce.
 */
class GeoIp
{
    /** @var array<string,resource|false> */
    private static array $fh = [];

    public static function pais(string $ip): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'LAN';
        }
        $bin = inet_pton($ip);
        if ($bin === false) {
            return null;
        }
        if (strlen($bin) === 16 && str_starts_with($bin, str_repeat("\0", 10)."\xff\xff")) { // IPv4 mapeada en IPv6
            $bin = substr($bin, 12);
        }
        $cc = strlen($bin) === 4 ? self::buscar('ipv4.bin', 4, $bin) : self::buscar('ipv6.bin', 8, substr($bin, 0, 8));

        return $cc === null || $cc === 'ZZ' ? null : $cc;
    }

    private static function buscar(string $archivo, int $clave, string $valor): ?string
    {
        $h = self::$fh[$archivo] ??= @fopen(resource_path('geoip/'.$archivo), 'rb');
        if (! $h) {
            return null;
        }
        $rec = $clave + 2;
        $n = intdiv((int) filesize(resource_path('geoip/'.$archivo)), $rec);
        $lo = 0;
        $hi = $n - 1;
        $mejor = null;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            fseek($h, $mid * $rec);
            $r = (string) fread($h, $rec);
            if (strlen($r) < $rec) {
                return null;
            }
            if (strcmp(substr($r, 0, $clave), $valor) <= 0) {
                $mejor = substr($r, $clave, 2);
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $mejor;
    }
}
