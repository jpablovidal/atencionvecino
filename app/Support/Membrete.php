<?php

namespace App\Support;

use App\Services\Admin\AparienciaService;

/**
 * Membrete de los documentos oficiales en PDF, al estilo de los decretos alcaldicios de la municipalidad: logotipo y «REPÚBLICA DE CHILE / I. MUNICIPALIDAD DE
 * SANTO DOMINGO» a la izquierda, sellos de certificación a la derecha y, al pie, nombre, dirección, teléfono, sitio web y número de página.
 * Las imágenes viajan como data URI (los PDF no cargan recursos externos).
 */
class Membrete
{
    private const LOGO_DEFECTO = 'assets/img/logo-santo-domingo.png';

    private const SELLOS = 'assets/img/membrete/sellos-certificacion.png';

    /** @return array{logo:?string,sellos:?string,republica:string,institucion:string,pie:string,direccion:string,web:string}|null null si el membrete está apagado */
    public static function datos(): ?array
    {
        $c = (array) config('sgd.membrete');
        if (empty($c['activo'])) {
            return null;
        }
        $logo = null;
        try {
            $logo = app(AparienciaService::class)->logoDataUri(); // el logo que el municipio subió en «Apariencia», si lo hay
        } catch (\Throwable) {
        }

        return [
            'logo' => $logo ?: self::dataUri(self::LOGO_DEFECTO),
            'sellos' => ! empty($c['sellos']) ? self::dataUri(self::SELLOS) : null,
            'republica' => (string) ($c['republica'] ?? ''), 'institucion' => (string) ($c['institucion'] ?? ''), 'pie' => (string) ($c['pie'] ?? ''),
            'direccion' => (string) ($c['direccion'] ?? ''), 'web' => (string) ($c['web'] ?? ''),
        ];
    }

    private static function dataUri(string $rutaPublica): ?string
    {
        $f = public_path($rutaPublica);

        return is_file($f) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($f)) : null;
    }
}
