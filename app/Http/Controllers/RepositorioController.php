<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Jobs\MigrarRepositorioADrive;
use App\Services\Admin\ApiConfigService;
use App\Services\Repositorio\RepositorioService;
use Illuminate\Http\Request;

class RepositorioController extends Controller
{
    public function __construct(private readonly RepositorioService $svc, private readonly ApiConfigService $api) {}

    public function index()
    {
        return view('app.admin.repositorio', [
            'stats' => $this->svc->estadisticas(), 'ultima' => $this->svc->ultimaPrueba(), 'estado' => $this->api->estado('repositorio'),
            'ef' => $this->api->efectivo('repositorio'), 'pasos' => session('pasos'), 'verif' => session('verif'),
            'cfg' => ['modo' => config('sgd.repositorio.modo'), 'sa' => config('sgd.repositorio.drive_sa_json'), 'drive' => config('sgd.repositorio.drive_shared_drive_id'), 'raiz' => config('sgd.repositorio.drive_root_folder_id')],
        ]);
    }

    public function guardar(Request $r)
    {
        $d = $r->validate(['modo' => 'required|in:local,drive', 'drive_sa_json' => 'nullable|string|max:300', 'drive_shared_drive_id' => 'nullable|string|max:100', 'drive_root_folder_id' => 'nullable|string|max:100']);
        try {
            $this->api->guardar('repositorio', $d, true, [], $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Configuración del repositorio guardada. Si cambió a Google Drive, pruebe la conexión y luego migre los archivos existentes.');
    }

    public function probar()
    {
        return back()->with('pasos', $this->svc->probar());
    }

    public function migrar()
    {
        if (config('sgd.repositorio.modo') !== 'drive') {
            return back()->with('error', 'Cambie primero el modo a Google Drive y pruebe la conexión.');
        }
        if (! $this->svc->ultimaPrueba()['ok']) {
            return back()->with('error', 'La última prueba de conexión no fue exitosa: pruebe la conexión antes de migrar.');
        }
        MigrarRepositorioADrive::dispatch();

        return back()->with('ok', 'Migración encolada: se procesa en lotes cortos por el cron; actualice esta página para ver el avance.');
    }

    public function verificar()
    {
        return back()->with('verif', $this->svc->verificarMuestra(10));
    }
}
