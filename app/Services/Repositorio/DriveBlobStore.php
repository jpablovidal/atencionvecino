<?php

namespace App\Services\Repositorio;

use App\Models\RepositorioCarpeta;

/** Unidad compartida de Google Drive. Rutas "gd:<fileId>". Carpetas por año/mes (se crean bajo demanda y se recuerdan). */
class DriveBlobStore implements BlobStore
{
    public function __construct(private readonly DriveClient $drive, private readonly string $carpetaRaiz) {}

    public static function esRutaDrive(string $ruta): bool
    {
        return str_starts_with($ruta, 'gd:') && DriveClient::idValido(substr($ruta, 3));
    }

    public function put(string $blob): string
    {
        $r = $this->drive->subir(bin2hex(random_bytes(20)), $this->carpetaDelMes(), $blob); // nombre aleatorio

        return 'gd:'.$r['id'];
    }

    public function get(string $ruta): string
    {
        if (! self::esRutaDrive($ruta)) {
            throw new DriveException('Ruta de Drive inválida.');
        }

        return $this->drive->descargar(substr($ruta, 3));
    }

    public function delete(string $ruta): void
    {
        if (self::esRutaDrive($ruta)) {
            try {
                $this->drive->eliminar(substr($ruta, 3));
            } catch (DriveException) {
                // limpieza de mejor esfuerzo
            }
        }
    }

    private function carpetaDelMes(): string
    {
        $clave = now('America/Santiago')->format('Y/m');
        $c = RepositorioCarpeta::query()->where('clave', $clave)->first();
        if ($c) {
            return $c->drive_folder_id;
        }
        $anio = $this->drive->asegurarCarpeta(substr($clave, 0, 4), $this->carpetaRaiz);
        $mes = $this->drive->asegurarCarpeta(substr($clave, 5, 2), $anio);
        RepositorioCarpeta::query()->insertOrIgnore(['clave' => $clave, 'drive_folder_id' => $mes]);

        return $mes;
    }
}
