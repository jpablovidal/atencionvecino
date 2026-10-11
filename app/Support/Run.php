<?php

namespace App\Support;

class Run
{
    /** Normaliza a "12345678-K" o null si es inválido (módulo 11). */
    public static function normalizar(?string $run): ?string
    {
        $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', (string) $run) ?? '');
        if (strlen($limpio) < 2 || strlen($limpio) > 9) {
            return null;
        }
        $cuerpo = substr($limpio, 0, -1);
        $dv = substr($limpio, -1);
        if (! ctype_digit($cuerpo) || self::dv($cuerpo) !== $dv) {
            return null;
        }

        return ltrim($cuerpo, '0').'-'.$dv;
    }

    public static function dv(string $cuerpo): string
    {
        $suma = 0;
        $m = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int) $cuerpo[$i] * $m;
            $m = $m === 7 ? 2 : $m + 1;
        }
        $r = 11 - ($suma % 11);

        return $r === 11 ? '0' : ($r === 10 ? 'K' : (string) $r);
    }

    public static function sinDv(string $run): string
    {
        return explode('-', $run)[0];
    }

    public static function enmascarar(string $run): string
    {
        return '***'.substr($run, -5);
    }
}
