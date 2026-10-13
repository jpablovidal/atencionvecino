<?php

namespace App\Services;

use App\Models\RepositorioReubicacion;
use App\Services\Repositorio\BlobStore;
use App\Services\Repositorio\DriveBlobStore;
use App\Services\Repositorio\DriveClient;
use App\Services\Repositorio\LocalBlobStore;
use App\Support\AeadXChaCha;
use RuntimeException;

/**
 * Almacén de archivos cifrados en reposo (XChaCha20-Poly1305, libsodium).
 * Formato: "SGD1" | len(id) | id_clave | nonce(24) | ciphertext. Datos asociados = SHA-256 del texto plano.
 * El cifrado ocurre ANTES de salir del servidor: el almacén (disco local o unidad compartida de Google Drive) solo ve blobs
 * cifrados con nombres aleatorios. Las rutas "ab/…" son locales; "gd:<id>" son de Drive; una tabla de reubicaciones
 * permite mover blobs locales a Drive sin tocar las referencias inmutables (versiones de documentos).
 */
class FileVault
{
    private LocalBlobStore $local;

    /** @var array<string,?string> */
    private array $reubicadas = [];

    /** @param array<string,string> $keys */
    public function __construct(
        string $root,
        private readonly string $activeKeyId,
        private readonly array $keys,
        private readonly ?BlobStore $drive = null,
        private readonly string $modo = 'local',
    ) {
        if (! isset($keys[$activeKeyId])) {
            throw new RuntimeException('Clave de cifrado de archivos no configurada.');
        }
        $this->local = new LocalBlobStore($root);
    }

    public static function fromConfig(): self
    {
        $raw = (string) config('sgd.file_keys');
        $decoded = $raw !== '' ? json_decode($raw, true) : [];
        $keys = [];
        foreach ((array) $decoded as $id => $b64) {
            $k = base64_decode((string) $b64, true);
            if ($k !== false && strlen($k) === AeadXChaCha::KEY_BYTES) {
                $keys[(string) $id] = $k;
            }
        }
        $r = (array) config('sgd.repositorio');
        $drive = null;
        $cliente = new DriveClient(['sa_json' => $r['drive_sa_json'] ?? null, 'shared_drive_id' => $r['drive_shared_drive_id'] ?? null, 'root_folder_id' => $r['drive_root_folder_id'] ?? null,
            'timeout' => (int) ($r['timeout'] ?? 30), 'max_bytes' => (int) ($r['max_bytes'] ?? 26214400)]);
        if ($cliente->configurado()) { // se arma siempre que haya configuración, para poder LEER aunque el modo vuelva a local
            $drive = new DriveBlobStore($cliente, (string) $r['drive_root_folder_id']);
        }

        return new self((string) config('sgd.storage_path'), (string) config('sgd.file_key_id'), $keys, $drive, (string) ($r['modo'] ?? 'local'));
    }

    public function modoEscritura(): string
    {
        return $this->modo === 'drive' && $this->drive ? 'drive' : 'local';
    }

    /** @return array{ruta:string,sha256:string,tamano:int,almacen:string} */
    public function put(string $plain): array
    {
        if ($this->modo === 'drive' && ! $this->drive) {
            throw new RuntimeException('El repositorio está en modo Google Drive pero no está configurado.'); // falla cerrada
        }
        $sha = hash('sha256', $plain);
        $nonce = random_bytes(AeadXChaCha::NONCE_BYTES);
        $cipher = AeadXChaCha::cifrar($plain, $sha, $nonce, $this->keys[$this->activeKeyId]);
        $blob = 'SGD1'.chr(strlen($this->activeKeyId)).$this->activeKeyId.$nonce.$cipher;
        $store = $this->modoEscritura() === 'drive' ? $this->drive : $this->local;
        $ruta = $store->put($blob);

        return ['ruta' => $ruta, 'sha256' => $sha, 'tamano' => strlen($plain), 'almacen' => $this->modoEscritura()];
    }

    public function delete(string $ruta): void
    {
        if (DriveBlobStore::esRutaDrive($ruta)) {
            $this->drive?->delete($ruta);
        } else {
            $this->local->delete($ruta);
        }
    }

    /** Blob cifrado tal como está almacenado (resuelve reubicaciones). */
    public function blob(string $ruta): string
    {
        if (DriveBlobStore::esRutaDrive($ruta)) {
            return $this->requerirDrive()->get($ruta);
        }
        if (! LocalBlobStore::esRutaLocal($ruta)) {
            throw new RuntimeException('Ruta de archivo inválida.');
        }
        $destino = $this->destinoReubicado($ruta);

        return $destino ? $this->requerirDrive()->get($destino) : $this->local->get($ruta);
    }

    /** Blob cifrado LOCAL sin resolver reubicaciones (para migrar). */
    public function blobLocal(string $ruta): string
    {
        return $this->local->get($ruta);
    }

    /** Sube un blob ya cifrado a Drive (migración). */
    public function blobADrive(string $blob): string
    {
        return $this->requerirDrive()->put($blob);
    }

    public function driveConfigurado(): bool
    {
        return $this->drive !== null;
    }

    public function almacenDe(string $ruta): string
    {
        return DriveBlobStore::esRutaDrive($ruta) || $this->destinoReubicado($ruta) ? 'drive' : 'local';
    }

    private function requerirDrive(): BlobStore
    {
        return $this->drive ?? throw new RuntimeException('Google Drive no está configurado.');
    }

    private function destinoReubicado(string $ruta): ?string
    {
        if (! $this->drive) {
            return null;
        }
        if (! array_key_exists($ruta, $this->reubicadas)) {
            try {
                $this->reubicadas[$ruta] = RepositorioReubicacion::query()->where('ruta_origen', $ruta)->value('ruta_destino');
            } catch (\Throwable) {
                $this->reubicadas[$ruta] = null;
            }
        }

        return $this->reubicadas[$ruta];
    }

    /** Descifra y verifica integridad contra el SHA-256 registrado en BD. */
    public function get(string $ruta, string $expectedSha256): string
    {
        $blob = $this->blob($ruta);
        if (strlen($blob) < 6 || substr($blob, 0, 4) !== 'SGD1') {
            throw new RuntimeException('Archivo no disponible.');
        }
        $idLen = ord($blob[4]);
        $id = substr($blob, 5, $idLen);
        $nonceLen = AeadXChaCha::NONCE_BYTES;
        $nonce = substr($blob, 5 + $idLen, $nonceLen);
        $cipher = substr($blob, 5 + $idLen + $nonceLen);
        if (! isset($this->keys[$id]) || strlen($nonce) !== $nonceLen) {
            throw new RuntimeException('Archivo no disponible.');
        }
        $plain = AeadXChaCha::descifrar($cipher, $expectedSha256, $nonce, $this->keys[$id]);
        if ($plain === false || ! hash_equals($expectedSha256, hash('sha256', $plain))) {
            throw new RuntimeException('Integridad del archivo no verificada.');
        }

        return $plain;
    }
}
