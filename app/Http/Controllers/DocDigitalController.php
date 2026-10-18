<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\ComunicacionOficial;
use App\Services\AuditoriaService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
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
        $oauth = $cfg['client_id'] && $cfg['client_secret'] && str_starts_with((string) $cfg['token_url'], 'https://');
        $veContenido = $u->tienePermiso('comunicaciones.ver') && ($u->operaExpedientes() || $u->rolBase() === 'auditor');
        $scope = fn ($q) => $u->rolBase() === 'auditor' ? $q : $q->where('unidad_id', $u->unidad_id);
        $datos = [
            'u' => $u, 'modo' => $modo, 'modoEtq' => self::MODOS[$modo] ?? $modo, 'host' => str_starts_with($url, 'https://') ? parse_url($url, PHP_URL_HOST) : null,
            'credenciales' => $oauth ? 'OAuth2 (Client ID y Client Secret)' : ($cfg['api_token'] ? 'Token fijo' : null), 'rutas' => (bool) ($cfg['path_enviar'] && $cfg['path_recibidos'] && $cfg['path_acuse']),
            'estado' => DocDigitalEstado::leer(), 'veContenido' => $veContenido, 'puedeGestionar' => $u->tienePermiso('docdigital.gestionar'), 'unidadRecepcion' => $cfg['unidad_recepcion'],
            'diagnostico' => session('diagnostico'),
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

        return back()->with('ok', "Sincronización terminada: {$res['nuevas']} nueva(s), {$res['omitidas']} omitida(s).");
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
