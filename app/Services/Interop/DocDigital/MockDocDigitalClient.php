<?php

namespace App\Services\Interop\DocDigital;

/** SOLO DESARROLLO. No envía nada: devuelve un identificador simulado (la UI lo rotula "SIMULADO"). */
class MockDocDigitalClient implements DocDigitalClient
{
    public function enviar(string $pdf, string $nombre, array $meta): array
    {
        return ['id' => 'SIM-'.bin2hex(random_bytes(6)), 'simulado' => true];
    }

    public function acusarRecibo(string $id): array
    {
        return ['evidencia' => 'SIM-ACUSE-'.bin2hex(random_bytes(4)), 'simulado' => true];
    }

    public function devolver(string $id, string $motivo): array
    {
        return ['evidencia' => 'SIM-DEVOLUCION-'.bin2hex(random_bytes(4)), 'simulado' => true];
    }

    public function recibidos(): array
    {
        return [];
    }
}
