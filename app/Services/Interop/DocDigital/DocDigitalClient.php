<?php

namespace App\Services\Interop\DocDigital;

interface DocDigitalClient
{
    /**
     * Despacha un documento firmado (PDF con firma electrónica avanzada) a una entidad destinataria
     * (POST /documentos/firmado/ingresar de DocDigital API v3).
     *
     * @param  array{asunto:string,destino_codigo:string,destino_nombre?:?string,destino_entidad_id?:?int,origen_codigo:?string,numero:?string,tipo:string,tipo_id?:?int,reservado?:bool,referencia?:?string,anexos?:list<array{nombre:string,bytes:string}>}  $meta
     * @return array{id:string,simulado:bool,solicitud_id?:?string,anexos?:list<array{id:?string,nombre:string}>}
     *
     * @throws DocDigitalException
     */
    public function enviar(string $pdf, string $nombre, array $meta): array;

    /**
     * Acuse de recibo de un documento recibido (PUT /documentos/recibidos/{id}/acusorecibo).
     *
     * @return array{evidencia:string,simulado:bool}
     *
     * @throws DocDigitalException
     */
    public function acusarRecibo(string $id): array;

    /**
     * Devuelve/rechaza un documento recibido (PUT /documentos/recibidos/{id}/devolver).
     *
     * @return array{evidencia:string,simulado:bool}
     *
     * @throws DocDigitalException
     */
    public function devolver(string $id, string $motivo): array;

    /**
     * Documentos recibidos por la entidad asociada al token, pendientes de acuse (GET /documentos/recibidos).
     *
     * @return array<int,array{id:string,asunto:string,origen_codigo:?string,origen_nombre:?string,origen_entidad_id:?int,pdf:string,anexos:list<array{id:string,nombre:string,tipo:string}>}>
     *
     * @throws DocDigitalException
     */
    public function recibidos(): array;

    /**
     * Descarga un anexo de un documento recibido (GET /documentos/{id}/archivo/descargar?archivo_id=). Null si no se pudo bajar o excede 50 MB.
     *
     * @throws DocDigitalException
     */
    public function descargarAnexo(string $documentoId, string $anexoId): ?string;
}
