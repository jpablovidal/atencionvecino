<?php

namespace Tests\Feature;

use App\Services\Firma\FirmaException;
use App\Services\Firma\HttpFirmaClient;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirmaGobClienteTest extends TestCase
{
    private function cliente(): HttpFirmaClient
    {
        return new HttpFirmaClient(['api_token_key' => 'tok-key', 'secret' => 'secreto-hs256', 'entity' => 'I. Municipalidad de Santo Domingo', 'purpose' => 'Propósito General',
            'urls' => ['test' => 'https://api.firma.test.digital.gob.cl/firma/v2/files/tickets', 'prod' => 'https://api.firma.digital.gob.cl/firma/v2/files/tickets'], 'max_bytes' => 5 * 1024 * 1024, 'timeout' => 5], 'test');
    }

    private const PDF = "%PDF-1.4\nfirmame\n%%EOF";

    public function test_envia_jwt_hs256_run_sin_dv_expiracion_corta_otp_en_cabecera_y_checksum(): void
    {
        $firmado = '%PDF-1.7 firmado';
        Http::fake(['*' => Http::response(['files' => [['content' => base64_encode($firmado), 'checksum_original' => hash('sha256', self::PDF)]]], 200)]);
        $r = $this->cliente()->firmar(self::PDF, '12345678-5', '987654', 'Decreto 1');
        $this->assertSame($firmado, $r['pdf']);
        $this->assertFalse($r['simulada']);
        Http::assertSent(function ($req) {
            $this->assertSame('987654', $req->header('OTP')[0]);
            $this->assertSame('https://api.firma.test.digital.gob.cl/firma/v2/files/tickets', $req->url());
            $b = $req->data();
            $this->assertSame('tok-key', $b['api_token_key']);
            $this->assertSame(hash('sha256', self::PDF), $b['files'][0]['checksum']);
            $this->assertSame(base64_encode(self::PDF), $b['files'][0]['content']);
            [$h, $p, $s] = explode('.', $b['token']);
            $d = fn ($x) => base64_decode(strtr($x, '-_', '+/'));
            $this->assertSame('HS256', json_decode($d($h), true)['alg']);
            $claims = json_decode($d($p), true);
            $this->assertSame('12345678', $claims['run']); // sin DV
            $this->assertSame('Propósito General', $claims['purpose']);
            $this->assertSame('I. Municipalidad de Santo Domingo', $claims['entity']);
            $exp = Carbon::parse($claims['expiration'], 'America/Santiago');
            $this->assertLessThanOrEqual(30, now()->diffInMinutes($exp, false));
            $this->assertGreaterThan(0, now()->diffInMinutes($exp, false));
            $this->assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", 'secreto-hs256', true)), '+/', '-_'), '='), $s);

            return true;
        });
    }

    public function test_checksum_distinto_error_http_y_otp_invalido_fallan_cerrado(): void
    {
        Http::fake(['*' => Http::response(['files' => [['content' => base64_encode('%PDF-1.7 x'), 'checksum_original' => 'deadbeef']]], 200)]);
        $this->expectFirma(fn () => $this->cliente()->firmar(self::PDF, '12345678-5', '987654', 'x'));
        Http::fake(['*' => Http::response(['error' => 'OTP'], 401)]);
        $this->expectFirma(fn () => $this->cliente()->firmar(self::PDF, '12345678-5', '987654', 'x'));
        $this->expectFirma(fn () => $this->cliente()->firmar(self::PDF, '12345678-5', '12ab56', 'x')); // OTP no numérico
        $this->expectFirma(fn () => $this->cliente()->firmar(str_repeat('A', 5 * 1024 * 1024 + 1), '12345678-5', '123456', 'x')); // > 5 MB
        Http::fake(['*' => fn () => throw new ConnectionException('timeout')]);
        $this->expectFirma(fn () => $this->cliente()->firmar(self::PDF, '12345678-5', '123456', 'x'));
    }

    public function test_no_sigue_redirecciones(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://evil.cl'])]);
        $this->expectFirma(fn () => $this->cliente()->firmar(self::PDF, '12345678-5', '123456', 'x'));
        Http::assertSentCount(1);
    }

    private function expectFirma(callable $f): void
    {
        try {
            $f();
            $this->fail('Se esperaba FirmaException');
        } catch (FirmaException) {
            $this->addToAssertionCount(1);
        }
    }
}
