<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Mail\GmailApiTransport;
use App\Models\ApiConfig;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\ClaveUnicaService;
use App\Services\Directorio\DirectorioFuncionarios;
use App\Services\Interop\CodificadorService;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use App\Services\Repositorio\DriveClient;
use App\Services\Repositorio\RepositorioService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Configuración de integraciones en BD con precedencia sobre .env (por servicio, si está "activo").
 * Los valores NO secretos se guardan en claro; los secretos solo se guardan (cifrados con APP_KEY) si
 * SGD_API_SECRETOS_EN_BD=1; nunca se muestran de vuelta. El archivo JSON de las cuentas de servicio sigue siendo una RUTA.
 */
class ApiConfigService
{
    private const CACHE = 'sgd_api_config_v1';

    public function __construct(private readonly AuditoriaService $audit) {}

    public static function secretosEnBd(): bool
    {
        return (bool) config('sgd.api_secretos_en_bd');
    }

    /** Aplica la configuración de BD sobre config(). Tolera BD sin migrar. */
    public static function aplicar(): void
    {
        try {
            $todo = Cache::remember(self::CACHE, 300, fn () => ApiConfig::query()->where('activo', true)->get(['servicio', 'valores', 'secretos'])->map(fn ($c) => [$c->servicio, (string) $c->valores, $c->secretos])->all());
        } catch (\Throwable) {
            return;
        }
        $cat = ApiCatalogo::servicios();
        foreach ($todo as [$servicio, $valores, $secretos]) {
            if (! isset($cat[$servicio])) {
                continue;
            }
            $v = (array) json_decode($valores, true);
            if ($secretos && self::secretosEnBd()) {
                try {
                    $v += (array) json_decode(Crypt::decryptString($secretos), true);
                } catch (\Throwable) {
                    // clave rotada o dato corrupto: se ignoran los secretos de BD
                }
            }
            foreach ($cat[$servicio]['campos'] as $clave => $def) {
                if (array_key_exists($clave, $v)) {
                    config([$def[2] => $def[1] === 'bool' ? (bool) $v[$clave] : ($def[1] === 'number' ? (int) $v[$clave] : $v[$clave])]);
                }
            }
        }
    }

    /** @return array{activo:bool,valores:array<string,mixed>,secretos_bd:array<int,string>} */
    public function estado(string $servicio): array
    {
        $c = ApiConfig::query()->where('servicio', $servicio)->first();
        $sec = [];
        if ($c && $c->secretos && self::secretosEnBd()) {
            try {
                $sec = array_keys((array) json_decode(Crypt::decryptString($c->secretos), true));
            } catch (\Throwable) {
            }
        }

        return ['existe' => $c !== null, 'activo' => (bool) $c?->activo, 'valores' => (array) json_decode((string) $c?->valores, true), 'secretos_bd' => $sec];
    }

