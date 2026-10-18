<?php

namespace App\Http\Middleware;

use App\Support\Espacio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCiudadano
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('ciudadano')) {
            return redirect()->route('ingreso');
        }

        Espacio::fijar(Espacio::CIUDADANO);

        return $next($request);
    }
}
