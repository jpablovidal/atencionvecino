<?php

namespace Tests\Unit;

use App\Support\AeadXChaCha;
use PHPUnit\Framework\TestCase;

class AeadXChaChaTest extends TestCase
{
    public function test_alternativa_openssl_es_identica_a_libsodium_en_ambos_sentidos(): void
    {
        if (! function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            $this->markTestSkipped('Requiere la extensión sodium para comparar.');
        }
        $this->assertTrue(AeadXChaCha::soportado());
        foreach ([0, 1, 15, 16, 17, 63, 64, 65, 1000, 70000] as $n) {
            $k = random_bytes(32);
            $nonce = random_bytes(24);
            $ad = ($n % 40) > 0 ? random_bytes($n % 40) : '';
            $p = $n > 0 ? random_bytes($n) : '';
            $nativo = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($p, $ad, $nonce, $k);
            $alt = AeadXChaCha::cifrar($p, $ad, $nonce, $k, true);
            $this->assertSame(bin2hex($nativo), bin2hex($alt), "cifrado idéntico ($n bytes)");
            $this->assertSame($p, AeadXChaCha::descifrar($nativo, $ad, $nonce, $k, true), "alternativa descifra lo de libsodium ($n)");
            $this->assertSame($p, sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($alt, $ad, $nonce, $k), "libsodium descifra lo de la alternativa ($n)");
        }
    }

    public function test_alternativa_detecta_manipulacion_y_parametros_invalidos(): void
    {
        $k = random_bytes(32);
        $nonce = random_bytes(24);
        $c = AeadXChaCha::cifrar('contenido secreto', 'sha', $nonce, $k, true);
        $this->assertSame('contenido secreto', AeadXChaCha::descifrar($c, 'sha', $nonce, $k, true));
        $mal = $c;
        $mal[3] = $mal[3] ^ "\x01";
        $this->assertFalse(AeadXChaCha::descifrar($mal, 'sha', $nonce, $k, true), 'texto alterado');
        $this->assertFalse(AeadXChaCha::descifrar($c, 'otro', $nonce, $k, true), 'datos asociados distintos');
        $this->assertFalse(AeadXChaCha::descifrar($c, 'sha', random_bytes(24), $k, true), 'nonce distinto');
        $this->assertFalse(AeadXChaCha::descifrar($c, 'sha', $nonce, random_bytes(32), true), 'clave distinta');
        $this->assertFalse(AeadXChaCha::descifrar('corto', 'sha', $nonce, $k, true));
        $this->expectException(\RuntimeException::class);
        AeadXChaCha::cifrar('x', '', 'nonce-corto', $k, true);
    }
}
