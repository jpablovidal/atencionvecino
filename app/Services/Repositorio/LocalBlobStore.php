<?php

namespace App\Services\Repositorio;

use RuntimeException;

/** Disco local FUERA del docroot (SGD_STORAGE_PATH). Rutas "ab/<38 hex>". */
class LocalBlobStore implements BlobStore
{
    public const PATRON = '#^[0-9a-f]{2}/[0-9a-f]{38}$#';

    public function __construct(private readonly string $root) {}

    public static function esRutaLocal(string $ruta): bool
    {
        return (bool) preg_match(self::PATRON, $ruta);
    }

    public function put(string $blob): string
    {
        $name = bin2hex(random_bytes(20)); // nombre aleatorio: no revela nada del contenido
        $dir = $this->root.'/'.substr($name, 0, 2);
        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de almacenamiento.');
        }
        $rel = substr($name, 0, 2).'/'.substr($name, 2);
        if (file_put_contents($this->root.'/'.$rel, $blob, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el archivo.');
        }
        @chmod($this->root.'/'.$rel, 0640);

        return $rel;
    }

    public function get(string $ruta): string
    {
        if (! self::esRutaLocal($ruta)) {
            throw new RuntimeException('Ruta de archivo inválida.'); // sin path traversal
        }
        $b = @file_get_contents($this->root.'/'.$ruta);
        if ($b === false) {
            throw new RuntimeException('Archivo no disponible.');
        }

        return $b;
    }

    public function delete(string $ruta): void
    {
        if (self::esRutaLocal($ruta)) {
            @unlink($this->root.'/'.$ruta);
        }
    }
}
