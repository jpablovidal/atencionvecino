<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Autorización por permiso (cualquiera de los indicados). Complementa, no reemplaza, las Policies. */
class EnsurePermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $u = $request->user();
        abort_unless($u && collect($permisos)->contains(fn ($p) => $u->tienePermiso($p)), 403);

        return $next($request);
    }
}
