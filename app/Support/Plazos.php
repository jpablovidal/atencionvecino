<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Cálculo de vencimientos de los trámites. Por defecto días corridos; con SGD_PLAZOS_HABILES=true cuenta lunes a viernes
 * (los feriados no están modelados: A VALIDAR con la normativa vigente, ver docs/NORMATIVA.md).
 */
class Plazos
{
    public const TZ = 'America/Santiago';

    /** Fin del día del vencimiento: el plazo corre desde el día siguiente al ingreso. */
    public static function vencimiento(Carbon $desde, int $dias, ?bool $habiles = null): Carbon
    {
        $f = $desde->copy()->setTimezone(self::TZ)->startOfDay();
        if ($habiles ?? (bool) config('sgd.plazos.habiles')) {
            for ($n = 0; $n < $dias;) {
                $f->addDay();
                if (! $f->isWeekend()) {
                    $n++;
                }
            }
        } else {
            $f->addDays($dias);
        }

        return $f->endOfDay();
    }

    /** @return 'vencido'|'por_vencer'|'en_plazo' */
    public static function situacion(Carbon $vence, ?Carbon $ahora = null, ?int $avisoDias = null): string
    {
        $ahora ??= now(self::TZ);
        if ($ahora->greaterThan($vence)) {
            return 'vencido';
        }
        $aviso = $avisoDias ?? (int) config('sgd.plazos.aviso_dias');

        return $ahora->copy()->startOfDay()->diffInDays($vence->copy()->startOfDay()) <= $aviso ? 'por_vencer' : 'en_plazo';
    }

    /** Días hasta el vencimiento (negativo si ya venció). */
    public static function diasRestantes(Carbon $vence, ?Carbon $ahora = null): int
    {
        $ahora ??= now(self::TZ);

        return (int) $ahora->copy()->startOfDay()->diffInDays($vence->copy()->startOfDay(), false);
    }
}
