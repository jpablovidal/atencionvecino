<?php

namespace App\Support;

use RuntimeException;

/**
 * XChaCha20-Poly1305 (IETF) compatible byte a byte con libsodium (`sodium_crypto_aead_xchacha20poly1305_ietf_*`).
 * Se usa SOLO cuando la extensión `sodium` no está disponible (p. ej. hosting compartido que no permite activarla):
 * HChaCha20 (derivación de subclave, RFC draft-irtf-cfrg-xchacha) + ChaCha20-Poly1305 IETF de OpenSSL (RFC 8439).
 * No implementa primitivas propias de cifrado ni autenticación: solo la derivación de subclave. Verificado contra libsodium en las pruebas.
 */
final class AeadXChaCha
{
    public const NONCE_BYTES = 24;

    public const KEY_BYTES = 32;

    public const TAG_BYTES = 16;

    /** Solo pruebas: obliga a usar la alternativa aunque exista la extensión (para ejercitar toda la suite sin sodium). */
    public static bool $soloAlternativa = false;

    public static function disponibleNativo(): bool
    {
        return ! self::$soloAlternativa && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt');
    }

    /** Disponible con sodium o con OpenSSL (chacha20-poly1305). */
    public static function soportado(): bool
    {
        return self::disponibleNativo() || (extension_loaded('openssl') && in_array('chacha20-poly1305', openssl_get_cipher_methods(), true));
    }

    public static function cifrar(string $plano, string $ad, string $nonce, string $clave, bool $forzarFallback = false): string
    {
        if (! $forzarFallback && self::disponibleNativo()) {
            return sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plano, $ad, $nonce, $clave);
        }
        [$sub, $iv] = self::derivar($nonce, $clave);
        $tag = '';
        $c = openssl_encrypt($plano, 'chacha20-poly1305', $sub, OPENSSL_RAW_DATA, $iv, $tag, $ad, self::TAG_BYTES);
        if ($c === false || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('No fue posible cifrar el archivo.');
        }

        return $c.$tag;
    }

    /** @return string|false texto plano, o false si la autenticación falla */
    public static function descifrar(string $cifrado, string $ad, string $nonce, string $clave, bool $forzarFallback = false): string|false
    {
        if (! $forzarFallback && self::disponibleNativo()) {
            return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cifrado, $ad, $nonce, $clave);
        }
        if (strlen($cifrado) < self::TAG_BYTES) {
            return false;
        }
        [$sub, $iv] = self::derivar($nonce, $clave);
        $tag = substr($cifrado, -self::TAG_BYTES);
        $c = substr($cifrado, 0, -self::TAG_BYTES);
        $p = openssl_decrypt($c, 'chacha20-poly1305', $sub, OPENSSL_RAW_DATA, $iv, $tag, $ad);

        return $p === false ? false : $p;
    }

    /** @return array{0:string,1:string} [subclave de 32 bytes, nonce IETF de 12 bytes] */
    private static function derivar(string $nonce, string $clave): array
    {
        if (strlen($nonce) !== self::NONCE_BYTES || strlen($clave) !== self::KEY_BYTES) {
            throw new RuntimeException('Longitud de clave o nonce inválida.');
        }

        return [self::hchacha20(substr($nonce, 0, 16), $clave), "\0\0\0\0".substr($nonce, 16, 8)];
    }

    private static function hchacha20(string $in16, string $key): string
    {
        $s = array_merge([0x61707865, 0x3320646E, 0x79622D32, 0x6B206574], array_values(unpack('V8', $key)), array_values(unpack('V4', $in16)));
        $r = static fn (int $v, int $n): int => (($v << $n) | ($v >> (32 - $n))) & 0xFFFFFFFF;
        $qr = static function (array &$x, int $a, int $b, int $c, int $d) use ($r): void {
            $x[$a] = ($x[$a] + $x[$b]) & 0xFFFFFFFF;
            $x[$d] = $r($x[$d] ^ $x[$a], 16);
            $x[$c] = ($x[$c] + $x[$d]) & 0xFFFFFFFF;
            $x[$b] = $r($x[$b] ^ $x[$c], 12);
            $x[$a] = ($x[$a] + $x[$b]) & 0xFFFFFFFF;
            $x[$d] = $r($x[$d] ^ $x[$a], 8);
            $x[$c] = ($x[$c] + $x[$d]) & 0xFFFFFFFF;
            $x[$b] = $r($x[$b] ^ $x[$c], 7);
        };
        for ($i = 0; $i < 10; $i++) {
            $qr($s, 0, 4, 8, 12);
            $qr($s, 1, 5, 9, 13);
            $qr($s, 2, 6, 10, 14);
            $qr($s, 3, 7, 11, 15);
            $qr($s, 0, 5, 10, 15);
            $qr($s, 1, 6, 11, 12);
            $qr($s, 2, 7, 8, 13);
            $qr($s, 3, 4, 9, 14);
        }

        return pack('V8', $s[0], $s[1], $s[2], $s[3], $s[12], $s[13], $s[14], $s[15]);
    }
}
