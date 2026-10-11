<?php

namespace App\Services\Interop\DocDigital;

use Illuminate\Http\Client\PendingRequest;
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
        if (! str_starts_with($u, 'https://') || ! $this->cfg['api_token']) {
            throw new DocDigitalException('DocDigital no está configurado (URL https y token).');
        }

        return rtrim($u, '/');
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->acceptJson()->withToken((string) $this->cfg['api_token']);
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
                'numero' => $meta['numero'], 'tipo_documento' => $meta['tipo'],
                'archivo' => ['nombre' => $nombre, 'content-type' => 'application/pdf', 'content' => base64_encode($pdf), 'checksum' => hash('sha256', $pdf)],
            ]);
        } catch (\Throwable) {
            throw new DocDigitalException('No fue posible contactar DocDigital.');
        }
        $id = $r->successful() ? ($r->json('id') ?? $r->json('folio') ?? $r->json('numero')) : null;
        if (! is_string($id) && ! is_int($id)) {
            throw new DocDigitalException('DocDigital rechazó el envío (HTTP '.$r->status().').');
        }

        return ['id' => (string) $id, 'simulado' => false];
    }

    public function recibidos(): array
    {
        $url = $this->base().$this->ruta('path_recibidos');
        try {
            $r = $this->http()->get($url);
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
