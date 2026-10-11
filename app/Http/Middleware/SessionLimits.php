<?php

namespace App\Http\Middleware;

use App\Services\AuditoriaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Expiración por inactividad (30 min) y absoluta (8 h). */
class SessionLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        $s = $request->session();
        if ($s->has('login_at')) {
            $ahora = time();
            $inactivo = $ahora - (int) $s->get('last_seen', $ahora) > config('sgd.sesion_inactividad_min') * 60;
            $absoluta = $ahora - (int) $s->get('login_at') > config('sgd.sesion_absoluta_horas') * 3600;
            if ($inactivo || $absoluta) {
                app(AuditoriaService::class)->registrar('auth.sesion_expirada', null, null, ['tipo' => $absoluta ? 'absoluta' : 'inactividad']);
                Auth::logout();
                $s->invalidate();
                $s->regenerateToken();

                return redirect()->route('ingreso')->with('aviso', 'Su sesión expiró. Ingrese nuevamente.');
            }
            $s->put('last_seen', $ahora);
        }

        return $next($request);
    }
}
