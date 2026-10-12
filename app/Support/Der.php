<?php

namespace App\Support;

/** Lector mínimo de ASN.1 DER (lo necesario para leer estructuras CMS/PKCS#7 de firmas PDF). Nunca lanza fuera de \RuntimeException. */
class Der
{
    /** @return array{tag:int,hdr:int,len:int,start:int,end:int}  start = inicio del contenido */
    public static function nodo(string $d, int $off): array
    {
        $n = strlen($d);
        if ($off + 2 > $n) {
            throw new \RuntimeException('DER truncado');
        }
        $tag = ord($d[$off]);
        $l = ord($d[$off + 1]);
        $hdr = 2;
        if ($l & 0x80) {
            $k = $l & 0x7F;
            if ($k < 1 || $k > 4 || $off + 2 + $k > $n) {
                throw new \RuntimeException('Longitud DER inválida');
            }
            $l = 0;
            for ($i = 0; $i < $k; $i++) {
                $l = ($l << 8) | ord($d[$off + 2 + $i]);
            }
            $hdr = 2 + $k;
        }
        if ($off + $hdr + $l > $n) {
            throw new \RuntimeException('DER fuera de rango');
        }

        return ['tag' => $tag, 'hdr' => $hdr, 'len' => $l, 'start' => $off + $hdr, 'end' => $off + $hdr + $l];
    }

    /** @return array<int,array{tag:int,hdr:int,len:int,start:int,end:int}> hijos de un nodo constructivo */
    public static function hijos(string $d, array $nodo): array
    {
        $out = [];
        $o = $nodo['start'];
        while ($o < $nodo['end']) {
            $h = self::nodo($d, $o);
            $out[] = $h;
            $o = $h['end'];
            if (count($out) > 500) {
                throw new \RuntimeException('Demasiados elementos');
            }
        }

        return $out;
    }

    public static function bytes(string $d, array $n): string
    {
        return substr($d, $n['start'], $n['len']);
    }

    /** Bytes completos del nodo (cabecera + contenido). */
    public static function crudo(string $d, array $n): string
    {
        return substr($d, $n['start'] - $n['hdr'], $n['hdr'] + $n['len']);
    }

    public static function oid(string $d, array $n): string
    {
        $b = self::bytes($d, $n);
        if ($b === '') {
            return '';
        }
        $partes = [intdiv(ord($b[0]), 40), ord($b[0]) % 40];
        $v = 0;
        for ($i = 1, $m = strlen($b); $i < $m; $i++) {
            $v = ($v << 7) | (ord($b[$i]) & 0x7F);
            if (! (ord($b[$i]) & 0x80)) {
                $partes[] = $v;
                $v = 0;
            }
        }

        return implode('.', $partes);
    }

    /** UTCTime/GeneralizedTime → timestamp UTC (o null). */
    public static function fecha(string $d, array $n): ?int
    {
        $s = self::bytes($d, $n);
        if ($n['tag'] === 0x17 && preg_match('/^(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})?Z$/', $s, $m)) {
            $y = (int) $m[1] + ((int) $m[1] >= 50 ? 1900 : 2000);

            return gmmktime((int) $m[4], (int) $m[5], (int) ($m[6] ?? 0), (int) $m[2], (int) $m[3], $y) ?: null;
        }
        if ($n['tag'] === 0x18 && preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})?(?:\.\d+)?Z$/', $s, $m)) {
            return gmmktime((int) $m[4], (int) $m[5], (int) ($m[6] ?? 0), (int) $m[2], (int) $m[3], (int) $m[1]) ?: null;
        }

        return null;
    }
}
