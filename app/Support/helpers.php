<?php

use Illuminate\Http\Response;

if (! function_exists('descarga')) {
    /** Respuesta de descarga segura: nosniff, nombre saneado, sin caché. */
    function descarga(string $bytes, string $nombre, string $mime, bool $inline = false): Response
    {
        $nombre = preg_replace('/[^\w.\-]+/u', '_', $nombre) ?: 'archivo';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$nombre.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
            'Content-Length' => (string) strlen($bytes),
        ]);
    }
}
