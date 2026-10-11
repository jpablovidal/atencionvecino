<?php

namespace Tests\Support;

use App\Services\FileVault;
use App\Services\Repositorio\RepositorioService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Google Drive (unidad compartida) simulado en memoria: token, carpetas, subida multipart, descarga, borrado y metadatos. */
class FakeDrive
{
    public const DRIVE_ID = '0ASharedDriveId12345';

    public const ROOT_ID = '1RootFolderIdAbcdef12345';

    /** @var array<string,array{name:string,parent:?string,mime:string,bytes:string}> */
    public array $files = [];

    /** @var array<int,string> */
    public array $log = [];

    public bool $canAddChildren = true;

    public string $driveIdDeLaRaiz = self::DRIVE_ID;

    public ?int $fallarSubida = null; // HTTP a devolver en la subida

    public bool $md5Incorrecto = false;

    private int $n = 0;

    public string $saPath = '';

    public function __construct()
    {
        $this->files[self::ROOT_ID] = ['name' => 'Repositorio SGD', 'parent' => null, 'mime' => 'application/vnd.google-apps.folder', 'bytes' => ''];
        $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($k, $pem);
        $this->saPath = sys_get_temp_dir().'/drive-sa-'.getmypid().'-'.spl_object_id($this).'.json';
        file_put_contents($this->saPath, json_encode(['client_email' => 'drive-sa@proj.iam.gserviceaccount.com', 'private_key' => $pem]));
        chmod($this->saPath, 0600);
    }

    public function __destruct()
    {
        @unlink($this->saPath);
    }

    public function configurar(string $modo = 'drive'): void
    {
        config(['sgd.repositorio' => ['modo' => $modo, 'drive_sa_json' => $this->saPath, 'drive_shared_drive_id' => self::DRIVE_ID, 'drive_root_folder_id' => self::ROOT_ID, 'timeout' => 5, 'max_bytes' => 25 * 1024 * 1024]]);
        Cache::forget('drive_access_token');
        app()->forgetInstance(FileVault::class);
        app()->forgetInstance(RepositorioService::class);
    }

    public function activar(): void
    {
        Http::fake(fn (Request $r) => $this->responder($r));
    }

    private function id(): string
    {
        return 'F'.str_pad((string) ++$this->n, 12, 'x').'abc';
    }

    private function responder(Request $r)
    {
        $url = $r->url();
        $path = parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->log[] = $r->method().' '.$path;
        if (str_contains($url, 'oauth2.googleapis.com')) {
            return Http::response(['access_token' => 'drive-token'], 200);
        }
        if (! str_contains($url, 'googleapis.com/') || ! $r->hasHeader('Authorization', 'Bearer drive-token')) {
            return Http::response(['error' => 'unauthorized'], 401);
        }
        if (($q['supportsAllDrives'] ?? '') !== 'true') {
            return Http::response(['error' => 'falta supportsAllDrives'], 400);
        }
        if (str_starts_with((string) $path, '/upload/drive/v3/files')) {
            if ($this->fallarSubida) {
                return Http::response(['error' => 'x'], $this->fallarSubida);
            }
            [$meta, $bytes] = $this->multipart($r->body());
            $id = $this->id();
            $this->files[$id] = ['name' => $meta['name'], 'parent' => $meta['parents'][0] ?? null, 'mime' => 'application/octet-stream', 'bytes' => $bytes];

            return Http::response(['id' => $id, 'size' => (string) strlen($bytes)] + ($this->md5Incorrecto ? ['md5Checksum' => str_repeat('0', 32)] : ['md5Checksum' => md5($bytes)]), 200);
        }
        if ($path === '/drive/v3/files' && $r->method() === 'GET') { // búsqueda de carpeta
            preg_match("/name = '((?:[^'\\\\]|\\\\.)*)' and '([^']+)' in parents/", $q['q'] ?? '', $m);
            foreach ($this->files as $id => $f) {
                if ($f['mime'] === 'application/vnd.google-apps.folder' && $f['name'] === ($m[1] ?? null) && $f['parent'] === ($m[2] ?? null)) {
                    return Http::response(['files' => [['id' => $id, 'name' => $f['name']]]], 200);
                }
            }

            return Http::response(['files' => []], 200);
        }
        if ($path === '/drive/v3/files' && $r->method() === 'POST') { // crear carpeta
            $b = $r->data();
            $id = $this->id();
            $this->files[$id] = ['name' => $b['name'], 'parent' => $b['parents'][0] ?? null, 'mime' => $b['mimeType'], 'bytes' => ''];

            return Http::response(['id' => $id], 200);
        }
        if (preg_match('#^/drive/v3/files/([^/]+)$#', (string) $path, $m)) {
            $id = rawurldecode($m[1]);
            if (! isset($this->files[$id])) {
                return Http::response(['error' => 'notFound'], 404);
            }
            if ($r->method() === 'DELETE') {
                unset($this->files[$id]);

                return Http::response('', 204);
            }
            $f = $this->files[$id];
            if (($q['alt'] ?? '') === 'media') {
                return Http::response($f['bytes'], 200);
            }

            return Http::response(['id' => $id, 'name' => $f['name'], 'mimeType' => $f['mime'], 'driveId' => $id === self::ROOT_ID ? $this->driveIdDeLaRaiz : self::DRIVE_ID,
                'md5Checksum' => md5($f['bytes']), 'size' => (string) strlen($f['bytes']), 'capabilities' => ['canAddChildren' => $this->canAddChildren, 'canDelete' => true]], 200);
        }

        return Http::response(['error' => 'ruta no simulada '.$path], 404);
    }

    /** @return array{0:array<string,mixed>,1:string} */
    private function multipart(string $body): array
    {
        preg_match('/^--(\S+)\r\n/', $body, $m);
        $partes = explode('--'.$m[1], $body);
        $json = trim(explode("\r\n\r\n", $partes[1], 2)[1]);
        $bytes = explode("\r\n\r\n", $partes[2], 2)[1];

        return [json_decode($json, true), substr($bytes, 0, -2)]; // quita el CRLF previo al delimitador
    }

    public function archivosSubidos(): array
    {
        return array_filter($this->files, fn ($f) => $f['mime'] === 'application/octet-stream');
    }

    public function carpetaPadre(string $id): ?string
    {
        return $this->files[$id]['parent'] ?? null;
    }

    public function ruta(string $id): string
    {
        $partes = [];
        for ($c = $id; $c !== null && $c !== self::ROOT_ID; $c = $this->files[$c]['parent'] ?? null) {
            array_unshift($partes, $this->files[$c]['name']);
        }

        return implode('/', $partes);
    }
}
