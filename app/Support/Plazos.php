<?php

namespace App\Support;

use App\Models\Solicitud;
use App\Models\Tramite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo de vencimientos. Días corridos por defecto; en días hábiles cuenta lunes a viernes salvo los feriados registrados por
 * Transparencia (administrar plazos). Los feriados no vienen precargados: A VALIDAR con la normativa vigente (docs/NORMATIVA.md).
 */
class Plazos
{
    public const TZ = 'America/Santiago';

    /** @var array<string,bool>|null */
    private static ?array $feriados = null;

    public static function olvidarFeriados(): void
    {
        self::$feriados = null;
    }

    /** @return array<string,bool> fechas Y-m-d registradas como feriado */
    public static function feriados(): array
    {
        if (self::$feriados === null) {
            try {
                self::$feriados = DB::table('transparencia_feriados')->pluck('fecha')->mapWithKeys(fn ($f) => [substr((string) $f, 0, 10) => true])->all();
            } catch (\Throwable) {
                self::$feriados = [];
            }
        }

        return self::$feriados;
    }

    public static function esHabil(Carbon $f): bool
    {
        return ! $f->isWeekend() && ! isset(self::feriados()[$f->format('Y-m-d')]);
    }

    /** Fin del día del vencimiento: el plazo corre desde el día siguiente a la fecha de inicio. */
    public static function vencimiento(Carbon $desde, int $dias, ?bool $habiles = null): Carbon
    {
        $f = $desde->copy()->setTimezone(self::TZ)->startOfDay();
        if ($habiles ?? (bool) config('sgd.plazos.habiles')) {
            for ($n = 0; $n < $dias;) {
                $f->addDay();
                if (self::esHabil($f)) {
                    $n++;
                }
            }
        } else {
            $f->addDays($dias);
        }

        return $f->endOfDay();
    }

    /** Días hábiles completos entre dos fechas (excluye la inicial, incluye la final). */
    public static function diasHabiles(Carbon $desde, Carbon $hasta): int
    {
        $f = $desde->copy()->setTimezone(self::TZ)->startOfDay();
        $fin = $hasta->copy()->setTimezone(self::TZ)->startOfDay();
        $n = 0;
        while ($f->lt($fin)) {
            $f->addDay();
            if (self::esHabil($f)) {
                $n++;
            }
        }

        return $n;
    }

    /** Vencimiento de una solicitud: plazo del trámite (corridos u hábiles) más la prórroga, si la hubo. */
    public static function deSolicitud(Solicitud $s, ?Tramite $t = null): Carbon
    {
        $t ??= $s->tramite;
        $habiles = (bool) $t->plazo_habiles;
        $v = self::vencimiento($s->created_at, (int) $t->plazo_dias, $habiles);
        if ($s->prorroga_dias) {
            $v = self::vencimiento($v, (int) $s->prorroga_dias, $habiles);
        }

        return $v;
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
