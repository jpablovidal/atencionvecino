<?php

namespace App\Services\Casilla;

use App\Models\Notificacion;
use Illuminate\Support\Facades\Http;

/**
 * Adaptador de Casilla Única / Notificador. Modo "outbox" (defecto): NO envía nada; la notificación queda
 * "pendiente" hasta que un funcionario registre la evidencia real. Modo "api": contrato A VALIDAR.
 */
class CasillaAdapter
{
    public function __construct(private readonly array $cfg) {}

    /** @return array{evidencia_tipo:string,evidencia:string}|null null = queda pendiente (outbox) */
    public function enviar(Notificacion $n): ?array
    {
        if (($this->cfg['mode'] ?? 'outbox') !== 'api') {
            return null;
        }
        $resp = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()->withToken((string) $this->cfg['api_token'])
            ->post((string) $this->cfg['api_url'], ['run' => $n->destinatario_run, 'folio' => $n->solicitud_id, 'documento' => $n->documento_id]);
        $id = $resp->successful() ? $resp->json('id') : null;
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('Casilla Única no confirmó el envío.');
        }

        return ['evidencia_tipo' => 'id_casilla_unica', 'evidencia' => $id];
    }
}
