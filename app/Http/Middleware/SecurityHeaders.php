<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $r = $next($request);
        // style-src 'unsafe-inline': TinyMCE aplica estilos en línea; los SCRIPTS en línea están prohibidos.
        $csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; "
            ."frame-src 'self' blob:; frame-ancestors 'none'; form-action 'self' https://accounts.claveunica.gob.cl; base-uri 'none'; object-src 'none'"; // ClaveÚnica: el cierre de sesión redirige desde un formulario POST
        $h = $r->headers;
        // Visor de archivos: el propio archivo (PDF, imagen, texto) puede mostrarse en un marco de ESTA misma aplicación; nunca de otro origen.
        $embebible = $h->has('X-SGD-Embebible');
        $h->remove('X-SGD-Embebible');
        if ($embebible) {
            $csp = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'";
        }
        $h->set('Content-Security-Policy', $csp);
        $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('X-Frame-Options', $embebible ? 'SAMEORIGIN' : 'DENY');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $r;
    }
}
