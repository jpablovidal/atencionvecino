<?php

namespace App\Http\Controllers;

use App\Services\Admin\AparienciaService;
use Illuminate\Http\Request;

/** Recursos públicos de marca: CSS de colores y logo (servidos por la app, con ETag y caché corta). */
class MarcaController extends Controller
{
    public function css(Request $r, AparienciaService $a)
    {
        $css = $a->css();
        $resp = response($css, 200, ['Content-Type' => 'text/css; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=300', 'ETag' => '"'.md5($css).'"']);
        $resp->isNotModified($r);

        return $resp;
    }

    public function logo(Request $r, AparienciaService $a)
    {
        $l = $a->logo();
        abort_if($l === null, 404);
        $resp = response($l['bytes'], 200, ['Content-Type' => $l['mime'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400', 'ETag' => '"'.$l['sha256'].'"', 'Content-Disposition' => 'inline; filename="logo"']);
        $resp->isNotModified($r);

        return $resp;
    }
}
