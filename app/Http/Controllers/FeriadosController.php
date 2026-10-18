<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\TransparenciaFeriado;
use App\Services\FeriadoService;
use App\Support\Plazos;
use Illuminate\Http\Request;

/** Administrador de feriados: los días registrados no cuentan como días administrativos en los plazos de trámites y servicios. */
class FeriadosController extends Controller
{
    public function __construct(private readonly FeriadoService $svc) {}

    private function hecho(callable $fn, string $ok)
    {
        try {
            $r = $fn();
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', is_callable($ok) ? $ok($r) : $ok);
    }

    public function index(Request $r)
    {
        $d = $r->validate(['anio' => 'nullable|integer|between:2020,2100', 'sim_fecha' => 'nullable|date_format:Y-m-d', 'sim_dias' => 'nullable|integer|between:1,365']);
        $anio = (int) ($d['anio'] ?? now(Plazos::TZ)->year);
        $feriados = TransparenciaFeriado::query()->whereBetween('fecha', ["$anio-01-01", "$anio-12-31"])->orderBy('fecha')->get();
        $anios = TransparenciaFeriado::query()->pluck('fecha')->map(fn ($f) => (int) substr((string) $f, 0, 4))->push($anio, (int) now(Plazos::TZ)->year, (int) now(Plazos::TZ)->year + 1)->unique()->sort()->values();
        $sim = null;
        $errorSim = null;
        if (! empty($d['sim_fecha']) && ! empty($d['sim_dias'])) {
            try {
                $sim = $this->svc->simular($d['sim_fecha'], (int) $d['sim_dias']);
            } catch (FlujoException $e) {
                $errorSim = $e->getMessage();
            }
        }

        return view('app.feriados', ['feriados' => $feriados, 'anio' => $anio, 'anios' => $anios, 'tipos' => FeriadoService::TIPOS, 'sim' => $sim, 'errorSim' => $errorSim, 'f' => $d,
            'enCurso' => $this->svc->solicitudesEnCurso()]);
    }

    public function guardar(Request $r)
    {
        $d = $r->validate(['fecha' => 'required|date_format:Y-m-d', 'nombre' => 'required|string|max:300', 'tipo' => 'required|string|max:15']);

        return $this->hecho(fn () => $this->svc->agregar($r->user(), $d['fecha'], $d['nombre'], $d['tipo']), 'Feriado agregado: los plazos en curso se recalculan automáticamente.');
    }

    public function actualizar(Request $r, TransparenciaFeriado $feriado)
    {
        $d = $r->validate(['nombre' => 'required|string|max:300', 'tipo' => 'required|string|max:15']);

        return $this->hecho(fn () => $this->svc->actualizar($r->user(), $feriado, $d['nombre'], $d['tipo']), 'Feriado actualizado.');
    }

    public function eliminar(Request $r, TransparenciaFeriado $feriado)
    {
        return $this->hecho(fn () => $this->svc->quitar($r->user(), $feriado), 'Feriado eliminado: los plazos en curso se recalculan automáticamente.');
    }

    public function importar(Request $r)
    {
        $d = $r->validate(['lista' => 'required|string|max:20000']);

        return $this->hecho(fn () => $this->svc->importar($r->user(), $d['lista']), fn ($x) => "Carga lista: {$x['agregados']} agregado(s), {$x['omitidos']} ya existían.");
    }
}
