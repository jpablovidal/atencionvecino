<?php

namespace App\Http\Middleware;

use App\Services\Sistema\ConexionesService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Cuenta cada conexión entrante (IP y tipo de ruta) DESPUÉS de enviar la respuesta. Solo contadores por hora: no guarda URL, parámetros ni contenido. */
class RegistrarConexion
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('sgd.registrar_conexiones')) {
            return;
        }
        $p = '/'.ltrim($request->path(), '/');
        if (str_starts_with($p, '/marca/') || $p === '/up') {
            return; // recursos de marca y salud: no son conexiones de personas
        }
        $grupo = match (true) {
            str_starts_with($p, '/app') => 'funcionarios',
            str_starts_with($p, '/ciudadano') => 'ciudadanos',
            str_starts_with($p, '/ingreso'), str_starts_with($p, '/claveunica'), $p === '/salir' => 'ingreso',
            default => 'publico',
        };
        try {
            ConexionesService::registrar('entrante', (string) $request->ip(), $grupo, $response->getStatusCode() >= 400);
        } catch (\Throwable) {
            // el conteo nunca debe afectar al servicio
        }
    }
}
