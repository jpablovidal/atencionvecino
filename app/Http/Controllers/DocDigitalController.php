<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\ComunicacionOficial;
use App\Models\DocDigitalEvidencia;
use App\Services\AuditoriaService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigital\EvidenciaService;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use App\Services\Interop\DocDigitalDashboardService;
use App\Services\Interop\DocDigitalEstado;
use Illuminate\Http\Request;

/** Panel de gestión de DocDigital para funcionarios: estado de la conexión, comunicaciones, sincronización y reintentos. */
class DocDigitalController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit) {}

    private const MODOS = ['outbox' => 'Outbox (registro manual)', 'mock' => 'Simulado (solo desarrollo)', 'demo' => 'DemoDoc (pruebas)', 'prod' => 'Producción'];

    public function index(Request $r)
    {
        $u = $r->user();
        $cfg = config('sgd.docdigital');
        $modo = (string) $cfg['mode'];
        $url = (string) ($modo === 'prod' ? $cfg['url_prod'] : $cfg['url_demo']);
        $oauth = $cfg['client_id'] && $cfg['client_secret'];
        $veContenido = $u->tienePermiso('comunicaciones.ver') && ($u->operaExpedientes() || $u->rolBase() === 'auditor');
        $scope = fn ($q) => $u->rolBase() === 'auditor' ? $q : $q->where('unidad_id', $u->unidad_id);
        $datos = [
            'u' => $u, 'modo' => $modo, 'modoEtq' => self::MODOS[$modo] ?? $modo, 'host' => str_starts_with($url, 'https://') ? parse_url($url, PHP_URL_HOST) : null,
            'credenciales' => $oauth ? 'OAuth2 (Client ID y Client Secret)' : ($cfg['api_token'] ? 'Token fijo' : null),
            'estado' => DocDigitalEstado::leer(), 'veContenido' => $veContenido, 'puedeGestionar' => $u->tienePermiso('docdigital.gestionar'), 'unidadRecepcion' => $cfg['unidad_recepcion'],
            'diagnostico' => session('diagnostico'),
            'evidenciaHasta' => EvidenciaService::activaHasta(), 'evidenciaTotal' => DocDigitalEvidencia::query()->count(),
            'evidencias' => $u->tienePermiso('docdigital.gestionar') ? DocDigitalEvidencia::query()->orderByDesc('id')->limit(25)->get() : collect(),
        ];
        if ($veContenido) {
            $ent = fn () => $scope(ComunicacionOficial::query()->where('direccion', 'entrante'));
            $sal = fn () => $scope(ComunicacionOficial::query()->where('direccion', 'saliente'));
            $datos['kpis'] = [
                ['icono' => 'mail', 'valor' => $ent()->where('acuse_estado', 'pendiente')->count(), 'etiqueta' => 'Recibidas con acuse pendiente', 'nivel' => $ent()->where('acuse_estado', 'pendiente')->exists() ? 'aviso' : 'ok'],
                ['icono' => 'inbox', 'valor' => $ent()->count(), 'etiqueta' => 'Comunicaciones recibidas', 'nivel' => 'ok'],
                ['icono' => 'send', 'valor' => $sal()->where('estado', 'pendiente')->count(), 'etiqueta' => 'Envíos pendientes', 'nivel' => 'info'],
                ['icono' => 'alert', 'valor' => $sal()->where('estado', 'fallida')->count(), 'etiqueta' => 'Envíos fallidos', 'nivel' => $sal()->where('estado', 'fallida')->exists() ? 'aviso' : 'ok'],
                ['icono' => 'check-circle', 'valor' => $sal()->where('estado', 'enviada')->count(), 'etiqueta' => 'Enviadas', 'nivel' => 'ok'],
            ];
            $datos['panel'] = app(DocDigitalDashboardService::class)->para($u);
            $datos['recibidas'] = $ent()->orderByDesc('id')->limit(15)->get();
            $datos['enviadas'] = $sal()->orderByDesc('id')->limit(15)->get();
        }

        return view('app.docdigital', $datos);
    }

    public function diagnosticar(Request $r)
    {
        $cfg = config('sgd.docdigital');
        $modo = (string) $cfg['mode'];
        if (in_array($modo, ['demo', 'prod'], true)) {
            $res = (new HttpDocDigitalClient($cfg, $modo))->diagnosticar();
        } else {
            $res = [['Modo de conexión', $modo !== 'outbox', self::MODOS[$modo] ?? $modo], ['Conexión por API', false, 'El modo actual no se conecta a DocDigital: cambie el modo en Administración → APIs e integraciones.']];
        }
        $this->audit->registrar('docdigital.diagnostico', null, null, ['modo' => $modo, 'ok' => collect($res)->every(fn ($f) => $f[1])]);

        return back()->with('diagnostico', $res);
    }

    /** Busca entidades en DocDigital (GET /entidades/) para copiar su id a la institución del Codificador. */
    public function entidades(Request $r)
    {
        $d = $r->validate(['q' => 'nullable|string|min:3|max:100']);
        $cfg = config('sgd.docdigital');
        $modo = (string) $cfg['mode'];
        $filas = null;
        $error = null;
        if (! in_array($modo, ['demo', 'prod'], true)) {
            $error = 'DocDigital no está en modo DemoDoc ni Producción.';
        } elseif (! empty($d['q'])) {
            try {
                $filas = (new HttpDocDigitalClient($cfg, $modo))->buscarEntidades($d['q'], 30);
            } catch (DocDigitalException $e) {
                $error = $e->getMessage();
            }
        }
        $this->audit->registrar('docdigital.buscar_entidades', null, null, ['modo' => $modo]);

        return view('app.docdigital_entidades', ['q' => $d['q'] ?? '', 'filas' => $filas, 'error' => $error]);
    }

    public function sincronizar(Request $r, ComunicacionService $svc)
    {
        if (! $svc->modoApi()) {
            return back()->with('error', 'DocDigital está en modo outbox: no hay nada que descargar. Cambie el modo en Administración → APIs e integraciones.');
        }
        try {
            $res = $svc->sincronizarRecibidos();
        } catch (DocDigitalException|FlujoException $e) {
            DocDigitalEstado::registrar(null, $e->getMessage());

            return back()->with('error', 'No se pudo sincronizar: '.$e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            DocDigitalEstado::registrar(null, 'Error inesperado al sincronizar (vea el registro de la aplicación).');

            return back()->with('error', 'No fue posible sincronizar con DocDigital. Revise el diagnóstico.');
        }

        $svc->refrescarEstados();

        return back()->with('ok', "Sincronización terminada: {$res['nuevas']} nueva(s), {$res['omitidas']} omitida(s).");
    }

    /** Activa (24 h), desactiva o borra el registro de evidencias (JSON de cada llamada a DocDigital). */
    public function evidencia(Request $r)
    {
        $d = $r->validate(['accion' => 'required|in:activar,desactivar,borrar']);
        $u = $r->user();
        if ($d['accion'] === 'activar') {
            $hasta = EvidenciaService::activar(24, $u->id);
            $this->audit->registrar('docdigital.evidencia_activar', null, null, ['hasta' => $hasta->format('c')]);

            return back()->with('ok', 'Registro de evidencias activo hasta '.$hasta->format('d-m-Y H:i').'. Solo se guardan el JSON y la respuesta de cada llamada (sin archivos ni credenciales).');
        }
        if ($d['accion'] === 'desactivar') {
            EvidenciaService::desactivar($u->id);
            $this->audit->registrar('docdigital.evidencia_desactivar');

            return back()->with('ok', 'Registro de evidencias desactivado.');
        }
        $n = EvidenciaService::vaciar();
        $this->audit->registrar('docdigital.evidencia_borrar', null, null, ['filas' => $n]);

        return back()->with('ok', "Evidencias borradas: $n.");
    }

    /** Descarga las evidencias: JSON único, o ZIP con un archivo por llamada (solicitud y respuesta) si hay soporte ZIP. */
    public function evidenciaDescargar(Request $r)
    {
        $d = $r->validate(['formato' => 'nullable|in:json,zip']);
        $filas = EvidenciaService::exportar();
        $this->audit->registrar('docdigital.evidencia_descargar', null, null, ['filas' => count($filas), 'formato' => $d['formato'] ?? 'json']);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        $sello = now()->format('Ymd-His');
        if (($d['formato'] ?? 'json') === 'zip' && class_exists(\ZipArchive::class)) {
            $tmp = tempnam(sys_get_temp_dir(), 'ddev');
            $z = new \ZipArchive;
            $z->open($tmp, \ZipArchive::OVERWRITE);
            foreach ($filas as $f) {
                $base = sprintf('%03d_%s_%s', $f['n'], $f['metodo'], trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $f['ruta']), '-'));
                $z->addFromString($base.'_solicitud.json', json_encode(['metodo' => $f['metodo'], 'ruta' => $f['ruta'], 'consulta' => $f['consulta'], 'cuerpo' => $f['solicitud']], $flags));
                $z->addFromString($base.'_respuesta.json', json_encode(['http_status' => $f['http_status'], 'cuerpo' => $f['respuesta']], $flags));
            }
            $z->addFromString('indice.json', json_encode(array_map(fn ($f) => ['n' => $f['n'], 'fecha' => $f['fecha'], 'ambiente' => $f['ambiente'], 'metodo' => $f['metodo'], 'ruta' => $f['ruta'], 'http_status' => $f['http_status']], $filas), $flags));
            $z->close();

            return response()->download($tmp, "docdigital-evidencias-$sello.zip")->deleteFileAfterSend(true);
        }

        return response(json_encode(['generado' => now()->toIso8601String(), 'llamadas' => $filas], $flags), 200, ['Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => "attachment; filename=\"docdigital-evidencias-$sello.json\""]);
    }

    public function reintentar(Request $r, ComunicacionService $svc)
    {
        $u = $r->user();
        if (! $svc->modoApi()) {
            return back()->with('error', 'DocDigital está en modo outbox: los envíos se registran manualmente.');
        }
        $q = ComunicacionOficial::query()->where('direccion', 'saliente')->where('estado', 'fallida');
        if ($u->operaExpedientes()) {
            $q->where('unidad_id', $u->unidad_id);
        }
        $ok = 0;
        $mal = 0;
        foreach ($q->orderBy('id')->limit(20)->get() as $c) {
            try {
                $svc->enviar($c);
                $c->refresh()->estado === 'enviada' ? $ok++ : $mal++;
            } catch (\Throwable) {
                $mal++;
            }
        }
        $this->audit->registrar('docdigital.reintentos', null, null, ['ok' => $ok, 'fallidos' => $mal]);

        return back()->with($mal ? 'aviso' : 'ok', "Reintentos: {$ok} enviada(s), {$mal} con error.");
    }
}
