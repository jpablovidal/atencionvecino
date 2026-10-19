<?php

namespace App\Support;

use App\Models\ConfigSitio;
use Illuminate\Support\Carbon;

/**
 * Ambiente de la instalación. «produccion» (por defecto): solo ClaveÚnica y servicios reales.
 * «pruebas»: permite modos simulados y el acceso con usuario/contraseña, SOLO hasta SGD_ACCESO_PRUEBAS_HASTA (vence solo).
 */
class Ambiente
{
    /** El acceso de pruebas no puede quedar habilitado por más de estos días desde hoy. */
    public const MAX_DIAS = 45;

    /** Si el panel ya pasó el sistema a producción, ese estado (guardado en la base) manda sobre SGD_AMBIENTE del .env. */
    public static function aplicarOverride(): void
    {
        try {
            $j = json_decode((string) ConfigSitio::query()->where('clave', 'ambiente_produccion')->value('valor'), true);
        } catch (\Throwable) {
            return; // sin base de datos (instalación, comandos previos a migrar): se respeta el .env
        }
        if (is_array($j) && ($j['ambiente'] ?? null) === 'produccion') {
            config(['sgd.ambiente' => 'produccion']);
        }
    }

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

        return self::esPruebas() && $h !== null && now()->lessThanOrEqualTo($h) && $h->lessThanOrEqualTo(now()->addDays(self::MAX_DIAS));
    }
}
