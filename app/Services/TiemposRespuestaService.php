<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Unidad;
use App\Support\Plazos;
use Illuminate\Support\Collection;

/**
 * Cuánto demora el municipio en responder: desde el ingreso de la solicitud hasta que su gestión termina (expediente cerrado),
 * en días hábiles (sin sábados, domingos ni feriados registrados) y corridos. Se mide por dirección (la que gestionó el expediente),
 * por área (según el área de esa dirección) y para todo el municipio. Solo cuenta solicitudes terminadas en la ventana indicada.
 */
class TiemposRespuestaService
{
    public const VENTANA_DIAS = 365;

    public function __construct(private readonly SeguimientoService $seg) {}

    /**
     * @return array{ventana:int,municipio:array<string,mixed>,direcciones:list<array<string,mixed>>,areas:list<array<string,mixed>>}
     */
    public function calcular(?Collection $snap = null, int $ventana = self::VENTANA_DIAS): array
    {
        $snap ??= $this->seg->instantanea();
        $desde = now(Plazos::TZ)->subDays($ventana);
        $term = $snap->filter(fn ($x) => $x['etapa'] === 'terminada' && $x['fin'] && $x['fin']->greaterThan($desde))->map(function ($x) {
            $ing = $x['ingreso']->copy()->setTimezone(Plazos::TZ);
            $fin = $x['fin']->copy()->setTimezone(Plazos::TZ);

            return $x + ['habiles' => max(0, Plazos::diasHabiles($ing, $fin)), 'corridos' => max(0.0, $ing->diffInHours($fin) / 24), 'en_plazo' => ! $fin->greaterThan($x['vence'])];
        })->values();
        $unidades = Unidad::query()->get(['id', 'nombre', 'area_id'])->keyBy('id');
        $areas = Area::query()->pluck('nombre', 'id');

        return [
            'ventana' => $ventana,
            'municipio' => $this->resumen($term),
            'direcciones' => $term->groupBy(fn ($x) => (int) $x['unidad_id'])->map(fn ($g, $id) => ['nombre' => (string) ($unidades[$id]->nombre ?? 'Sin dirección')] + $this->resumen($g))
                ->sortBy('nombre')->values()->all(),
            'areas' => $term->groupBy(fn ($x) => (int) ($unidades[(int) $x['unidad_id']]->area_id ?? 0))->map(fn ($g, $id) => ['nombre' => $id ? (string) ($areas[$id] ?? 'Área') : 'Sin área asignada'] + $this->resumen($g))
                ->sortBy('nombre')->values()->all(),
        ];
    }

    /** @return array{n:int,habiles:?float,corridos:?float,mediana_habiles:?float,en_plazo:?float} */
    public function resumen(Collection $g): array
    {
        $n = $g->count();
        if ($n === 0) {
            return ['n' => 0, 'habiles' => null, 'corridos' => null, 'mediana_habiles' => null, 'en_plazo' => null];
        }
        $h = $g->pluck('habiles')->sort()->values();
        $mid = intdiv($n, 2);
        $mediana = $n % 2 ? (float) $h[$mid] : ($h[$mid - 1] + $h[$mid]) / 2;

        return ['n' => $n, 'habiles' => round($g->avg('habiles'), 1), 'corridos' => round($g->avg('corridos'), 1), 'mediana_habiles' => round($mediana, 1),
            'en_plazo' => round(100 * $g->where('en_plazo', true)->count() / $n, 0)];
    }

    public static function fmt(?float $v, string $unidad = 'días'): string
    {
        return $v === null ? 'Sin datos' : number_format($v, 1, ',', '.').' '.$unidad;
    }
}
