<?php

namespace Tests\Feature;

use App\Models\ApiConfig;
use App\Services\Admin\ApiConfigService;
use App\Services\ClaveUnicaService;
use App\Services\Firma\HttpFirmaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Configuración desde pantalla de ClaveÚnica, FirmaGob, Gmail y DocDigital: bloques por ambiente, secretos cifrados y verificaciones. */
class ConfiguracionPorPantallaTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Cache::flush();
    }

    private function admin()
    {
        return $this->comoFuncionario($this->usuario('admin'));
    }

    public function test_las_pantallas_muestran_bloques_por_ambiente_y_secretos_sin_valor(): void
    {
        $h = $this->admin()->get('/app/admin/api/claveunica')->assertOk()->getContent();
        foreach (['Credenciales — Sandbox / Testing', 'Credenciales — QA', 'Credenciales — Producción', 'Guardar credenciales de Producción', 'Datos para el trámite en Cerofilas', 'Referencia rápida', 'Ambiente activo'] as $t) {
            $this->assertStringContainsString($t, $h);
        }
        $this->assertSame(4, substr_count($h, 'type="password"'), 'un secreto por ambiente + el general');
        $f = $this->admin()->get('/app/admin/api/firmagob')->assertOk()->getContent();
        $this->assertStringContainsString('Credenciales de PRUEBAS', $f);
        $this->assertStringContainsString('Credenciales de PRODUCCIÓN', $f);
        $dd = $this->admin()->get('/app/admin/api/docdigital')->assertOk()->getContent();
        $this->assertStringContainsString('DemoDoc (pruebas)', $dd);
        $this->assertStringContainsString('Guardar credenciales de producción', $dd);
        $this->assertStringContainsString('Probar conexión', $dd);
        $gm = $this->admin()->get('/app/admin/api/google_mail')->assertOk()->getContent();
        $this->assertStringContainsString('Enviar correo de prueba', $gm);
        $this->assertStringContainsString('Delegación en todo el dominio', $gm);
    }

    public function test_credenciales_por_ambiente_de_claveunica_se_guardan_cifradas_y_se_aplican(): void
    {
        $this->admin()->post('/app/admin/api/claveunica', [
            'activo' => 1, 'mode' => 'real', 'ambiente' => 'sandbox',
            'sandbox_client_id' => 'sbx1234567', 'sandbox_client_secret' => 'SECRETO-SBX-999', 'sandbox_redirect_uri' => 'https://gestor.santodomingo.cl/claveunica/callback', 'sandbox_logout_uri' => 'https://gestor.santodomingo.cl/',
        ])->assertSessionHas('ok');
        $c = ApiConfig::query()->where('servicio', 'claveunica')->first();
        $this->assertStringNotContainsString('SECRETO-SBX-999', (string) $c->secretos.(string) $c->valores);
        $h = $this->admin()->get('/app/admin/api/claveunica')->getContent();
        $this->assertStringNotContainsString('SECRETO-SBX-999', $h);
        $this->assertStringContainsString('guardado, cifrado', $h);

        config(['sgd.claveunica.client_id' => null, 'sgd.claveunica.sandbox_client_id' => null, 'sgd.claveunica.sandbox_client_secret' => null]);
        ApiConfigService::aplicar();
        $svc = new ClaveUnicaService(config('sgd.claveunica'));
        $this->assertStringContainsString('client_id=sbx1234567', $svc->urlAutorizacion('est'));
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fgestor.santodomingo.cl%2Fclaveunica%2Fcallback', $svc->urlAutorizacion('est'));
        $this->assertSame('https://accounts.claveunica.gob.cl/api/v1/accounts/app/logout?redirect=https%3A%2F%2Fgestor.santodomingo.cl%2F', $svc->urlCierre());

        Http::fake(['https://accounts.claveunica.gob.cl/openid/token/' => Http::response(['access_token' => 't']), 'https://accounts.claveunica.gob.cl/openid/userinfo/' => Http::response(['RolUnico' => ['numero' => 44444444, 'DV' => '4'], 'name' => ['nombres' => ['A'], 'apellidos' => ['B']]])]);
        $svc->identificar('code', 'est');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/openid/token/') && $r['client_id'] === 'sbx1234567' && $r['client_secret'] === 'SECRETO-SBX-999');
    }

    public function test_un_client_id_de_ambiente_sin_su_secreto_no_toma_el_secreto_general(): void
    {
        $svc = new ClaveUnicaService(['ambiente' => 'qa', 'qa_client_id' => 'qa-id', 'client_secret' => 'GENERAL', 'client_id' => 'g', 'redirect_uri' => 'https://x', 'authorize_url' => 'https://a/', 'token_url' => 'https://a/token/', 'userinfo_url' => 'https://a/ui/', 'scope' => 'openid run name']);
        Http::fake(['*' => Http::response([], 500)]);
        try {
            $svc->identificar('c', 's');
        } catch (\Throwable) {
        }
        Http::assertSent(fn ($r) => $r['client_id'] === 'qa-id' && ($r['client_secret'] ?? '') === '');
    }

    public function test_firmagob_usa_las_credenciales_del_ambiente_activo(): void
    {
        $cfg = ['test_api_token_key' => 'TKEY', 'test_secret' => 'TSEC', 'prod_api_token_key' => 'PKEY', 'prod_secret' => 'PSEC', 'api_token_key' => 'GKEY', 'secret' => 'GSEC', 'entity' => 'E', 'purpose' => 'P', 'max_bytes' => 5 << 20, 'timeout' => 5,
            'urls' => ['test' => 'https://firma.test/t', 'prod' => 'https://firma.prod/t']];
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
        foreach (['test' => ['TKEY', 'TSEC'], 'prod' => ['PKEY', 'PSEC']] as $amb => [$key, $sec]) {
            Http::fake(['*' => Http::response(['files' => [['content' => base64_encode($pdf), 'checksum_original' => hash('sha256', $pdf)]]])]);
            (new HttpFirmaClient($cfg, $amb))->firmar($pdf, '12345678-5', '123456', 'x');
            Http::assertSent(function ($r) use ($key, $sec) {
                [$h, $p, $s] = explode('.', $r['token']);
                $esp = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", $sec, true)), '+/', '-_'), '=');

                return $r['api_token_key'] === $key && hash_equals($esp, $s);
            });
        }
    }

    public function test_validar_claveunica_revisa_el_ambiente_activo_y_el_dominio_gob_cl(): void
    {
        config(['sgd.claveunica.mode' => 'real', 'sgd.claveunica.ambiente' => 'produccion', 'sgd.claveunica.produccion_client_id' => 'abc', 'sgd.claveunica.produccion_client_secret' => 'x',
            'sgd.claveunica.produccion_redirect_uri' => 'https://gestor.santodomingo.cl/claveunica/callback', 'sgd.claveunica.produccion_logout_uri' => 'https://gestor.santodomingo.cl/']);
        $filas = collect($this->admin()->post('/app/admin/api/claveunica/probar')->assertSessionHas('pruebas')->getSession()->get('pruebas'));
        $gob = $filas->first(fn ($f) => str_contains($f[0], '.gob.cl'));
        $this->assertFalse($gob[1], 'santodomingo.cl no es .gob.cl');
        $this->assertTrue($filas->first(fn ($f) => $f[0] === 'client_id')[1]);
        $this->assertTrue($filas->first(fn ($f) => str_starts_with($f[0], 'Endpoints'))[1]);
        config(['sgd.claveunica.produccion_redirect_uri' => 'https://gestor.santodomingo.cl/cb?x=1']);
        $r = collect($this->admin()->post('/app/admin/api/claveunica/probar')->getSession()->get('pruebas'))->first(fn ($f) => $f[0] === 'Redirect URI');
        $this->assertFalse($r[1], 'con query no es válida');
    }

    public function test_probar_docdigital_hace_el_diagnostico_de_solo_lectura(): void
    {
        $this->ddConfig('demo');
        $this->ddFake();
        $filas = collect($this->admin()->post('/app/admin/api/docdigital/probar')->getSession()->get('pruebas'));
        $this->assertTrue($filas->first(fn ($f) => str_contains($f[0], 'Entidad'))[1]);
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET' && ! str_contains($r->url(), 'oauth/token'));
        config(['sgd.docdigital.mode' => 'outbox']);
        $o = collect($this->admin()->post('/app/admin/api/docdigital/probar')->getSession()->get('pruebas'));
        $this->assertFalse($o->first()[1]);
    }

    public function test_el_metodo_de_envio_de_correo_se_configura_y_hay_correo_de_prueba(): void
    {
        $this->admin()->post('/app/admin/api/google_mail', ['activo' => 1, 'mailer' => 'log', 'sender' => 'no-responder@santodomingo.cl'])->assertSessionHas('ok');
        config(['mail.default' => 'array']);
        ApiConfigService::aplicar();
        $this->assertSame('log', config('mail.default'));
        $f = collect($this->admin()->post('/app/admin/api-correo-prueba', ['destino' => 'prueba@santodomingo.cl'])->assertSessionHas('pruebas')->getSession()->get('pruebas'));
        $this->assertTrue($f->first()[1]);
        $this->assertStringContainsString('solo registro', $f->first()[2]);
        $this->admin()->post('/app/admin/api-correo-prueba', ['destino' => 'no-es-correo'])->assertSessionHasErrors('destino');
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/admin/api-correo-prueba', ['destino' => 'a@b.cl'])->assertForbidden();
    }
}
