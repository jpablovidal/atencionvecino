<?php

namespace App\Services\Firma;

use App\Support\Run;
use Illuminate\Support\Facades\Http;

/**
 * FirmaGob API v2 (firma atendida con OTP). Contrato A VALIDAR contra la documentación vigente
 * (https://firma.digital.gob.cl) con credenciales del ambiente de pruebas antes de pasar a producción.
 */
class HttpFirmaClient implements FirmaClient
{
    public function __construct(private readonly array $cfg, private readonly string $ambiente) {}

    public function firmar(string $pdf, string $run, ?string $otp, string $descripcion): array
    {
        if ($otp === null || ! preg_match('/^\d{6}$/', $otp)) {
            throw new FirmaException('Debe ingresar el código OTP de 6 dígitos.');
        }
        if (strlen($pdf) > $this->cfg['max_bytes']) {
            throw new FirmaException('El documento excede el máximo de 5 MB para firma.');
        }
        $checksum = hash('sha256', $pdf);
        $jwt = $this->jwt(Run::sinDv($run));
        $body = [
            'api_token_key' => $this->cfg['api_token_key'],
            'token' => $jwt,
            'files' => [[
                'content-type' => 'application/pdf',
                'content' => base64_encode($pdf),
                'description' => mb_substr($descripcion, 0, 100),
                'checksum' => $checksum,
            ]],
        ];
        try {
            $resp = Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()
                ->withHeaders(['OTP' => $otp])->acceptJson()->asJson()
                ->post($this->cfg['urls'][$this->ambiente], $body);
        } catch (\Throwable) {
            throw new FirmaException('No fue posible comunicarse con FirmaGob. Reintente más tarde.');
        }
        if (! $resp->successful()) {
            throw new FirmaException('FirmaGob rechazó la solicitud de firma (código '.$resp->status().').');
        }
        $f = $resp->json('files.0');
        $contenido = is_array($f) && isset($f['content']) ? base64_decode((string) $f['content'], true) : false;
        if ($contenido === false || ! str_starts_with($contenido, '%PDF-')) {
            throw new FirmaException('Respuesta de FirmaGob inválida.');
        }
        $original = (string) ($f['checksum_original'] ?? '');
        if (! hash_equals($checksum, strtolower($original))) {
            throw new FirmaException('FirmaGob devolvió un checksum que no coincide con el documento enviado.');
        }

        return ['pdf' => $contenido, 'checksum_original' => $original, 'simulada' => false];
    }

    private function jwt(string $runSinDv): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $exp = now('America/Santiago')->addMinutes(20)->format('Y-m-d\TH:i:s'); // ≤ 30 min, hora de Chile
        $h = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = $b64(json_encode(['entity' => $this->cfg['entity'], 'run' => $runSinDv, 'expiration' => $exp, 'purpose' => $this->cfg['purpose']], JSON_UNESCAPED_UNICODE));

        return "$h.$p.".$b64(hash_hmac('sha256', "$h.$p", (string) $this->cfg['secret'], true));
    }
}
