<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $r = $next($request);
        $r->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $r->headers->set('Pragma', 'no-cache');

        return $r;
    }
}
