<?php

namespace App\Support;

/**
 * Espacio de trabajo de una persona que es funcionaria Y vecina. Quien ingresa como funcionario(a) con ClaveÚnica ya acreditó
 * su identidad, por lo que la sesión conserva también su identidad ciudadana (mismo RUN). El espacio activo lo fija la ruta
 * que visita (/app = funcionario, /ciudadano = vecino); el menú y la barra superior se adaptan. El espacio NO concede acceso:
 * cada ruta sigue exigiendo su propio middleware.
 */
class Espacio
{
    public const APP = 'app';

    public const CIUDADANO = 'ciudadano';

    public static function habilitado(): bool
    {
        return (bool) config('sgd.espacio_dual', true);
    }

    /** Sesión de funcionario con identidad ciudadana acreditada. */
    public static function dual(): bool
    {
        return self::habilitado() && auth()->check() && session()->has('ciudadano');
    }

    /** @return 'app'|'ciudadano'|null null = aún sin elegir (inicio dual) o sesión pública */
    public static function activo(): ?string
    {
        if (! session()->has('ciudadano')) {
            return auth()->check() ? self::APP : null;
        }
        if (! auth()->check()) {
            return self::CIUDADANO;
        }
        $e = session('espacio');

        return in_array($e, [self::APP, self::CIUDADANO], true) ? $e : null;
    }

    public static function fijar(string $espacio): void
    {
        if (self::dual()) {
            session()->put('espacio', $espacio);
        }
    }
}
