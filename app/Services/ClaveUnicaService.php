<?php

namespace App\Services;

use App\Support\Run;
use Illuminate\Support\Facades\Http;

/** OIDC ClaveÚnica: authorize -> token -> userinfo. Endpoints fijos, sin redirecciones, timeouts explícitos. */
class ClaveUnicaService
{
    public function __construct(private readonly array $cfg) {}

    public function urlAutorizacion(string $state): string
    {
        return $this->cfg['authorize_url'].'?'.http_build_query([
            'client_id' => $this->cfg['client_id'], 'redirect_uri' => $this->cfg['redirect_uri'], 'scope' => $this->cfg['scope'],
            'response_type' => 'code', 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{run:string,nombres:string,apellidos:string} */
    public function identificar(string $code, string $state): array
    {
        $t = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()->asForm()->post($this->cfg['token_url'], [
            'client_id' => $this->cfg['client_id'], 'client_secret' => $this->cfg['client_secret'], 'redirect_uri' => $this->cfg['redirect_uri'],
            'grant_type' => 'authorization_code', 'code' => $code, 'state' => $state,
        ]);
        $at = $t->successful() ? $t->json('access_token') : null;
        if (! is_string($at) || $at === '') {
            throw new \RuntimeException('ClaveÚnica no entregó token.');
        }
        $u = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()->withToken($at)->post($this->cfg['userinfo_url']);
        if (! $u->successful()) {
            throw new \RuntimeException('ClaveÚnica no entregó datos.');
        }
        $j = $u->json();
        $run = Run::normalizar(($j['RolUnico']['numero'] ?? '').($j['RolUnico']['DV'] ?? ''));
        if (! $run) {
            throw new \RuntimeException('RUN inválido en respuesta de ClaveÚnica.');
        }

        return [
            'run' => $run,
            'nombres' => mb_substr(trim(implode(' ', (array) ($j['name']['nombres'] ?? []))), 0, 120),
            'apellidos' => mb_substr(trim(implode(' ', (array) ($j['name']['apellidos'] ?? []))), 0, 120),
        ];
    }
}
