<?php

namespace App\Support;

/** Trazos del mapa del mundo (Natural Earth, dominio público) por código de país: ['n' => nombre, 'd' => trazo SVG, 'c' => [x, y] centro]. */
class MapaMundo
{
    /** @var array<string,array{n:string,d:string,c:array<int,float>}>|null */
    private static ?array $datos = null;

    /** @return array<string,array{n:string,d:string,c:array<int,float>}> */
    public static function datos(): array
    {
        if (self::$datos === null) {
            $f = resource_path('geoip/mapa.json');
            self::$datos = is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
        }

        return self::$datos;
    }

    public static function nombre(?string $cc): string
    {
        return match ($cc) {
            null => 'Desconocido', 'LAN' => 'Red interna / local', default => self::datos()[$cc]['n'] ?? $cc,
        };
    }
}
