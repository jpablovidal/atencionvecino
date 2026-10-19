<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Jobs\MigrarRepositorioADrive;
use App\Services\Admin\ApiConfigService;
use App\Services\AuditoriaService;
use App\Services\Repositorio\CredencialDriveService;
use App\Services\Repositorio\RepositorioService;
use Illuminate\Http\Request;

class RepositorioController extends Controller
{
    public function __construct(private readonly RepositorioService $svc, private readonly ApiConfigService $api, private readonly AuditoriaService $audit, private readonly CredencialDriveService $cred) {}

    public function index()
    {
        $est = $this->api->estado('repositorio');
        $sa = ($est['activo'] ? ($est['valores']['drive_sa_json'] ?? null) : null) ?: config('sgd.repositorio.drive_sa_json');

        return view('app.admin.repositorio', [
            'stats' => $this->svc->estadisticas(), 'ultima' => $this->svc->ultimaPrueba(), 'estado' => $this->api->estado('repositorio'),
            'ef' => $this->api->efectivo('repositorio'), 'pasos' => session('pasos'), 'verif' => session('verif'),
            'cuenta' => CredencialDriveService::resumen($sa),
            'cfg' => ['modo' => config('sgd.repositorio.modo'), 'sa' => $sa, 'drive' => config('sgd.repositorio.drive_shared_drive_id'), 'raiz' => config('sgd.repositorio.drive_root_folder_id')],
        ]);
    }

    public function guardar(Request $r)
    {
        // se acepta pegar la URL de Drive: se extrae el ID
        $r->merge(array_map(fn ($k) => CredencialDriveService::extraerId((string) $r->input($k)), ['drive_shared_drive_id' => 'drive_shared_drive_id', 'drive_root_folder_id' => 'drive_root_folder_id']));
        $d = $r->validate(['modo' => 'required|in:local,drive', 'drive_sa_json' => 'nullable|string|max:300', 'drive_sa_archivo' => 'nullable|file|max:20', 'drive_shared_drive_id' => 'nullable|string|max:100', 'drive_root_folder_id' => 'nullable|string|max:100']);
        try {
            if ($r->hasFile('drive_sa_archivo')) {
                $c = $this->cred->guardar($r->file('drive_sa_archivo'));
                $d['drive_sa_json'] = $c['ruta'];
                $this->audit->registrar('repositorio.credencial_drive', null, null, ['cuenta' => $c['email'], 'proyecto' => $c['proyecto']]);
            }
            unset($d['drive_sa_archivo']);
            $this->api->guardar('repositorio', $d, true, [], $r->user());
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', ($r->hasFile('drive_sa_archivo') ? 'Clave de la cuenta de servicio cargada y guardada en el servidor. ' : '').'Configuración del repositorio guardada. Si cambió a Google Drive, pruebe la conexión y luego migre los archivos existentes.');
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
