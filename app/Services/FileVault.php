<?php

namespace App\Services;

use RuntimeException;

/**
 * Almacén de archivos cifrados en reposo (XChaCha20-Poly1305, libsodium).
 * Formato: "SGD1" | id_clave(2 bytes len + id) | nonce(24) | ciphertext. Datos asociados = SHA-256 del texto plano.
 */
class FileVault
{
    public function __construct(private readonly string $root, private readonly string $activeKeyId, private readonly array $keys)
    {
        if (! isset($keys[$activeKeyId])) {
            throw new RuntimeException('Clave de cifrado de archivos no configurada.');
        }
    }

    public static function fromConfig(): self
    {
        $raw = (string) config('sgd.file_keys');
        $decoded = $raw !== '' ? json_decode($raw, true) : [];
        $keys = [];
        foreach ((array) $decoded as $id => $b64) {
            $k = base64_decode((string) $b64, true);
            if ($k !== false && strlen($k) === SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                $keys[(string) $id] = $k;
            }
        }

        return new self((string) config('sgd.storage_path'), (string) config('sgd.file_key_id'), $keys);
    }

    /** @return array{ruta:string,sha256:string,tamano:int} */
    public function put(string $plain): array
    {
        $sha = hash('sha256', $plain);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $sha, $nonce, $this->keys[$this->activeKeyId]);
        $blob = 'SGD1'.chr(strlen($this->activeKeyId)).$this->activeKeyId.$nonce.$cipher;

        $name = bin2hex(random_bytes(20)); // nombre aleatorio: no revela nada del contenido
        $rel = substr($name, 0, 2).'/'.substr($name, 2);
        $dir = $this->root.'/'.substr($name, 0, 2);
        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de almacenamiento.');
        }
        $path = $this->root.'/'.$rel;
        if (file_put_contents($path, $blob, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el archivo.');
        }
        @chmod($path, 0640);

        return ['ruta' => $rel, 'sha256' => $sha, 'tamano' => strlen($plain)];
    }

    /** Descifra y verifica integridad contra el SHA-256 registrado en BD. */
    public function get(string $ruta, string $expectedSha256): string
    {
        if (! preg_match('#^[0-9a-f]{2}/[0-9a-f]{38}$#', $ruta)) {
            throw new RuntimeException('Ruta de archivo inválida.'); // sin path traversal
        }
        $blob = @file_get_contents($this->root.'/'.$ruta);
        if ($blob === false || strlen($blob) < 6 || substr($blob, 0, 4) !== 'SGD1') {
            throw new RuntimeException('Archivo no disponible.');
        }
        $idLen = ord($blob[4]);
        $id = substr($blob, 5, $idLen);
        $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $nonce = substr($blob, 5 + $idLen, $nonceLen);
        $cipher = substr($blob, 5 + $idLen + $nonceLen);
        if (! isset($this->keys[$id]) || strlen($nonce) !== $nonceLen) {
            throw new RuntimeException('Archivo no disponible.');
        }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $expectedSha256, $nonce, $this->keys[$id]);
        if ($plain === false || ! hash_equals($expectedSha256, hash('sha256', $plain))) {
            throw new RuntimeException('Integridad del archivo no verificada.');
        }

        return $plain;
    }
}
