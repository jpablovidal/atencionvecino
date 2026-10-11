<?php

namespace App\Services\Interop\DocDigital;

interface DocDigitalClient
{
    /**
     * Envía un documento (PDF con firma electrónica avanzada) a una institución destinataria.
     *
     * @param  array{asunto:string,destino_codigo:string,origen_codigo:?string,numero:?string,tipo:string,referencia?:?string}  $meta
     * @return array{id:string,simulado:bool}
     *
     * @throws DocDigitalException
     */
    public function enviar(string $pdf, string $nombre, array $meta): array;

    /**
     * Acuse de recibo de una comunicación recibida (contrato A VALIDAR).
     *
     * @return array{evidencia:string,simulado:bool}
     *
     * @throws DocDigitalException
     */
    public function acusarRecibo(string $id): array;

    /**
     * Bandeja de comunicaciones recibidas.
     *
     * @return array<int,array{id:string,asunto:string,origen_codigo:?string,origen_nombre:?string,pdf:string}>
     *
     * @throws DocDigitalException
     */
    public function recibidos(): array;
}
