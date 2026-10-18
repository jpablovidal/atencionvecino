<?php

namespace App\Services\Interop\DocDigital;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * API Middleware V2 de DocDigital (guía: https://wikiguias.digital.gob.cl/Manuales/integracion-api-docdigital;
 * documentación de métodos: https://api-doc.digital.gob.cl/api/defs/; ambiente de pruebas "DemoDoc").
 *
 * IMPORTANTE – CONTRATO A VALIDAR: no se pudo acceder a la especificación en esta construcción. Las rutas se
 * configuran por .env (DOCDIGITAL_PATH_*) y las formas JSON de abajo son una propuesta mínima que DEBE ajustarse a
 * la especificación oficial (y probarse en DemoDoc) antes de usarse. Si falta URL/ruta/token falla cerrado.
 * Requisitos de DocDigital (según la guía): solo PDF con Firma Electrónica Avanzada; Administrador Principal registrado.
 */
class HttpDocDigitalClient implements DocDigitalClient
{
    public function __construct(private readonly array $cfg, private readonly string $ambiente) {}

    private function base(): string
    {
        $u = (string) ($this->ambiente === 'prod' ? $this->cfg['url_prod'] : $this->cfg['url_demo']);
        if (! str_starts_with($u, 'https://') || ! $this->tieneCredenciales()) {
            throw new DocDigitalException('DocDigital no está configurado (URL https y credenciales).');
        }

        return rtrim($u, '/');
    }

    public function tieneCredenciales(): bool
    {
        return $this->usaOauth() || (bool) ($this->cfg['api_token'] ?? null);
    }

    private function usaOauth(): bool
    {
        return ! empty($this->cfg['client_id']) && ! empty($this->cfg['client_secret']) && str_starts_with((string) ($this->cfg['token_url'] ?? ''), 'https://');
    }

    /**
     * Token de acceso. OAuth2 «client credentials» (RFC 6749 §4.4, autenticación HTTP Basic del cliente; A VALIDAR con la especificación)
     * o token fijo. El token se cachea hasta poco antes de vencer y nunca se escribe en registros ni en mensajes de error.
     */
    public function token(): string
    {
        if (! $this->usaOauth()) {
            return (string) $this->cfg['api_token'];
        }
        $clave = 'docdigital_token_'.hash('sha256', $this->cfg['token_url'].'|'.$this->cfg['client_id'].'|'.$this->cfg['client_secret']);
        $t = Cache::get($clave);
        if (is_string($t) && $t !== '') {
            return $t;
        }
        try {
            $datos = ['grant_type' => 'client_credentials'] + (! empty($this->cfg['scope']) ? ['scope' => (string) $this->cfg['scope']] : []);
            $r = Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->acceptJson()
                ->withBasicAuth((string) $this->cfg['client_id'], (string) $this->cfg['client_secret'])->asForm()->post((string) $this->cfg['token_url'], $datos);
        } catch (\Throwable) {
            throw new DocDigitalException('No fue posible contactar el servicio de autorización de DocDigital.');
        }
        $tok = $r->successful() ? $r->json('access_token') : null;
        if (! is_string($tok) || $tok === '') {
            throw new DocDigitalException('DocDigital no entregó un token de acceso (HTTP '.$r->status().'): revise el Client ID, el Client Secret y la URL del token.');
        }
        $ttl = max(30, min(3600, (int) ($r->json('expires_in') ?: 300) - 60));
        Cache::put($clave, $tok, $ttl);

        return $tok;
    }

    /**
     * Diagnóstico sin datos: obtiene el token y consulta la ruta de recibidos solo para leer el código HTTP.
     *
     * @return array<int,array{0:string,1:bool,2:string}>
     */
    public function diagnosticar(): array
    {
        $out = [];
        $u = (string) ($this->ambiente === 'prod' ? $this->cfg['url_prod'] : $this->cfg['url_demo']);
        $out[] = ['URL base (https)', str_starts_with($u, 'https://'), str_starts_with($u, 'https://') ? (string) parse_url($u, PHP_URL_HOST) : 'sin definir o no es https'];
        $out[] = ['Credenciales', $this->tieneCredenciales(), $this->usaOauth() ? 'OAuth2 (client id + secret)' : ($this->cfg['api_token'] ? 'token fijo' : 'sin definir')];
        if (! str_starts_with($u, 'https://') || ! $this->tieneCredenciales()) {
            return $out;
        }
        try {
            $this->token();
            $out[] = ['Autorización (token)', true, 'token obtenido'];
        } catch (DocDigitalException $e) {
            $out[] = ['Autorización (token)', false, $e->getMessage()];

            return $out;
        }
        try {
            $r = $this->http()->get($this->base().$this->ruta('path_recibidos'));
            $out[] = ['Consulta de recibidos', $r->successful(), 'HTTP '.$r->status()];
        } catch (DocDigitalException $e) {
            $out[] = ['Consulta de recibidos', false, $e->getMessage()];
        } catch (\Throwable) {
            $out[] = ['Consulta de recibidos', false, 'sin respuesta de DocDigital'];
        }

        return $out;
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->acceptJson()->withToken($this->token());
    }

    private function ruta(string $k): string
    {
        $r = (string) $this->cfg[$k];
        if (! preg_match('#^/[A-Za-z0-9._~\-/]{1,150}$#', $r) || str_contains($r, '..')) {
            throw new DocDigitalException('Ruta de API DocDigital no configurada.');
        }

        return $r;
    }

    public function enviar(string $pdf, string $nombre, array $meta): array
    {
        if (strlen($pdf) > $this->cfg['max_bytes']) {
            throw new DocDigitalException('El documento excede el tamaño máximo.');
        }
        $url = $this->base().$this->ruta('path_enviar');
        try {
            $r = $this->http()->asJson()->post($url, [ // forma A VALIDAR
                'asunto' => $meta['asunto'], 'destinatarios' => [['codigo_institucion' => $meta['destino_codigo']]], 'origen' => $meta['origen_codigo'],
                'numero' => $meta['numero'], 'tipo_documento' => $meta['tipo'], 'referencia' => $meta['referencia'] ?? null,
                'archivo' => ['nombre' => $nombre, 'content-type' => 'application/pdf', 'content' => base64_encode($pdf), 'checksum' => hash('sha256', $pdf)],
            ]);
        } catch (DocDigitalException $e) {
            throw $e; // p. ej. falla de autorización: conserva su mensaje (sin secretos)
        } catch (\Throwable) {
            throw new DocDigitalException('No fue posible contactar DocDigital.');
        }
        $id = $r->successful() ? ($r->json('id') ?? $r->json('folio') ?? $r->json('numero')) : null;
        if (! is_string($id) && ! is_int($id)) {
            throw new DocDigitalException('DocDigital rechazó el envío (HTTP '.$r->status().').');
        }

        return ['id' => (string) $id, 'simulado' => false];
    }

    public function acusarRecibo(string $id): array
    {
        $url = $this->base().$this->ruta('path_acuse');
        try {
            $r = $this->http()->asJson()->post($url, ['id' => $id]); // forma A VALIDAR
        } catch (DocDigitalException $e) {
            throw $e; // p. ej. falla de autorización: conserva su mensaje (sin secretos)
        } catch (\Throwable) {
            throw new DocDigitalException('No fue posible contactar DocDigital.');
        }
        if (! $r->successful()) {
            throw new DocDigitalException('DocDigital rechazó el acuse de recibo (HTTP '.$r->status().').');
        }
        $ev = $r->json('id') ?? $r->json('folio') ?? $r->json('acuse');

        return ['evidencia' => is_scalar($ev) && (string) $ev !== '' ? mb_substr((string) $ev, 0, 120) : 'acuse-'.$id, 'simulado' => false];
    }

    public function recibidos(): array
    {
        $url = $this->base().$this->ruta('path_recibidos');
        try {
            $r = $this->http()->get($url);
        } catch (DocDigitalException $e) {
            throw $e; // p. ej. falla de autorización: conserva su mensaje (sin secretos)
        } catch (\Throwable) {
            throw new DocDigitalException('No fue posible contactar DocDigital.');
        }
        $lista = $r->successful() ? $r->json('documentos', $r->json()) : null; // forma A VALIDAR
        if (! is_array($lista)) {
            throw new DocDigitalException('Respuesta inválida de DocDigital.');
        }
        $out = [];
        foreach ($lista as $d) {
            $pdf = is_array($d) && isset($d['archivo']['content']) ? base64_decode((string) $d['archivo']['content'], true) : false;
            if (is_array($d) && isset($d['id'], $d['asunto']) && $pdf !== false) {
                $out[] = ['id' => (string) $d['id'], 'asunto' => (string) $d['asunto'], 'origen_codigo' => $d['origen']['codigo'] ?? null, 'origen_nombre' => $d['origen']['nombre'] ?? null, 'pdf' => $pdf];
            }
        }

        return $out;
    }
}