    /** Valor efectivo actual (BD si aplica, si no .env) de los campos NO secretos; para los secretos solo indica el origen. */
    public function efectivo(string $servicio): array
    {
        $out = [];
        foreach (ApiCatalogo::servicios()[$servicio]['campos'] as $clave => $def) {
            $secreto = (bool) ($def[3]['secreto'] ?? false);
            $val = config($def[2]);
            $out[$clave] = ['secreto' => $secreto, 'valor' => $secreto ? null : $val, 'definido' => $val !== null && $val !== '' && $val !== false];
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $datos  campos no secretos y, si procede, secretos
     * @param  array<int,string>  $borrar  secretos de BD a eliminar
     */
    public function guardar(string $servicio, array $datos, bool $activo, array $borrar, Usuario $u): void
    {
        $cat = ApiCatalogo::servicios()[$servicio] ?? throw new FlujoException('Servicio desconocido.');
        $actual = ApiConfig::query()->where('servicio', $servicio)->first();
        $valores = [];
        $secretos = [];
        if ($actual && $actual->secretos && self::secretosEnBd()) {
            try {
                $secretos = (array) json_decode(Crypt::decryptString($actual->secretos), true);
            } catch (\Throwable) {
            }
        }
        $tocados = [];
        foreach ($cat['campos'] as $clave => $def) {
            [$etiqueta, $tipo, , $op] = $def + [3 => []];
            $v = $datos[$clave] ?? null;
            if ($op['secreto'] ?? false) {
                if (in_array($clave, $borrar, true)) {
                    unset($secretos[$clave]);
                    $tocados[] = $clave.' (borrado)';
                } elseif ($v !== null && $v !== '') {
                    if (! self::secretosEnBd()) {
                        throw new FlujoException("«{$etiqueta}» es un secreto: defínalo en el archivo .env (el almacenamiento de secretos en BD está desactivado: SGD_API_SECRETOS_EN_BD).");
                    }
                    $secretos[$clave] = $this->validar($etiqueta, 'text', (string) $v, $op);
                    $tocados[] = $clave.' (secreto)';
                }

                continue;
            }
            if ($tipo === 'bool') {
                $valores[$clave] = (bool) $v;
            } elseif ($v === null || $v === '') {
                continue; // vacío: se usa el valor de .env
            } else {
                $valores[$clave] = $this->validar($etiqueta, $tipo, (string) $v, $op);
            }
            $tocados[] = $clave;
        }
        $this->validarCoherencia($servicio, $valores);
        ApiConfig::query()->updateOrCreate(['servicio' => $servicio], [
            'activo' => $activo, 'valores' => (string) json_encode($valores), 'actualizado_por' => $u->id,
            'secretos' => $secretos ? Crypt::encryptString((string) json_encode($secretos)) : null,
        ]);
        Cache::forget(self::CACHE);
        $this->audit->registrar('api.configurar', 'api_config', $servicio, ['campos' => $tocados, 'activo' => $activo]);
    }

    public function volverAEnv(string $servicio, Usuario $u): void
    {
        ApiConfig::query()->where('servicio', $servicio)->update(['activo' => false]);
        Cache::forget(self::CACHE);
        $this->audit->registrar('api.volver_env', 'api_config', $servicio);
    }

    /** @param array<string,mixed> $op */
    private function validar(string $etiqueta, string $tipo, string $v, array $op): string
    {
        $v = trim($v);
        if (mb_strlen($v) > 500 || preg_match('/[\r\n\0]/', $v)) {
            throw new FlujoException("«{$etiqueta}»: valor inválido (máx. 500 caracteres, una línea).");
        }

        return match ($tipo) {
            'select' => array_key_exists($v, $op['opciones'] ?? []) ? $v : throw new FlujoException("«{$etiqueta}»: opción no permitida."),
            'url' => $this->url($etiqueta, $v, (bool) ($op['privada'] ?? false)),
            'number' => ctype_digit($v) && (int) $v <= 100000 ? $v : throw new FlujoException("«{$etiqueta}»: debe ser un número entero."),
            'path' => $this->ruta($etiqueta, $v),
            default => (! empty($op['regex']) && ! preg_match($op['regex'], $v)) ? throw new FlujoException("«{$etiqueta}»: valor con formato no permitido.") : $v,
        };
    }

    private function url(string $etiqueta, string $v, bool $privadaOk): string
    {
        $p = parse_url($v);
        if (! $p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            throw new FlujoException("«{$etiqueta}»: debe ser una URL https:// sin credenciales.");
        }
        $host = strtolower($p['host']);
        if (! $privadaOk && ($host === 'localhost' || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)))) {
            throw new FlujoException("«{$etiqueta}»: no se permiten direcciones locales o privadas (protección SSRF).");
        }
        if (! $privadaOk && ! filter_var($host, FILTER_VALIDATE_IP) && $this->resuelveAPrivada($host)) {
            throw new FlujoException("«{$etiqueta}»: el nombre resuelve a una dirección local o privada (protección SSRF).");
        }

        return $v;
    }

