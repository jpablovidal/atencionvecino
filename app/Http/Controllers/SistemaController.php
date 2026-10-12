<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\Sistema\ComponentesService;
use App\Services\Sistema\RecursosService;
use Illuminate\Http\Request;

/** Panel de recursos del servidor e inventario de componentes (solo lectura). */
class SistemaController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function recursos(Request $r, RecursosService $svc)
    {
        $r->validate(['rango' => 'nullable|in:24h,7d,30d']);
        $rango = $r->query('rango', '24h');
        $horas = ['24h' => 24, '7d' => 168, '30d' => 720][$rango];

        return view('app.sistema.recursos', ['s' => $svc->instantanea(), 'rango' => $rango, 'historico' => $svc->historico($horas)]);
    }

    public function componentes(Request $r, ComponentesService $svc)
    {
        $f = $r->validate(['q' => 'nullable|string|max:60', 'dev' => 'nullable|in:0,1']);
        $paquetes = $svc->paquetes();
        if (! empty($f['q'])) {
            $q = mb_strtolower($f['q']);
            $paquetes = array_values(array_filter($paquetes, fn ($p) => str_contains(mb_strtolower($p['nombre'].' '.$p['licencia']), $q)));
        }

        return view('app.sistema.componentes', ['plat' => $svc->plataforma(), 'paquetes' => $paquetes, 'filtro' => $f + ['q' => null], 'front' => $svc->frontend(), 'plugins' => $svc->pluginsTiny(),
            'ext' => $svc->externos(), 'integraciones' => $svc->integraciones(), 'sbom' => is_file(base_path('docs/sbom.cdx.json'))]);
    }

    public function exportar(ComponentesService $svc)
    {
        $this->audit->registrar('sistema.exportar_inventario');
        $json = json_encode($svc->inventario(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, ['Content-Type' => 'application/json; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="inventario-componentes-'.now()->format('Ymd').'.json"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }
}
