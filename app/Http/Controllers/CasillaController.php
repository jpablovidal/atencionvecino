<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarAvisoMovimiento;
use App\Models\AvisoMovimiento;
use App\Models\Notificacion;
use App\Services\AuditoriaService;
use App\Services\Casilla\CasillaAdapter;
use App\Services\NotificacionService;
use Illuminate\Http\Request;

/** Panel del notificador de Casilla Única para funcionarios: estado, notificaciones de su unidad, evidencia y reintentos. */
class CasillaController extends Controller
{
    public function __construct(private readonly AuditoriaService $audit, private readonly CasillaAdapter $casilla) {}

    /** Notificaciones por Casilla Única visibles para la persona: las de los expedientes de su unidad (auditoría: todas). */
    private function alcance(Request $r)
    {
        $u = $r->user();
        $q = Notificacion::query()->where('canal', 'casilla_unica');

        return $u->veTodo() ? $q : $q->whereHas('expediente', fn ($e) => $e->where('unidad_id', $u->unidad_id));
    }

    public function index(Request $r)
    {
        $u = $r->user();
        $cfg = config('sgd.casilla');
        $api = $this->casilla->modoApi();
        $base = fn () => $this->alcance($r);
        $kpis = [
            ['icono' => 'inbox', 'valor' => $base()->where('estado', 'pendiente')->count(), 'etiqueta' => 'Pendientes de evidencia', 'nivel' => $base()->where('estado', 'pendiente')->exists() ? 'aviso' : 'ok'],
            ['icono' => 'alert', 'valor' => $base()->where('estado', 'fallida')->count(), 'etiqueta' => 'Envíos fallidos', 'nivel' => $base()->where('estado', 'fallida')->exists() ? 'aviso' : 'ok'],
            ['icono' => 'check-circle', 'valor' => $base()->where('estado', 'enviada')->count(), 'etiqueta' => 'Enviadas con evidencia', 'nivel' => 'ok'],
        ];

        return view('app.casilla', [
            'api' => $api, 'host' => str_starts_with((string) $cfg['api_url'], 'https://') ? parse_url((string) $cfg['api_url'], PHP_URL_HOST) : null,
            'credenciales' => $this->casilla->usaOauth() ? 'OAuth2 (Client ID y Client Secret)' : ($cfg['api_token'] ? 'Token fijo' : null),
            'puedeGestionar' => $u->tienePermiso('casilla.gestionar'), 'puedeEvidencia' => $u->tienePermiso('notificaciones.gestionar') && $u->operaExpedientes(),
            'kpis' => $kpis, 'diagnostico' => session('diagnostico'),
            'avisos' => AvisoMovimiento::query()->where('canal', 'casilla_unica')->whereIn('estado', ['pendiente', 'fallida'])->with('movimiento.unidad')->orderBy('id')->limit(30)->get(),
            'avisosTotal' => AvisoMovimiento::query()->where('canal', 'casilla_unica')->whereIn('estado', ['pendiente', 'fallida'])->count(),
            'pendientes' => $base()->whereIn('estado', ['pendiente', 'fallida'])->with('expediente:id,codigo,unidad_id', 'documento:id,titulo')->orderBy('id')->limit(50)->get(),
            'recientes' => $base()->where('estado', 'enviada')->with('expediente:id,codigo', 'documento:id,titulo')->orderByDesc('id')->limit(15)->get(),
        ]);
    }

    public function diagnosticar(Request $r)
    {
        $res = $this->casilla->diagnosticar();
        $this->audit->registrar('casilla.diagnostico', null, null, ['modo' => $this->casilla->modoApi() ? 'api' : 'outbox', 'ok' => collect($res)->every(fn ($f) => $f[1])]);

        return back()->with('diagnostico', $res);
    }

    /** Registra el acuse de Casilla Única de un aviso de movimiento (modo outbox). */
    public function avisoEvidencia(Request $r, AvisoMovimiento $aviso)
    {
        $d = $r->validate(['valor' => 'required|string|max:250']);
        abort_unless($aviso->canal === 'casilla_unica' && $aviso->estado !== 'enviada', 404);
        $aviso->forceFill(['estado' => 'enviada', 'evidencia_tipo' => 'id_casilla_unica', 'evidencia' => trim($d['valor']), 'evidencia_en' => now(), 'ultimo_error' => null])->save();
        $this->audit->registrar('aviso.evidencia', 'aviso', $aviso->id, ['canal' => 'casilla_unica']);

        return back()->with('ok', 'Evidencia registrada.');
    }

    public function reintentar(Request $r, NotificacionService $svc)
    {
        if (! $this->casilla->modoApi()) {
            return back()->with('error', 'Casilla Única está en modo outbox: registre la evidencia de cada notificación manualmente.');
        }
        $ok = 0;
        $mal = 0;
        foreach (AvisoMovimiento::query()->where('canal', 'casilla_unica')->whereIn('estado', ['pendiente', 'fallida'])->orderBy('id')->limit(20)->pluck('id') as $id) {
            try {
                EnviarAvisoMovimiento::dispatch($id);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        foreach ($this->alcance($r)->whereIn('estado', ['pendiente', 'fallida'])->orderBy('id')->limit(20)->get() as $n) {
            try {
                $svc->reintentarCasilla($n) ? $ok++ : $mal++;
            } catch (FlujoException) {
                $mal++;
            }
        }
        $this->audit->registrar('casilla.reintentos', null, null, ['ok' => $ok, 'fallidos' => $mal]);

        return back()->with($mal ? 'aviso' : 'ok', "Reintentos: {$ok} enviada(s), {$mal} sin confirmar.");
    }
}