    /** Resuelve el nombre y detecta destinos internos (169.254.x.x, 10.x, 127.x…). En producción un nombre que no resuelve se rechaza. */
    private function resuelveAPrivada(string $host): bool
    {
        $r = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        if ($r === []) {
            return app()->isProduction();
        }
        foreach ($r as $x) {
            $ip = $x['ip'] ?? $x['ipv6'] ?? null;
            if ($ip && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }

    private function ruta(string $etiqueta, string $v): string
    {
        if (! str_starts_with($v, '/') || str_contains($v, '..') || ! preg_match('#^/[A-Za-z0-9._\-/ ]+$#', $v)) {
            throw new FlujoException("«{$etiqueta}»: debe ser una ruta absoluta sin «..».");
        }
        if (str_starts_with($v, rtrim(public_path(), '/').'/')) {
            throw new FlujoException("«{$etiqueta}»: el archivo no puede estar dentro del docroot (public/).");
        }

        return $v;
    }

    /** @param array<string,mixed> $v */
    private function validarCoherencia(string $servicio, array $v): void
    {
        if (app()->isProduction()) {
            foreach (['claveunica' => ['mode', ['mock']], 'firmagob' => ['mode', ['mock']], 'docdigital' => ['mode', ['mock']], 'pisee' => ['mode', ['mock']]] as $s => [$campo, $prohibidos]) {
                if ($servicio === $s && in_array($v[$campo] ?? null, $prohibidos, true)) {
                    throw new FlujoException('El modo simulado no está permitido con la configuración de producción de este servidor: elija el servicio real.');
                }
            }
        }
        if ($servicio === 'directorio' && ! empty($v['rol_defecto']) && Rol::query()->where('clave', $v['rol_defecto'])->where('activo', true)->value('rol_base') !== 'funcionario') {
            throw new FlujoException('El rol de los funcionarios nuevos debe existir y tener base «funcionario».');
        }
        if ($servicio === 'repositorio' && ($v['modo'] ?? null) === 'drive') {
            foreach (['drive_sa_json', 'drive_shared_drive_id', 'drive_root_folder_id'] as $c) {
                if (empty($v[$c]) && empty(config('sgd.repositorio.'.$c))) {
                    throw new FlujoException('Para usar Google Drive complete la cuenta de servicio, la unidad compartida y la carpeta raíz.');
                }
            }
            foreach (['drive_shared_drive_id', 'drive_root_folder_id'] as $c) {
                if (isset($v[$c]) && ! DriveClient::idValido($v[$c])) {
                    throw new FlujoException('El ID de Drive debe tener entre 10 y 100 caracteres (letras, números, - y _).');
                }
            }
        }
    }

    /**
     * Validación estructural de una integración (no envía datos de ciudadanos).
     *
     * @return array<int,array{0:string,1:bool,2:string}>
     */
    public function probar(string $servicio): array
    {
        $cat = ApiCatalogo::servicios()[$servicio] ?? throw new FlujoException('Servicio desconocido.');
        if (in_array($servicio, ['claveunica', 'firmagob', 'docdigital'], true)) {
            return $this->diagnostico($servicio);
        }
        if ($servicio === 'codificador') {
            return app(CodificadorService::class)->probar();
        }
        if ($servicio === 'directorio') {
            return app(DirectorioFuncionarios::class)->probar();
        }
        $extra = [];
        $out = [];
        foreach ($cat['campos'] as $clave => $def) {
            [$etiqueta, $tipo, $path, $op] = $def + [3 => []];
            $val = config($path);
            $vacio = $val === null || $val === '' || $val === false;
            $solo = ['mode', 'modo'];
            $out[] = [$etiqueta, ! $vacio || $tipo === 'bool' || in_array($clave, $solo, true), $vacio ? 'sin definir' : (($op['secreto'] ?? false) ? 'definido' : ($tipo === 'path' ? (is_file((string) $val) ? 'archivo presente' : 'el archivo NO existe') : 'definido'))];
            if ($tipo === 'path' && ! $vacio && ! is_file((string) $val)) {
                $out[count($out) - 1][1] = false;
            }
            if ($tipo === 'path' && ! $vacio && is_file((string) $val) && (fileperms((string) $val) & 0007) !== 0) {
                $out[] = [$etiqueta.': permisos', false, 'el archivo es legible por "otros": use permisos 600/640'];
            }
        }
        if ($servicio === 'repositorio' && config('sgd.repositorio.modo') === 'drive') {
            try {
                $extra = app(RepositorioService::class)->probar();
            } catch (\Throwable) {
                $extra = [['Conexión con Google Drive', false, 'No fue posible completar la prueba.']];
            }
        }
        if ($servicio === 'google_mail' && ($f = config('sgd.google.service_account_json')) && is_file((string) $f) && config('sgd.google.sender')) {
            [$okT, $detT] = (new GmailApiTransport(config('sgd.google')))->probarToken();
            $extra[] = ['Delegación de dominio (token de Google, sin enviar correo)', $okT, $detT];
        }
        if ($servicio === 'google_mail' && ($f = config('sgd.google.service_account_json')) && is_file((string) $f)) {
            $j = json_decode((string) file_get_contents((string) $f), true);
            $out[] = ['Contenido del JSON (client_email y private_key)', is_array($j) && ! empty($j['client_email']) && ! empty($j['private_key']) && openssl_pkey_get_private($j['private_key']) !== false, 'estructura de cuenta de servicio'];
        }

        return array_merge($out, $extra);
    }

    /**
     * Verificación por servicio (sin enviar datos): ClaveÚnica y FirmaGob revisan la configuración del ambiente activo;
     * DocDigital hace además la consulta de solo lectura de «Probar conexión» (token, entidad y tipos de documento).
     *
     * @return list<array{0:string,1:bool,2:string}>
     */
    private function diagnostico(string $servicio): array
    {
        $ok = fn (string $n, bool $b, string $d) => [$n, $b, $d];
        $out = [];
        if ($servicio === 'claveunica') {
            $c = config('sgd.claveunica');
            $amb = in_array($c['ambiente'] ?? null, ['sandbox', 'qa', 'produccion'], true) ? $c['ambiente'] : 'produccion';
            $v = fn (string $k) => (string) (($c[$amb.'_'.$k] ?? '') ?: ($c[$k] ?? ''));
            $out[] = $ok('Modo', ($c['mode'] ?? '') === 'real', ($c['mode'] ?? '') === 'real' ? 'Real' : 'Simulado: el botón de ClaveÚnica no llama al servicio real');
            $out[] = $ok('Ambiente activo', true, ['sandbox' => 'Sandbox / Testing (solo RUN de prueba)', 'qa' => 'QA (solo RUN de prueba)', 'produccion' => 'Producción'][$amb]);
            $out[] = $ok('client_id', $v('client_id') !== '', $v('client_id') !== '' ? 'definido' : 'sin definir');
            $secreto = ! empty($c[$amb.'_client_id']) ? ! empty($c[$amb.'_client_secret']) : ! empty($c['client_secret']);
            $out[] = $ok('client_secret', $secreto, $secreto ? 'definido' : 'sin definir');
            $red = $v('redirect_uri');
            $p = parse_url($red) ?: [];
            $redOk = $red !== '' && ($p['scheme'] ?? '') === 'https' && ! isset($p['query']) && ! isset($p['fragment']) && ! filter_var($p['host'] ?? '', FILTER_VALIDATE_IP);
            $out[] = $ok('Redirect URI', $redOk, $red === '' ? 'sin definir' : ($redOk ? $red : 'debe ser https, sin query ni fragmento y sin dirección IP'));
            $lo = $v('logout_uri');
            $lp = parse_url($lo) ?: [];
            $loOk = $lo !== '' && ($lp['scheme'] ?? '') === 'https' && ! isset($lp['query']);
            $out[] = $ok('Logout URI (obligatoria; debe estar registrada ante ClaveÚnica)', $loOk, $lo === '' ? 'sin definir: se usará la portada del sitio' : ($loOk ? $lo : 'debe ser https y sin query'));
            $svc = new ClaveUnicaService($c);
            if (($c['mode'] ?? '') === 'real') {
                [$okC, $detC] = $svc->verificarCredenciales();
                $out[] = $ok('Credenciales aceptadas por ClaveÚnica (consulta real, sin iniciar sesión)', $okC, $detC);
            }
            $out[] = $ok('Endpoints en accounts.claveunica.gob.cl', collect(['authorize_url', 'token_url', 'userinfo_url', 'logout_url'])->every(fn ($k) => str_starts_with((string) ($c[$k] ?? ''), 'https://accounts.claveunica.gob.cl/')), 'authorize, token, userinfo y logout');

            return $out;
        }
        if ($servicio === 'firmagob') {
            $c = config('sgd.firmagob');
            $m = (string) ($c['mode'] ?? '');
            $out[] = $ok('Ambiente', in_array($m, ['test', 'prod'], true), ['test' => 'Pruebas (firmas sin validez)', 'prod' => 'Producción (certificado real)', 'mock' => 'Simulado: no se llama a FirmaGob'][$m] ?? 'sin definir');
            if (in_array($m, ['test', 'prod'], true)) {
                $tk = ! empty($c[$m.'_api_token_key']) || ! empty($c['api_token_key']);
                $sc = ! empty($c[$m.'_api_token_key']) ? ! empty($c[$m.'_secret']) : ! empty($c['secret']);
                $out[] = $ok('api_token_key del ambiente', $tk, $tk ? 'definido' : 'sin definir');
                $out[] = $ok('Secret del ambiente', $sc, $sc ? 'definido' : 'sin definir');
            }
            $out[] = $ok('Entidad', ! empty($c['entity']), (string) ($c['entity'] ?: 'sin definir'));
            $out[] = $ok('Propósito', ! empty($c['purpose']), (string) ($c['purpose'] ?: 'sin definir'));
            $out[] = $ok('URL del servicio', str_starts_with((string) ($c['urls'][$m] ?? ''), 'https://'), (string) parse_url((string) ($c['urls'][$m] ?? ''), PHP_URL_HOST));

            return $out;
        }
        $d = config('sgd.docdigital');
        $m = (string) ($d['mode'] ?? 'outbox');
        if (! in_array($m, ['demo', 'prod'], true)) {
            return [$ok('Modo', false, 'El modo actual ('.$m.') no se conecta a DocDigital: elija DemoDoc o Producción.')];
        }
        try {
            return (new HttpDocDigitalClient($d, $m))->diagnosticar();
        } catch (\Throwable) {
            return [$ok('Diagnóstico de DocDigital', false, 'No fue posible completar la consulta.')];
        }
    }
}
