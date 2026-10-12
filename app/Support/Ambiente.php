<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Ambiente de la instalación. «produccion» (por defecto): solo ClaveÚnica y servicios reales.
 * «pruebas»: permite modos simulados y el acceso con usuario/contraseña, SOLO hasta SGD_ACCESO_PRUEBAS_HASTA (vence solo).
 */
class Ambiente
{
    public static function esPruebas(): bool
    {
        return config('sgd.ambiente') === 'pruebas';
    }

    public static function pruebasHasta(): ?Carbon
    {
        $v = (string) config('sgd.acceso_pruebas_hasta');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $v));
        if (! checkdate($m, $d, $y)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $v, 'America/Santiago')->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** ¿Está habilitado hoy el acceso con usuario y contraseña? */
    public static function accesoPruebasActivo(): bool
    {
        $h = self::pruebasHasta();

        return self::esPruebas() && $h !== null && now()->lessThanOrEqualTo($h);
    }
}
