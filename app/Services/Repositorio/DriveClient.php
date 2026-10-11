<?php

namespace App\Services\Repositorio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo de la API de Google Drive v3 para una UNIDAD COMPARTIDA (Shared Drive).
 * Autenticación: cuenta de servicio (JWT RS256, sin delegación de dominio) que debe ser MIEMBRO de la unidad compartida
 * con rol "Gestor de contenido". Alcance: https://www.googleapis.com/auth/drive (con `drive.file` no se puede escribir en una
 * carpeta creada por una persona); el mínimo privilegio se logra limitando la membresía de la cuenta a ESA unidad.
 * Endpoints fijos, sin redirecciones, timeouts, reintentos acotados. La clave JSON nunca se registra.
 */
class DriveClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const ID = '/^[A-Za-z0-9_\-]{10,100}$/';

    /** @param array{sa_json:?string,shared_drive_id:?string,root_folder_id:?string,timeout:int,max_bytes:int} $cfg */
    public function __construct(private readonly array $cfg) {}

    public static function idValido(string $id): bool
    {
        return (bool) preg_match(self::ID, $id);
    }

    public function configurado(): bool
    {
        return $this->cfg['sa_json'] && is_file((string) $this->cfg['sa_json']) && self::idValido((string) $this->cfg['shared_drive_id']) && self::idValido((string) $this->cfg['root_folder_id']);
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->withToken($this->token());
    }

    /** Reintenta ante 429/5xx (máx. 3 intentos, espera 0,2 s y 0,8 s). */
    private function enviar(callable $f): Response
    {
        $r = null;
        for ($i = 0; $i < 3; $i++) {
            try {
                $r = $f();
            } catch (\Throwable) {
                throw new DriveException('No fue posible contactar Google Drive.');
            }
            if ($r->status() === 401) {
                Cache::forget('drive_access_token');
            }
            if ($r->status() !== 429 && $r->status() < 500) {
                return $r;
            }
            usleep($i === 0 ? 200_000 : 800_000);
        }

        return $r;
    }

    private function token(): string
    {
        return Cache::remember('drive_access_token', 3000, function () {
            $path = (string) $this->cfg['sa_json'];
            $sa = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            if (! is_array($sa) || empty($sa['private_key']) || empty($sa['client_email'])) {
                throw new DriveException('Credenciales de la cuenta de servicio de Drive no disponibles.');
            }
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $h = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $p = $b64((string) json_encode(['iss' => $sa['client_email'], 'scope' => self::SCOPE, 'aud' => self::TOKEN, 'iat' => $now, 'exp' => $now + 3000]));
            if (! openssl_sign("$h.$p", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new DriveException('No se pudo firmar la aserción JWT.');
            }
            try {
                $r = Http::timeout((int) $this->cfg['timeout'])->connectTimeout(5)->withoutRedirecting()->asForm()
                    ->post(self::TOKEN, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$h.$p.".$b64($sig)]);
            } catch (\Throwable) {
                throw new DriveException('No fue posible obtener el token de Google.');
            }
            $t = $r->successful() ? $r->json('access_token') : null;
            if (! is_string($t) || $t === '') {
                throw new DriveException('Google rechazó las credenciales de Drive (HTTP '.$r->status().').');
            }

            return $t;
        });
    }

    private function parametros(): array
    {
        return ['supportsAllDrives' => 'true'];
    }

    private function exigirId(string $id): string
    {
        if (! self::idValido($id)) {
            throw new DriveException('Identificador de Drive inválido.');
        }

        return $id;
    }

    /** Busca (o crea) una subcarpeta por nombre dentro de $padre. */
    public function asegurarCarpeta(string $nombre, string $padre): string
    {
        $this->exigirId($padre);
        $nombreQ = str_replace(['\\', "'"], ['\\\\', "\\'"], $nombre);
        $q = "name = '$nombreQ' and '$padre' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
        $r = $this->enviar(fn () => $this->http()->get(self::API.'/files', $this->parametros() + [
            'q' => $q, 'fields' => 'files(id,name)', 'corpora' => 'drive', 'driveId' => $this->cfg['shared_drive_id'], 'includeItemsFromAllDrives' => 'true', 'pageSize' => 1,
        ]));
        if (! $r->successful()) {
            throw new DriveException('Drive rechazó la búsqueda de carpeta (HTTP '.$r->status().').');
        }
        $id = $r->json('files.0.id');
        if (is_string($id) && self::idValido($id)) {
            return $id;
        }
        $c = $this->enviar(fn () => $this->http()->asJson()->post(self::API.'/files?'.http_build_query($this->parametros()), [
            'name' => $nombre, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$padre],
        ]));
        $nuevo = $c->successful() ? $c->json('id') : null;
        if (! is_string($nuevo) || ! self::idValido($nuevo)) {
            throw new DriveException('Drive rechazó la creación de la carpeta (HTTP '.$c->status().').');
        }

        return $nuevo;
    }

    /** @return array{id:string,md5:string} */
    public function subir(string $nombre, string $carpeta, string $bytes): array
    {
        $this->exigirId($carpeta);
        if (strlen($bytes) > $this->cfg['max_bytes']) {
            throw new DriveException('El archivo excede el tamaño máximo.');
        }
        $b = 'sgd'.bin2hex(random_bytes(12));
        $meta = (string) json_encode(['name' => $nombre, 'parents' => [$carpeta]]);
        $cuerpo = "--$b\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$b\r\nContent-Type: application/octet-stream\r\n\r\n$bytes\r\n--$b--";
        $r = $this->enviar(fn () => $this->http()->withBody($cuerpo, "multipart/related; boundary=$b")
            ->post(self::UPLOAD.'?'.http_build_query($this->parametros() + ['uploadType' => 'multipart', 'fields' => 'id,md5Checksum,size'])));
        $id = $r->successful() ? $r->json('id') : null;
        if (! is_string($id) || ! self::idValido($id)) {
            throw new DriveException('Drive rechazó la subida (HTTP '.$r->status().').');
        }
        $md5 = md5($bytes);
        $remoto = $r->json('md5Checksum');
        if (is_string($remoto) && ! hash_equals($md5, strtolower($remoto))) { // integridad extremo a extremo del blob cifrado
            $this->eliminar($id);
            throw new DriveException('Drive devolvió una suma de verificación distinta: subida descartada.');
        }

        return ['id' => $id, 'md5' => $md5];
    }

    public function descargar(string $id): string
    {
        $this->exigirId($id);
        $r = $this->enviar(fn () => $this->http()->get(self::API.'/files/'.rawurlencode($id), $this->parametros() + ['alt' => 'media']));
        if (! $r->successful()) {
            throw new DriveException('Drive no entregó el archivo (HTTP '.$r->status().').');
        }
        if (strlen($r->body()) > $this->cfg['max_bytes'] + 4096) {
            throw new DriveException('Archivo remoto demasiado grande.');
        }

        return $r->body();
    }

    public function eliminar(string $id): void
    {
        $this->exigirId($id);
        $r = $this->enviar(fn () => $this->http()->delete(self::API.'/files/'.rawurlencode($id).'?'.http_build_query($this->parametros())));
        if (! $r->successful() && $r->status() !== 404) {
            throw new DriveException('Drive no permitió eliminar el archivo (HTTP '.$r->status().').');
        }
    }

    /** @return array<string,mixed> */
    public function metadatos(string $id): array
    {
        $this->exigirId($id);
        $r = $this->enviar(fn () => $this->http()->get(self::API.'/files/'.rawurlencode($id), $this->parametros() + ['fields' => 'id,name,mimeType,driveId,md5Checksum,size,capabilities(canAddChildren,canDelete)']));
        if (! $r->successful()) {
            throw new DriveException('Drive no entregó los metadatos (HTTP '.$r->status().').');
        }

        return (array) $r->json();
    }

    /**
     * Prueba de conexión extremo a extremo: lee la carpeta raíz (debe pertenecer a la unidad compartida configurada y permitir
     * escritura), sube un blob de prueba, lo descarga, compara y lo elimina.
     *
     * @return array<int,array{0:string,1:bool,2:string}> [paso, ok, detalle]
     */
    public function probar(): array
    {
        $out = [];
        $paso = function (string $n, callable $f) use (&$out) {
            try {
                $d = $f();
                $out[] = [$n, true, is_string($d) ? $d : 'OK'];

                return true;
            } catch (\Throwable $e) {
                $out[] = [$n, false, $e instanceof DriveException ? $e->getMessage() : 'Error inesperado'];

                return false;
            }
        };
        if (! $paso('Configuración (cuenta de servicio, unidad compartida y carpeta raíz)', function () {
            if (! $this->configurado()) {
                throw new DriveException('Falta la ruta del JSON de la cuenta de servicio, el ID de la unidad compartida o el ID de la carpeta raíz.');
            }
        })) {
            return $out;
        }
        if (! $paso('Autenticación con la cuenta de servicio', fn () => (bool) $this->token())) {
            return $out;
        }
        $meta = [];
        if (! $paso('Carpeta raíz pertenece a la unidad compartida y permite escribir', function () use (&$meta) {
            $meta = $this->metadatos((string) $this->cfg['root_folder_id']);
            if (($meta['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') {
                throw new DriveException('El ID de la carpeta raíz no es una carpeta.');
            }
            if (($meta['driveId'] ?? null) !== $this->cfg['shared_drive_id']) {
                throw new DriveException('La carpeta raíz no pertenece a la unidad compartida indicada.');
            }
            if (($meta['capabilities']['canAddChildren'] ?? false) !== true) {
                throw new DriveException('La cuenta de servicio no puede crear archivos en la carpeta (¿rol "Gestor de contenido"?).');
            }

            return 'Carpeta: '.($meta['name'] ?? '');
        })) {
            return $out;
        }
        $id = null;
        $blob = random_bytes(64);
        if ($paso('Subir archivo de prueba (cifrado/aleatorio)', function () use (&$id, $blob) {
            $id = $this->subir('sgd-prueba-'.bin2hex(random_bytes(6)), (string) $this->cfg['root_folder_id'], $blob)['id'];
        })) {
            $paso('Descargar y comparar', function () use ($id, $blob) {
                if (! hash_equals($blob, $this->descargar((string) $id))) {
                    throw new DriveException('El contenido descargado no coincide.');
                }
            });
            $paso('Eliminar archivo de prueba', fn () => $this->eliminar((string) $id));
        }

        return $out;
    }
}
