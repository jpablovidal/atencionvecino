<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\Casilla\CasillaAdapter;
use App\Services\ExpedienteService;
use App\Services\NotificacionService;
use App\Support\Permisos;
use App\Support\ProductionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\Escenario;
use Tests\TestCase;

class CasillaPanelTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private const SEC = 'secreto-casilla-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Cache::flush();
    }

    private function api(): void
    {
        config(['sgd.casilla.mode' => 'api', 'sgd.casilla.api_url' => 'https://casilla.ejemplo.gob.cl/notificar', 'sgd.casilla.api_token' => null,
            'sgd.casilla.client_id' => 'cid-test', 'sgd.casilla.client_secret' => self::SEC, 'sgd.casilla.token_url' => 'https://auth.casilla.ejemplo.gob.cl/token']);
        $this->app->forgetInstance(CasillaAdapter::class);
        $this->app->forgetInstance(NotificacionService::class);
    }

    /** @return array{0:Expediente,1:Documento,2:Usuario} */
    private function cerrado(): array
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $doc = $this->documentoVigente($e, $dom);
        app(ExpedienteService::class)->cerrar($e);

        return [$e->refresh(), $doc, $dom];
    }

    public function test_permiso_y_roles_por_defecto(): void
    {
        $c = Permisos::catalogo()['casilla.gestionar'];
        $this->assertEqualsCanonicalizing(['funcionario', 'alcalde', 'secretario', 'transparencia', 'administrador_municipal', 'admin'], $c[2]);
        $this->assertEqualsCanonicalizing(['secretario', 'admin'], $c[4]);
        $this->assertTrue($this->usuario('secretario')->tienePermiso('casilla.gestionar'));
        $this->assertFalse($this->usuario('funcionario')->tienePermiso('casilla.gestionar'));
        $this->assertFalse($this->usuario('auditor')->tienePermiso('casilla.gestionar'));
    }

    public function test_panel_visible_segun_permiso_y_acciones_solo_con_gestionar(): void
    {
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/casilla')->assertOk()->assertSee('Casilla Única')->assertDontSee('Probar conexión');
        $this->comoFuncionario($this->usuario('secretario'))->get('/app/casilla')->assertOk()->assertSee('Probar conexión');
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/casilla')->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/casilla/diagnosticar')->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/casilla/reintentar')->assertForbidden();
    }

    public function test_sin_sesion_de_funcionario_no_entra(): void
    {
        $this->comoCiudadano()->get('/app/casilla')->assertRedirect('/ingreso');
        auth()->forgetGuards();
        $this->flushSession();
        $this->get('/app/casilla')->assertRedirect('/ingreso');
    }

    public function test_aparece_en_el_menu_solo_con_permiso(): void
    {
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertSee('app/casilla', false);
        $this->comoFuncionario($this->usuario('auditor'))->get('/app')->assertDontSee('app/casilla', false);
    }

    public function test_listado_acotado_a_la_unidad_y_evidencia_manual_en_outbox(): void
    {
        [$e, $doc, $dom] = $this->cerrado();
        $n = app(NotificacionService::class)->crear($e, [$doc->id], 'casilla_unica', $dom)[0];
        $this->assertSame('pendiente', $n->refresh()->estado);
        $this->comoFuncionario($dom)->get('/app/casilla')->assertSee($e->codigo);
        $otra = $this->usuario('funcionario', 2);
        $otra->forceFill(['unidad_id' => Unidad::query()->where('id', '!=', $dom->unidad_id)->value('id')])->save();
        $this->comoFuncionario($otra)->get('/app/casilla')->assertOk()->assertDontSee($e->codigo);
        $this->comoFuncionario($dom)->post("/app/notificaciones/{$n->id}/evidencia", ['tipo' => 'id_casilla_unica', 'valor' => 'CU-123'])->assertSessionHas('ok');
        $this->assertSame('enviada', $n->refresh()->estado);
        $this->comoFuncionario($dom)->get('/app/casilla')->assertSee('CU-123');
    }

    public function test_envio_por_api_con_oauth_y_reintento_de_fallidas(): void
    {
        $this->api();
        [$e, $doc, $dom] = $this->cerrado();
        Http::fake(['auth.casilla.ejemplo.gob.cl/*' => Http::response(['access_token' => 'TOK-C', 'expires_in' => 3600], 200),
            'casilla.ejemplo.gob.cl/notificar' => Http::sequence()->push([], 500)->push(['id' => 'CU-777'], 200)]);
        $n = app(NotificacionService::class)->crear($e, [$doc->id], 'casilla_unica', $dom)[0];
        $this->assertSame('fallida', $n->refresh()->estado);
        $this->assertStringNotContainsString('TOK-C', (string) $n->ultimo_error);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/notificar') && $r->hasHeader('Authorization', 'Bearer TOK-C'));
        $sec = $this->usuario('secretario');
        $sec->forceFill(['unidad_id' => $dom->unidad_id])->save();
        $this->comoFuncionario($sec)->post('/app/casilla/reintentar')->assertSessionHas('ok');
        $this->assertSame('enviada', $n->refresh()->estado);
        $this->assertSame('CU-777', $n->evidencia);
    }

    public function test_diagnostico_no_filtra_secretos_ni_envia_notificaciones(): void
    {
        $this->api();
        Http::fake(['auth.casilla.ejemplo.gob.cl/*' => Http::response(['access_token' => 'TOK-D', 'expires_in' => 60], 200)]);
        $r = $this->comoFuncionario($this->usuario('secretario'))->post('/app/casilla/diagnosticar');
        $r->assertRedirect();
        $html = $this->comoFuncionario($this->usuario('secretario'))->withSession(['diagnostico' => app(CasillaAdapter::class)->diagnosticar()])->get('/app/casilla')->getContent();
        $this->assertStringContainsString('obtenido', $html);
        $this->assertStringNotContainsString(self::SEC, $html);
        $this->assertStringNotContainsString('TOK-D', $html);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/notificar'));
        $this->assertDatabaseHas('audit_log', ['accion' => 'casilla.diagnostico']);
    }

    public function test_reintentar_en_outbox_no_hace_nada_y_token_https_obligatorio(): void
    {
        $this->comoFuncionario($this->usuario('secretario'))->post('/app/casilla/reintentar')->assertSessionHas('error');
        config(['sgd.casilla.mode' => 'api', 'sgd.casilla.client_id' => 'a', 'sgd.casilla.client_secret' => 'b', 'sgd.casilla.token_url' => 'http://inseguro.test/token', 'sgd.casilla.api_token' => null]);
        $this->assertFalse((new CasillaAdapter(config('sgd.casilla')))->usaOauth());
    }

    public function test_guardia_de_produccion_exige_https_y_credenciales(): void
    {
        config(['sgd.casilla.mode' => 'api', 'sgd.casilla.api_url' => 'http://x.test', 'sgd.casilla.api_token' => 'T']);
        $this->assertNotNull(ProductionGuard::casillaProblema());
        config(['sgd.casilla.api_url' => 'https://x.test', 'sgd.casilla.api_token' => null, 'sgd.casilla.client_id' => null]);
        $this->assertNotNull(ProductionGuard::casillaProblema());
        config(['sgd.casilla.api_token' => 'T']);
        $this->assertNull(ProductionGuard::casillaProblema());
        config(['sgd.casilla.mode' => 'outbox']);
        $this->assertNull(ProductionGuard::casillaProblema());
    }

    public function test_reintento_de_otro_canal_o_estado_se_rechaza(): void
    {
        $this->api();
        [$e, $doc, $dom] = $this->cerrado();
        $n = app(NotificacionService::class)->crear($e, [$doc->id], 'presencial', $dom)[0];
        $this->expectException(FlujoException::class);
        app(NotificacionService::class)->reintentarCasilla($n);
    }
}
