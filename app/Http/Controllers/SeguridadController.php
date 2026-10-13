<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Incidente;
use App\Services\AuditoriaService;
use App\Support\AeadXChaCha;
use Illuminate\Http\Request;

class SeguridadController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public function panel(Request $r)
    {
        return view('app.seguridad', [
            'incidentes' => Incidente::query()->orderByDesc('id')->limit(50)->get(),
            'eventos' => AuditLog::query()->orderByDesc('id')->limit(50)->get(['id', 'ts', 'actor_rol', 'accion', 'entidad', 'entidad_id', 'ip']),
            'resultado' => session('verificacion'),
        ]);
    }

    public function verificar(Request $r)
    {
        $res = $this->audit->verificar();
        $this->audit->registrar('seguridad.verificar_cadena', null, null, $res);

        return redirect()->route('app.seguridad')->with('verificacion', $res);
    }

    public function incidente(Request $r)
    {
        $this->permiso('incidentes.gestionar'); // el auditor es solo lectura
        $d = $r->validate(['titulo' => 'required|string|max:190', 'descripcion' => 'required|string|max:4000', 'severidad' => 'required|in:baja,media,alta,critica', 'reportable_csirt' => 'nullable|boolean']);
        $i = Incidente::query()->create($d + ['detectado_en' => now(), 'creado_por' => $r->user()->id, 'reportable_csirt' => (bool) ($d['reportable_csirt'] ?? false)]);
        $this->audit->registrar('incidente.crear', 'incidente', $i->id, ['severidad' => $d['severidad']]);

        return back()->with('ok', 'Incidente registrado.');
    }

    public function actualizarIncidente(Request $r, Incidente $incidente)
    {
        $this->permiso('incidentes.gestionar');
        $d = $r->validate(['estado' => 'required|in:abierto,contenido,cerrado', 'marca' => 'nullable|in:alerta_temprana,segundo_reporte,informe_final']);
        $incidente->estado = $d['estado'];
        if (! empty($d['marca'])) {
            $incidente->{$d['marca'].'_en'} = now(); // plazos legales "A VALIDAR" en docs/NORMATIVA.md
        }
        $incidente->save();
        $this->audit->registrar('incidente.actualizar', 'incidente', $incidente->id, ['estado' => $d['estado'], 'marca' => $d['marca'] ?? null]);

        return back()->with('ok', 'Incidente actualizado.');
    }

    public function diagnostico(Request $r)
    {
        $ext = ['pdo_mysql', 'mbstring', 'openssl', 'sodium', 'intl', 'curl', 'gd', 'zip', 'fileinfo'];
        $storage = (string) config('sgd.storage_path');
        $checks = [];
        foreach ($ext as $e) {
            $checks[] = ['Extensión PHP '.$e, extension_loaded($e) || ($e === 'gd' && extension_loaded('imagick')) || ($e === 'sodium' && AeadXChaCha::soportado())];
        }
        $checks[] = ['PHP >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>=')];
        $checks[] = ['APP_DEBUG desactivado', ! config('app.debug')];
        $checks[] = ['APP_URL https', str_starts_with((string) config('app.url'), 'https://')];
        $checks[] = ['Directorio de archivos escribible', is_dir($storage) && is_writable($storage)];
        $checks[] = ['Almacenamiento fuera del docroot', ! str_starts_with(realpath($storage) ?: $storage, public_path())];
        $checks[] = ['Clave de cifrado de archivos configurada', (bool) config('sgd.file_keys')];
        $checks[] = ['AUDIT_HMAC_KEY configurada', strlen((string) config('sgd.audit_hmac_key')) >= 32];
        $checks[] = ['FirmaGob fuera de modo mock', config('sgd.firmagob.mode') !== 'mock'];
        $checks[] = ['ClaveÚnica fuera de modo mock', config('sgd.claveunica.mode') !== 'mock'];
        $sa = (string) config('sgd.google.service_account_json');
        $checks[] = ['Cuenta de servicio Google presente', $sa !== '' && is_file($sa)];
        $checks[] = ['Cuenta de servicio con permisos restrictivos (600/640)', $sa !== '' && is_file($sa) && (fileperms($sa) & 0007) === 0];
        $info = ['php' => PHP_VERSION, 'memory_limit' => ini_get('memory_limit'), 'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size'), 'zona_horaria' => date_default_timezone_get()];

        return view('app.diagnostico', ['checks' => $checks, 'info' => $info]);
    }
}
