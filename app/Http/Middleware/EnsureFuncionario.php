<?php

namespace App\Http\Middleware;

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

        return $next($request);
    }
}
