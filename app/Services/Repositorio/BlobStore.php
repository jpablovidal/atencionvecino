<?php

namespace App\Services\Repositorio;

/** Almacén de blobs YA CIFRADOS (el contenido nunca llega en claro al almacén). */
interface BlobStore
{
    /** @return string ruta opaca para recuperar el blob */
    public function put(string $blob): string;

    public function get(string $ruta): string;

    public function delete(string $ruta): void;
}
