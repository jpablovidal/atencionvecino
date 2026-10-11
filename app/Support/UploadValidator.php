<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/** Valida subidas por CONTENIDO (firma binaria), no por extensión ni Content-Type declarado. */
class UploadValidator
{
    private const PDF_PELIGROSOS = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/RichMedia', '/OpenAction', '/AA'];

    /** @return array{mime:string,ext:string} */
    public static function validar(UploadedFile $f): array
    {
        if (! $f->isValid() || $f->getSize() === false || $f->getSize() === 0 || $f->getSize() > config('sgd.upload_max_bytes')) {
            throw new \InvalidArgumentException('Archivo inválido o excede el tamaño máximo (10 MB).');
        }
        $bytes = (string) file_get_contents($f->getRealPath());

        return self::validarBytes($bytes);
    }

    /** @return array{mime:string,ext:string} */
    public static function validarBytes(string $b): array
    {
        $head = substr($b, 0, 8);
        if (str_starts_with($b, '%PDF-')) {
            if (! str_contains(substr($b, -2048), '%%EOF')) {
                throw new \InvalidArgumentException('PDF incompleto o corrupto.');
            }
            // Se normalizan escapes #xx de nombres PDF para evitar evasión (/J#61vaScript).
            $norm = preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn ($m) => chr(hexdec($m[1])), $b) ?? $b;
            foreach (self::PDF_PELIGROSOS as $tok) {
                if (preg_match('#'.preg_quote($tok, '#').'(?![A-Za-z])#', $norm)) {
                    throw new \InvalidArgumentException('El PDF contiene contenido activo no permitido ('.$tok.').');
                }
            }

            return ['mime' => 'application/pdf', 'ext' => 'pdf'];
        }
        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return ['mime' => 'image/jpeg', 'ext' => 'jpg'];
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1A\n")) {
            return ['mime' => 'image/png', 'ext' => 'png'];
        }
        if (str_starts_with($head, "II*\x00") || str_starts_with($head, "MM\x00*")) {
            return ['mime' => 'image/tiff', 'ext' => 'tif'];
        }
        throw new \InvalidArgumentException('Formato no permitido. Solo PDF, JPG, PNG o TIFF.');
    }
}
