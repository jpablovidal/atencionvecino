<?php

namespace App\Services\Casilla;

use App\Models\Notificacion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Adaptador de Casilla Única / Notificador. Modo "outbox" (defecto): NO envía nada; la notificación queda
 * "pendiente" hasta que un funcionario registre la evidencia real. Modo "api": el contrato (URL, autenticación y JSON)
 * es A VALIDAR con la guía de integración; la autenticación admite token fijo u OAuth2 «client credentials».
 */
class CasillaAdapter
{
    public function __construct(private readonly array $cfg) {}

    public function modoApi(): bool
    {
        return ($this->cfg['mode'] ?? 'outbox') === 'api';
    }

    public function usaOauth(): bool
    {
        return ! empty($this->cfg['client_id']) && ! empty($this->cfg['client_secret']) && str_starts_with((string) ($this->cfg['token_url'] ?? ''), 'https://');
    }

    public function tieneCredenciales(): bool
    {
        return $this->usaOauth() || ! empty($this->cfg['api_token']);
    }

    /** Token fijo u OAuth2 (HTTP Basic del cliente, A VALIDAR); cacheado hasta poco antes de vencer, nunca registrado. */
    public function token(): string
    {
        if (! $this->usaOauth()) {
            return (string) ($this->cfg['api_token'] ?? '');
        }
        $clave = 'casilla_token_'.hash('sha256', $this->cfg['token_url'].'|'.$this->cfg['client_id'].'|'.$this->cfg['client_secret']);
        $t = Cache::get($clave);
        if (is_string($t) && $t !== '') {
            return $t;
        }
        try {
            $datos = ['grant_type' => 'client_credentials'] + (! empty($this->cfg['scope']) ? ['scope' => (string) $this->cfg['scope']] : []);
            $r = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()->acceptJson()
                ->withBasicAuth((string) $this->cfg['client_id'], (string) $this->cfg['client_secret'])->asForm()->post((string) $this->cfg['token_url'], $datos);
        } catch (\Throwable) {
            throw new \RuntimeException('No fue posible contactar el servicio de autorización de Casilla Única.');
        }
        $tok = $r->successful() ? $r->json('access_token') : null;
        if (! is_string($tok) || $tok === '') {
            throw new \RuntimeException('Casilla Única no entregó un token de acceso (HTTP '.$r->status().'): revise el Client ID, el Client Secret y la URL del token.');
        }
        Cache::put($clave, $tok, max(30, min(3600, (int) ($r->json('expires_in') ?: 300) - 60)));

        return $tok;
    }

    /** @return array{evidencia_tipo:string,evidencia:string}|null null = queda pendiente (outbox) */
    public function enviar(Notificacion $n): ?array
    {
        if (! $this->modoApi()) {
            return null;
        }
        if (! str_starts_with((string) ($this->cfg['api_url'] ?? ''), 'https://')) {
            throw new \RuntimeException('La URL de Casilla Única debe ser https.');
        }
        $resp = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()->withToken($this->token())
            ->post((string) $this->cfg['api_url'], ['run' => $n->destinatario_run, 'folio' => $n->solicitud_id, 'documento' => $n->documento_id]);
        $id = $resp->successful() ? $resp->json('id') : null;
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('Casilla Única no confirmó el envío.');
        }

        return ['evidencia_tipo' => 'id_casilla_unica', 'evidencia' => $id];
    }

    /**
     * Diagnóstico sin enviar notificaciones: valida la configuración y, si hay credenciales, solo obtiene el token.
     *
     * @return array<int,array{0:string,1:bool,2:string}>
     */
    public function diagnosticar(): array
    {
        $out = [['Modo de conexión', $this->modoApi(), $this->modoApi() ? 'API' : 'Outbox (registro manual de evidencia)']];
        $u = (string) ($this->cfg['api_url'] ?? '');
        $out[] = ['URL de la API (https)', str_starts_with($u, 'https://'), str_starts_with($u, 'https://') ? (string) parse_url($u, PHP_URL_HOST) : 'sin definir o no es https'];
        $out[] = ['Credenciales', $this->tieneCredenciales(), $this->usaOauth() ? 'OAuth2 (client id + secret)' : (! empty($this->cfg['api_token']) ? 'token fijo' : 'sin definir')];
        if ($this->usaOauth()) {
            try {
                $this->token();
                $out[] = ['Token de acceso', true, 'obtenido'];
            } catch (\Throwable $e) {
                $out[] = ['Token de acceso', false, $e->getMessage()];
            }
        }

        return $out;
    }
}
