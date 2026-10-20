<?php

namespace App\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transporte Gmail API (scope mínimo gmail.send) con cuenta de servicio + delegación de todo el dominio.
 * Suplanta SOLO el buzón configurado en GOOGLE_MAIL_SENDER. La clave JSON no se registra en logs.
 */
class GmailApiTransport extends AbstractTransport
{
    public function __construct(private readonly array $cfg)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Message) {
            throw new TransportException('Mensaje no soportado.');
        }
        $email = MessageConverter::toEmail($original);
        $this->validar($email);
        $email->from($this->cfg['sender']);
        $raw = $email->toString();
        $b64 = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        try {
            $resp = Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()
                ->withToken($this->accessToken())->acceptJson()->asJson()->post($this->cfg['send_url'], ['raw' => $b64]);
        } catch (\Throwable) {
            throw new TransportException('No fue posible contactar la Gmail API.');
        }
        if ($resp->status() === 401) {
            Cache::forget('gmail_access_token');
        }
        $id = $resp->successful() ? $resp->json('id') : null;
        if (! is_string($id) || $id === '') {
            throw new TransportException('Gmail API rechazó el envío (HTTP '.$resp->status().').');
        }
        $original->getHeaders()->addTextHeader('X-Gmail-Api-Id', $id);
    }

    /** Rechaza inyección de cabeceras: CR/LF en asunto o direcciones. */
    private function validar(Email $e): void
    {
        $subject = (string) $e->getSubject();
        if (preg_match('/[\r\n]/', $subject)) {
            throw new TransportException('Asunto inválido.');
        }
        foreach (array_merge($e->getTo(), $e->getCc(), $e->getBcc()) as $a) {
            $dir = $a->getAddress();
            if (preg_match('/[\r\n,;<>]/', $dir) || ! filter_var($dir, FILTER_VALIDATE_EMAIL)) {
                throw new TransportException('Destinatario inválido.');
            }
        }
        if (! $e->getTo()) {
            throw new TransportException('Sin destinatario.');
        }
    }

    /** Pide un token a Google con la cuenta de servicio y la delegación de dominio, sin enviar nada. @return array{0:bool,1:string} */
    public function probarToken(): array
    {
        try {
            Cache::forget('gmail_access_token');
            $this->accessToken();
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }

        return [true, 'Google entregó un token: la cuenta de servicio y la delegación de dominio funcionan para '.$this->cfg['sender'].'.'];
    }

    private function accessToken(): string
    {
        return Cache::remember('gmail_access_token', 3000, function () {
            $path = (string) $this->cfg['service_account_json'];
            $sa = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            if (! is_array($sa) || empty($sa['private_key']) || empty($sa['client_email'])) {
                throw new TransportException('Credenciales de la cuenta de servicio no disponibles.');
            }
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $h = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $p = $b64(json_encode(['iss' => $sa['client_email'], 'sub' => $this->cfg['sender'], 'scope' => $this->cfg['scope'], 'aud' => $this->cfg['token_url'], 'iat' => $now, 'exp' => $now + 3000]));
            if (! openssl_sign("$h.$p", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new TransportException('No se pudo firmar la aserción JWT.');
            }
            try {
                $r = Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->asForm()
                    ->post($this->cfg['token_url'], ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$h.$p.".$b64($sig)]);
            } catch (\Throwable) {
                throw new TransportException('No fue posible obtener el token de Google.');
            }
            $t = $r->successful() ? $r->json('access_token') : null;
            if (! is_string($t) || $t === '') {
                throw new TransportException('Google rechazó la delegación de dominio (HTTP '.$r->status().').');
            }

            return $t;
        });
    }

    public function __toString(): string
    {
        return 'gmail_api';
    }
}
