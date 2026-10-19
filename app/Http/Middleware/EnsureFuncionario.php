<?php

namespace App\Http\Middleware;

use App\Services\Admin\TotpService;
use App\Support\Espacio;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureFuncionario
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u || ! $u->activo) {
            if ($u) {
                Auth::logout();
                $request->session()->invalidate();
            }

            return redirect()->route('ingreso');
        }

        Espacio::fijar(Espacio::APP);

        // Los administradores del sistema deben tener el segundo factor (TOTP) configurado y verificado en esta sesión.
        if (TotpService::exigido($u) && ! $request->routeIs('app.2fa*') && (int) $request->session()->get('totp_ok') !== (int) $u->id) {
            return redirect()->route('app.2fa');
        }

        return $next($request);
    }
}
