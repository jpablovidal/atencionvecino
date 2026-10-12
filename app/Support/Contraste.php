<?php

namespace App\Support;

/** Contraste de color WCAG 2.x (luminancia relativa). */
class Contraste
{
    public static function esHex(string $c): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c);
    }

    public static function ratio(string $a, string $b): float
    {
        [$x, $y] = [self::luminancia($a), self::luminancia($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    public static function luminancia(string $hex): float
    {
        $c = array_map(function ($v) {
            $v = hexdec($v) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }
}
