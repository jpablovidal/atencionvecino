<?php

namespace App\Support;

/** Monto en pesos chilenos escrito en letras (p. ej. 1.250.000 → «UN MILLÓN DOSCIENTOS CINCUENTA MIL PESOS»). Hasta 999.999.999.999. */
class MontoEnLetras
{
    private const UNIDADES = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
        'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];

    private const DECENAS = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];

    private const CENTENAS = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

    public static function pesos(int $n): string
    {
        if ($n < 0 || $n > 999_999_999_999) {
            throw new \InvalidArgumentException('Monto fuera de rango.');
        }
        if ($n === 0) {
            return 'CERO PESOS';
        }

        return self::letras($n).($n === 1 ? ' PESO' : (($n % 1_000_000 === 0 && $n >= 1_000_000) ? ' DE PESOS' : ' PESOS'));
    }

    private static function letras(int $n): string
    {
        if ($n >= 1_000_000_000) { // miles de millones
            $mm = intdiv($n, 1_000_000_000);
            $r = $n % 1_000_000_000;

            return trim(($mm === 1 ? 'MIL' : self::menosDeMil($mm, true).' MIL').($r >= 1_000_000 ? ' '.self::letras($r) : ($r > 0 ? ' MILLONES '.self::letras($r) : ' MILLONES')));
        }
        if ($n >= 1_000_000) {
            $m = intdiv($n, 1_000_000);
            $r = $n % 1_000_000;

            return trim(($m === 1 ? 'UN MILLÓN' : self::letras($m).' MILLONES').($r ? ' '.self::letras($r) : ''));
        }
        if ($n >= 1000) {
            $k = intdiv($n, 1000);
            $r = $n % 1000;

            return trim(($k === 1 ? 'MIL' : self::menosDeMil($k, true).' MIL').($r ? ' '.self::menosDeMil($r, true) : ''));
        }

        return self::menosDeMil($n, true);
    }

    /** @param bool $apocopado «un» en lugar de «uno» antes de «mil» */
    private static function menosDeMil(int $n, bool $apocopado): string
    {
        if ($n === 100) {
            return 'CIEN';
        }
        $c = intdiv($n, 100);
        $r = $n % 100;
        $txt = $c ? self::CENTENAS[$c] : '';
        if ($r === 0) {
            return $txt;
        }
        if ($r <= 29) {
            $u = self::UNIDADES[$r];
            if (! $apocopado && $r === 1) {
                $u = 'UNO';
            } elseif (! $apocopado && $r === 21) {
                $u = 'VEINTIUNO';
            }

            return trim($txt.' '.$u);
        }
        $d = intdiv($r, 10);
        $u = $r % 10;
        $dec = self::DECENAS[$d].($u ? ' Y '.($u === 1 && ! $apocopado ? 'UNO' : self::UNIDADES[$u]) : '');

        return trim($txt.' '.$dec);
    }
}
