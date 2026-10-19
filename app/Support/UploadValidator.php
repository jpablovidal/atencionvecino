<?php

namespace App\Support;

use App\Services\Admin\ArchivoConfigService;
use Illuminate\Http\UploadedFile;

/**
 * Valida subidas por CONTENIDO (firma binaria y estructura), no por extensión ni Content-Type declarado.
 * Los formatos permitidos y los pesos máximos los define el administrador (ArchivoConfigService); PDF siempre está permitido.
 */
class UploadValidator
{
    private const PDF_PELIGROSOS = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/RichMedia', '/OpenAction', '/AA'];

    /** Máximo de entradas y de bytes descomprimidos en un contenedor ZIP (documentos ofimáticos), contra «bombas» de compresión. */
    private const ZIP_MAX_ENTRADAS = 3000;

    private const ZIP_MAX_BYTES = 209715200;

    /** @return array{mime:string,ext:string} */
    public static function validar(UploadedFile $f, string $ambito = 'funcionario'): array
    {
        $max = ArchivoConfigService::maxBytes($ambito);
        if (! $f->isValid() || $f->getSize() === false || $f->getSize() === 0 || $f->getSize() > $max) {
            throw new \InvalidArgumentException('Archivo inválido o excede el tamaño máximo ('.self::mb($max).' MB).');
        }
        $bytes = (string) file_get_contents($f->getRealPath());

        return self::validarBytes($bytes);
    }

    private static function mb(int $b): string
    {
        $v = $b / 1048576;

        return rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
    }

    /** Lista legible de formatos permitidos, p. ej. «PDF, JPG, PNG o TIFF». */
    public static function descripcionFormatos(): string
    {
        $e = [];
        foreach (ArchivoConfigService::FORMATOS as $k => $f) {
            if (ArchivoConfigService::formatoHabilitado($k)) {
                $e[] = strtoupper($f['ext']);
            }
        }
        $ultimo = array_pop($e);

        return $e ? implode(', ', $e).' o '.$ultimo : (string) $ultimo;
    }

    /** @return array{mime:string,ext:string} */
    public static function validarBytes(string $b): array
    {
        $tipo = self::detectar($b);
        $clave = $tipo['ext'] === 'jpg' ? 'jpg' : $tipo['ext'];
        if (! ArchivoConfigService::formatoHabilitado($clave)) {
            throw new \InvalidArgumentException('El formato '.strtoupper($tipo['ext']).' no está habilitado. Formatos permitidos: '.self::descripcionFormatos().'.');
        }

        return $tipo;
    }

    /** @return array{mime:string,ext:string} */
    private static function detectar(string $b): array
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
        if (str_starts_with($head, "PK\x03\x04")) {
            return self::zipOfimatico($b);
        }
        $t = self::texto($b);
        if ($t !== null) {
            return $t;
        }
        throw new \InvalidArgumentException('Formato no permitido. Formatos permitidos: '.self::descripcionFormatos().'.');
    }

    /** DOCX/XLSX/PPTX (OOXML) y ODT/ODS (OpenDocument): estructura reconocida, sin macros ni objetos incrustados. */
    private static function zipOfimatico(string $b): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \InvalidArgumentException('Formato no permitido.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'sgdzip');
        file_put_contents($tmp, $b);
        try {
            $z = new \ZipArchive;
            if ($z->open($tmp, \ZipArchive::RDONLY) !== true) {
                throw new \InvalidArgumentException('Documento corrupto o no reconocido.');
            }
            if ($z->numFiles > self::ZIP_MAX_ENTRADAS) {
                throw new \InvalidArgumentException('Documento con demasiadas partes internas.');
            }
            $nombres = [];
            $total = 0;
            for ($i = 0; $i < $z->numFiles; $i++) {
                $st = $z->statIndex($i);
                $n = (string) $st['name'];
                if (str_contains($n, '..') || str_starts_with($n, '/') || str_contains($n, '\\')) {
                    throw new \InvalidArgumentException('Documento con rutas internas no permitidas.');
                }
                $total += (int) $st['size'];
                $nombres[strtolower($n)] = $i;
            }
            if ($total > self::ZIP_MAX_BYTES) {
                throw new \InvalidArgumentException('Documento con contenido descomprimido excesivo.');
            }
            foreach ($nombres as $n => $_) {
                if (str_contains($n, 'vbaproject') || str_contains($n, '/embeddings/') || str_ends_with($n, '.bin') && str_contains($n, 'vba') || str_starts_with($n, 'basic/') || str_starts_with($n, 'scripts/') || str_contains($n, 'activex')) {
                    throw new \InvalidArgumentException('El documento contiene macros u objetos incrustados no permitidos.');
                }
            }
            $mapa = ['word/document.xml' => 'docx', 'xl/workbook.xml' => 'xlsx', 'ppt/presentation.xml' => 'pptx'];
            if (isset($nombres['[content_types].xml'])) {
                foreach ($mapa as $parte => $ext) {
                    if (isset($nombres[$parte])) {
                        return ['mime' => ArchivoConfigService::FORMATOS[$ext]['mime'], 'ext' => $ext];
                    }
                }
            }
            if (isset($nombres['mimetype'])) {
                $m = trim((string) $z->getFromName('mimetype', 200));
                foreach (['odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet'] as $ext => $mime) {
                    if ($m === $mime) {
                        return ['mime' => $mime, 'ext' => $ext];
                    }
                }
            }
            throw new \InvalidArgumentException('Formato no permitido. Formatos permitidos: '.self::descripcionFormatos().'.');
        } finally {
            @unlink($tmp);
        }
    }

    /** TXT o CSV: texto sin bytes de control ni apariencia de script/marcado. */
    private static function texto(string $b): ?array
    {
        if ($b === '' || str_contains($b, "\0") || preg_match('/[\x01-\x08\x0B\x0C\x0E-\x1F]/', substr($b, 0, 65536))) {
            return null;
        }
        if (! mb_check_encoding($b, 'UTF-8') && ! mb_check_encoding(mb_convert_encoding($b, 'UTF-8', 'ISO-8859-1'), 'UTF-8')) {
            return null;
        }
        if (preg_match('/^\s*(<\?|<script|<!doctype|<html|#!|<svg|<\w+[\s>])/i', substr($b, 0, 2048))) {
            throw new \InvalidArgumentException('El texto contiene marcado o código no permitido.');
        }
        $primera = (string) strtok(substr($b, 0, 4096), "\n");
        $csv = substr_count($primera, ',') >= 1 || substr_count($primera, ';') >= 1 || substr_count($primera, "\t") >= 1;

        return $csv ? ['mime' => 'text/csv', 'ext' => 'csv'] : ['mime' => 'text/plain', 'ext' => 'txt'];
    }
}
