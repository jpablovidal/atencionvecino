<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\RecursoImagen;
use App\Services\RecursoImagenService;
use Illuminate\Http\Request;

/** Imágenes del editor: subida (JSON para TinyMCE) y entrega autenticada. */
class RecursosController extends Controller
{
    public function subir(Request $r, RecursoImagenService $svc)
    {
        abort_unless($r->user()->tienePermiso('plantillas.gestionar') || $r->user()->tienePermiso('documentos.crear'), 403);
        $r->validate(['file' => 'required|file|max:2048']);
        try {
            $img = $svc->subir($r->file('file'), $r->user());
        } catch (FlujoException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['location' => $svc->url($img), 'ancho' => $img->ancho, 'alto' => $img->alto]);
    }

    public function mostrar(Request $r, RecursoImagen $recurso, RecursoImagenService $svc)
    {
        try {
            $c = $svc->contenido($recurso);
        } catch (\Throwable) {
            abort(404);
        }
        $resp = response($c['bytes'], 200, ['Content-Type' => $c['mime'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400', 'ETag' => '"'.$c['sha256'].'"',
            'Content-Disposition' => 'inline; filename="imagen-'.$recurso->id.'"']);
        $resp->isNotModified($r);

        return $resp;
    }
}
