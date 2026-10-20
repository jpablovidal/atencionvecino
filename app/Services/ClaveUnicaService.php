<?php

namespace App\Services;

use App\Support\Run;
use Illuminate\Support\Facades\Http;

/** OIDC ClaveÚnica: authorize -> token -> userinfo. Endpoints fijos, sin redirecciones, timeouts explícitos. */
class ClaveUnicaService
{
    /** @var array<string,mixed> */
    private readonly array $cfg;

    /**
     * Las credenciales del ambiente activo (sandbox, qa o produccion) reemplazan a las generales cuando están definidas;
     * sin ellas se usan las generales (instalaciones anteriores).
     *
     * @param  array<string,mixed>  $cfg
     */
    public function __construct(array $cfg)
    {
        $amb = in_array($cfg['ambiente'] ?? null, ['sandbox', 'qa', 'produccion'], true) ? $cfg['ambiente'] : 'produccion';
        foreach (['client_id', 'client_secret', 'redirect_uri', 'logout_uri'] as $k) {
            if (! empty($cfg[$amb.'_'.$k])) {
                $cfg[$k] = $cfg[$amb.'_'.$k];
            }
        }
        if (! empty($cfg[$amb.'_client_id']) && empty($cfg[$amb.'_client_secret'])) {
            $cfg['client_secret'] = null; // nunca mezclar el client_id de un ambiente con el secreto de otro
        }
        $this->cfg = $cfg;
    }

    public function urlAutorizacion(string $state): string
    {
        return $this->cfg['authorize_url'].'?'.http_build_query([
            'client_id' => $this->cfg['client_id'], 'redirect_uri' => $this->cfg['redirect_uri'], 'scope' => $this->cfg['scope'],
            'response_type' => 'code', 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Cierre de sesión de ClaveÚnica (GET a pantalla completa, nunca en iframe ni popup: así lo exige el manual). El parámetro
     * «redirect» solo funciona si la Logout URI está registrada ante ClaveÚnica; por defecto se usa la portada del sitio.
     */
    public function urlCierre(): string
    {
        $uri = (string) ($this->cfg['logout_uri'] ?? '') ?: rtrim((string) config('app.url'), '/').'/';

        return ($this->cfg['logout_url'] ?? 'https://accounts.claveunica.gob.cl/api/v1/accounts/app/logout').'?'.http_build_query(['redirect' => $uri], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Comprueba con ClaveÚnica que el client_id/client_secret del ambiente activo son aceptados, SIN iniciar sesión de ninguna persona:
     * se pide el token con un código inexistente; si las credenciales son válidas el servicio responde «invalid_grant», y si no, «invalid_client».
     * (Criterio A VALIDAR contra el servicio real.)
     *
     * @return array{0:bool,1:string}
     */
    public function verificarCredenciales(): array
    {
        if (empty($this->cfg['client_id']) || empty($this->cfg['client_secret']) || empty($this->cfg['redirect_uri'])) {
            return [false, 'Faltan el client_id, el client_secret o la Redirect URI del ambiente activo.'];
        }
        try {
            $r = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()->asForm()->post($this->cfg['token_url'], [
                'client_id' => $this->cfg['client_id'], 'client_secret' => $this->cfg['client_secret'], 'redirect_uri' => $this->cfg['redirect_uri'],
                'grant_type' => 'authorization_code', 'code' => 'verificacion-de-credenciales', 'state' => 'verificacion',
            ]);
        } catch (\Throwable) {
            return [false, 'No fue posible contactar a ClaveÚnica (accounts.claveunica.gob.cl).'];
        }
        $err = (string) $r->json('error', '');
        if ($err === 'invalid_grant') {
            return [true, 'ClaveÚnica aceptó las credenciales (respondió «invalid_grant» porque el código de verificación no es real; es lo esperado).'];
        }
        if (in_array($err, ['invalid_client', 'unauthorized_client'], true) || $r->status() === 401) {
            $desc = mb_substr(trim(strip_tags((string) $r->json('error_description', ''))), 0, 120);

            return [false, 'ClaveÚnica rechazó el client_id o el client_secret ('.($err !== '' ? $err : 'HTTP '.$r->status()).($desc !== '' ? ': '.$desc : '').'). Revise que sean los del ambiente elegido y que no tengan espacios.'];
        }

        return [false, 'Respuesta inesperada de ClaveÚnica (HTTP '.$r->status().($err !== '' ? ', '.mb_substr($err, 0, 60) : '').'): revise las credenciales y la Redirect URI.'];
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
