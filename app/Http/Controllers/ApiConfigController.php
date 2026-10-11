<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Services\Admin\ApiCatalogo;
use App\Services\Admin\ApiConfigService;
use Illuminate\Http\Request;

class ApiConfigController extends Controller
{
    public function __construct(private readonly ApiConfigService $svc) {}

    private function servicio(string $s): array
    {
        $c = ApiCatalogo::servicios();
        abort_unless(isset($c[$s]), 404);

        return $c[$s];
    }

    public function index()
    {
        $filas = [];
        foreach (ApiCatalogo::servicios() as $k => $d) {
            $e = $this->svc->estado($k);
            $ef = $this->svc->efectivo($k);
            $filas[] = ['clave' => $k, 'etiqueta' => $d['etiqueta'], 'descripcion' => $d['descripcion'], 'origen' => $e['activo'] ? 'BD (mantenedor)' : '.env', 'modo' => config(array_values(array_filter($d['campos'], fn ($c) => $c[1] === 'select'))[0][2] ?? 'sgd.x') ?? '—',
                'faltan' => collect($ef)->filter(fn ($x) => ! $x['definido'])->count(), 'total' => count($ef)];
        }

        return view('app.admin.api', ['filas' => $filas, 'secretosBd' => ApiConfigService::secretosEnBd()]);
    }

    public function editar(string $servicio)
    {
        $d = $this->servicio($servicio);

        return view('app.admin.api_form', ['clave' => $servicio, 'd' => $d, 'estado' => $this->svc->estado($servicio), 'ef' => $this->svc->efectivo($servicio), 'secretosBd' => ApiConfigService::secretosEnBd(), 'pruebas' => session('pruebas')]);
    }

    public function actualizar(Request $r, string $servicio)
    {
        $this->servicio($servicio);
        try {
            $this->svc->guardar($servicio, $r->except(['_token', 'borrar', 'activo']), $r->boolean('activo'), (array) $r->input('borrar', []), $r->user());
        } catch (FlujoException $e) {
            return back()->withInput($r->except(['_token']) + [])->with('error', $e->getMessage());
        }

        return redirect()->route('app.admin.api.editar', $servicio)->with('ok', 'Configuración guardada.');
    }

    public function volverEnv(Request $r, string $servicio)
    {
        $this->servicio($servicio);
        $this->svc->volverAEnv($servicio, $r->user());

        return back()->with('ok', 'Se usará la configuración del archivo .env.');
    }

    public function probar(string $servicio)
    {
        $this->servicio($servicio);

        return back()->with('pruebas', $this->svc->probar($servicio));
    }
}
