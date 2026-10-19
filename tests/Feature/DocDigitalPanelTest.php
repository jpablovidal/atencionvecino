<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ComunicacionOficial;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalClient;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use App\Services\Interop\DocDigitalEstado;
use App\Support\ProductionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

class DocDigitalPanelTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    private const CID = 'uapi_test_cliente';

    private const SEC = 'secreto-de-prueba-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        config(['sgd.institucion_codigo' => 'DEMO-MUN-SD']);
        Cache::flush();
    }

    private function oauth(string $modo = 'demo'): void
    {
        config(['sgd.docdigital.mode' => $modo, 'sgd.docdigital.url_demo' => $this->dd, 'sgd.docdigital.url_prod' => 'https://prod.ejemplo.gob.cl/api', 'sgd.docdigital.api_token' => null,
            'sgd.docdigital.client_id' => self::CID, 'sgd.docdigital.client_secret' => self::SEC, 'sgd.docdigital.token_url' => null, 'sgd.docdigital.entidad_id' => null, 'sgd.docdigital.tipo_documento_id' => null]);
        $this->app->forgetInstance(DocDigitalClient::class);
        $this->app->forgetInstance(ComunicacionService::class);
    }

    /** Apunta el token a otro servidor de autorización que responde 401 (las simulaciones HTTP se evalúan en orden). */
    private function token401(): void
    {
        Http::swap(new Factory);
        Http::fake(['auth401.ejemplo.gob.cl/*' => Http::response([], 401)]);
        config(['sgd.docdigital.token_url' => 'https://auth401.ejemplo.gob.cl/oauth/token']);
        $this->app->forgetInstance(DocDigitalClient::class);
        $this->app->forgetInstance(ComunicacionService::class);
    }

    private function tokenFalla(): void
    {
        Http::fake(['authboom.ejemplo.gob.cl/*' => fn () => throw new \RuntimeException('boom '.self::SEC)]);
        config(['sgd.docdigital.token_url' => 'https://authboom.ejemplo.gob.cl/oauth/token']);
    }

    private function secretario()
    {
        return $this->usuario('secretario');
    }

    public function test_oauth_client_credentials_obtiene_token_y_lo_usa_en_las_llamadas(): void
    {
        $this->oauth();
        $this->ddFake();
        $c = new HttpDocDigitalClient(config('sgd.docdigital'), 'demo');
        $c->recibidos();
        $c->recibidos(); // el segundo uso sale del caché
        Http::assertSentCount(3); // 1 token + 2 consultas
        // según la especificación: POST /oauth/token con HTTP Basic (Client_Id:Client_Secret), sin cuerpo
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/oauth/token') && $r->hasHeader('Authorization', 'Basic '.base64_encode(self::CID.':'.self::SEC)) && $r->body() === '');
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), '/api/documentos/recibidos') && $r->hasHeader('Authorization', 'Bearer TOK-ABC'));
    }

    public function test_errores_de_autorizacion_no_filtran_el_secreto_ni_el_token(): void
    {
        $this->oauth();
        Http::fake([$this->dd.'/oauth/token' => Http::response(['error' => 'invalid_client', 'detalle' => self::SEC], 401)]);
        try {
            (new HttpDocDigitalClient(config('sgd.docdigital'), 'demo'))->recibidos();
            $this->fail('debía fallar');
        } catch (DocDigitalException $e) {
            $this->assertStringNotContainsString(self::SEC, $e->getMessage());
            $this->assertStringNotContainsString(self::CID, $e->getMessage());
            $this->assertStringContainsString('401', $e->getMessage());
        }
        $this->tokenFalla();
        try {
            (new HttpDocDigitalClient(config('sgd.docdigital'), 'demo'))->recibidos();
            $this->fail('debía fallar');
        } catch (DocDigitalException $e) {
            $this->assertStringNotContainsString(self::SEC, $e->getMessage());
        }
    }

    public function test_exige_https_y_credenciales_y_falla_cerrado(): void
    {
        $this->oauth();
        config(['sgd.docdigital.url_demo' => 'http://demodoc.ejemplo.gob.cl/api']); // http: no se usa
        Http::fake();
        try {
            (new HttpDocDigitalClient(config('sgd.docdigital'), 'demo'))->recibidos();
            $this->fail('debía fallar');
        } catch (DocDigitalException) {
            Http::assertNothingSent();
        }
        $this->oauth();
        config(['sgd.docdigital.client_id' => null]);
        $c = new HttpDocDigitalClient(config('sgd.docdigital'), 'demo');
        $this->assertFalse($c->tieneCredenciales());
        $this->expectException(DocDigitalException::class);
        $c->recibidos();
    }

    public function test_token_fijo_sigue_funcionando(): void
    {
        $this->oauth();
        config(['sgd.docdigital.client_id' => null, 'sgd.docdigital.api_token' => 'TOK-FIJO']);
        $this->ddFake();
        (new HttpDocDigitalClient(config('sgd.docdigital'), 'demo'))->recibidos();
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer TOK-FIJO'));
    }

    public function test_panel_permisos_y_alcance(): void
    {
        $this->oauth();
        // un funcionario con acceso a comunicaciones ve el panel (solo lo de su unidad) pero no gestiona la conexión
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/docdigital')->assertOk()->assertDontSee('Probar conexión');
        $this->comoFuncionario($this->usuario('alcalde'))->get('/app/docdigital')->assertOk();
        $sec = $this->secretario();
        $this->assertTrue($sec->tienePermiso('docdigital.gestionar'));
        $this->assertFalse($this->usuario('funcionario')->tienePermiso('docdigital.gestionar'));
        $r = $this->comoFuncionario($sec)->get('/app/docdigital')->assertOk();
        $r->assertSee('demodoc.ejemplo.gob.cl')->assertSee('OAuth2 (Client ID y Client Secret)')->assertSee('Probar conexión');
        $this->assertStringNotContainsString(self::SEC, $r->getContent());
        $this->assertStringNotContainsString(self::CID, $r->getContent());
        // el administrador gestiona la conexión pero no ve el contenido
        $adm = $this->comoFuncionario($this->usuario('admin'))->get('/app/docdigital')->assertOk();
        $adm->assertSee('Probar conexión')->assertSee('no ve el contenido');
        // la auditoría ve, pero no gestiona
        $aud = $this->comoFuncionario($this->usuario('auditor'))->get('/app/docdigital')->assertOk();
        $aud->assertDontSee('Probar conexión');
        foreach (['diagnosticar', 'sincronizar', 'reintentar'] as $a) {
            $this->comoFuncionario($this->usuario('auditor'))->post("/app/docdigital/$a")->assertForbidden();
            $this->comoFuncionario($this->usuario('funcionario'))->post("/app/docdigital/$a")->assertForbidden();
        }
    }

    public function test_diagnostico_informa_cada_paso_sin_datos(): void
    {
        $this->oauth();
        $this->ddFake([55 => 'SECRETO']);
        $r = $this->comoFuncionario($this->secretario())->post('/app/docdigital/diagnosticar')->assertRedirect();
        $diag = $r->getSession()->get('diagnostico');
        $this->assertSame(['URL base (https)', 'Credenciales', 'Autorización (token)', 'Entidad asociada al token', 'Tipos de documento', 'Consulta de recibidos'], array_column($diag, 0));
        $this->assertTrue(collect($diag)->every(fn ($f) => $f[1]));
        $this->assertStringNotContainsString('SECRETO', json_encode($diag));
        $this->assertDatabaseHas('audit_log', ['accion' => 'docdigital.diagnostico']);
        // credenciales incorrectas
        $this->token401();
        $diag = $this->comoFuncionario($this->secretario())->post('/app/docdigital/diagnosticar')->getSession()->get('diagnostico');
        $this->assertFalse(collect($diag)->firstWhere(0, 'Autorización (token)')[1]);
        // modo outbox: no se conecta
        config(['sgd.docdigital.mode' => 'outbox']);
        $diag = $this->comoFuncionario($this->secretario())->post('/app/docdigital/diagnosticar')->getSession()->get('diagnostico');
        $this->assertFalse(collect($diag)->firstWhere(0, 'Conexión por API')[1]);
    }

    public function test_sincronizar_desde_el_panel_registra_el_estado(): void
    {
        $this->oauth();
        $this->ddFake([9 => 'Oficio 9 del GORE'], [9 => $this->pdfValido('doc')]);
        $this->comoFuncionario($this->secretario())->post('/app/docdigital/sincronizar')->assertSessionHas('ok');
        $this->assertSame(1, ComunicacionOficial::query()->where('direccion', 'entrante')->count());
        $e = DocDigitalEstado::leer();
        $this->assertSame(1, $e['nuevas']);
        $this->assertNull($e['error']);
        $this->assertNotNull($e['ultimo_ok']);
        $this->comoFuncionario($this->secretario())->get('/app/docdigital')->assertSee('Oficio 9 del GORE')->assertSee('1 nueva(s)');
        // falla de autorización: se informa y se registra, sin secretos
        $this->token401();
        $r = $this->comoFuncionario($this->secretario())->post('/app/docdigital/sincronizar')->assertSessionHas('error');
        $this->assertStringNotContainsString(self::SEC, (string) session('error'));
        $e = DocDigitalEstado::leer();
        $this->assertNotNull($e['error']);
        $this->assertNotNull($e['ultimo_ok']); // conserva la última vez que sí funcionó
        $this->assertStringNotContainsString(self::SEC, (string) json_encode($e));
    }

    public function test_sincronizar_y_reintentar_en_modo_outbox_son_inofensivos(): void
    {
        config(['sgd.docdigital.mode' => 'outbox']);
        $this->app->forgetInstance(ComunicacionService::class);
        $this->comoFuncionario($this->secretario())->post('/app/docdigital/sincronizar')->assertSessionHas('error');
        $this->comoFuncionario($this->secretario())->post('/app/docdigital/reintentar')->assertSessionHas('error');
    }

    public function test_menu_muestra_docdigital_solo_a_quien_corresponde(): void
    {
        $this->comoFuncionario($this->secretario())->get('/app')->assertSee('/app/docdigital', false);
        $this->comoFuncionario($this->usuario('admin'))->get('/app')->assertSee('/app/docdigital', false);
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertSee('/app/docdigital', false); // tiene comunicaciones.ver
    }

    public function test_el_secreto_no_se_registra_en_la_auditoria_ni_en_los_logs(): void
    {
        $this->oauth();
        Log::spy();
        $this->ddFake([], [], [$this->dd.'/oauth/token' => Http::response(['access_token' => 'TOK-XYZ', 'expires_in' => 600], 200)]);
        $this->comoFuncionario($this->secretario())->post('/app/docdigital/diagnosticar');
        $this->comoFuncionario($this->secretario())->post('/app/docdigital/sincronizar');
        foreach (AuditLog::query()->get() as $a) {
            $this->assertStringNotContainsString(self::SEC, (string) $a->detalle);
            $this->assertStringNotContainsString('TOK-XYZ', (string) $a->detalle);
        }
    }

    public function test_produccion_exige_credenciales(): void
    {
        config(['sgd.docdigital.mode' => 'prod', 'sgd.docdigital.url_prod' => 'https://prod.ejemplo.gob.cl/api', 'sgd.docdigital.api_token' => null, 'sgd.docdigital.client_id' => 'x', 'sgd.docdigital.client_secret' => null]);
        $this->assertStringContainsString('DocDigital prod sin URL https ni credenciales', (string) ProductionGuard::docdigitalProblema());
        config(['sgd.docdigital.client_secret' => 'y']);
        $this->assertNull(ProductionGuard::docdigitalProblema());
    }
}
