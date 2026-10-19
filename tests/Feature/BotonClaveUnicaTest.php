<?php

namespace Tests\Feature;

use App\Services\ClaveUnicaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Botón oficial de ClaveÚnica (kit de Gobierno Digital) y requisitos de certificación del manual 6.5 que se pueden verificar sin el servicio real. */
class BotonClaveUnicaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    public function test_los_accesos_usan_el_boton_oficial(): void
    {
        $r = $this->get('/ingreso')->assertOk();
        $this->assertSame(2, substr_count($r->getContent(), 'data-claveunica'), 'vecino y funcionario');
        $r->assertSee('btn-cu btn-m btn-color-estandar', false)->assertSee('class="cl-claveunica" aria-hidden="true"', false)->assertSee('assets/css/claveunica.css', false);
        $r->assertSee('Iniciar sesión con ClaveÚnica (como vecino(a))', false)->assertSee('Iniciar sesión con ClaveÚnica (como funcionario(a))', false);
        $this->get('/pagos-proveedores')->assertSee('btn-cu', false);
        $this->get('/')->assertSee('btn-color-highContrast', false); // encabezado oscuro: variante de alto contraste del kit
    }

    public function test_los_archivos_oficiales_estan_y_el_css_no_fue_alterado(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/claveunica.css'));
        $this->assertStringContainsString('#0F69C4', $css);
        $this->assertStringContainsString('#625AF6', $css); // alto contraste
        $this->assertStringContainsString('url(../img/claveunica/cu-blanco.svg)', $css); // única línea ajustada: ruta del ícono
        $svg = (string) file_get_contents(public_path('assets/img/claveunica/cu-blanco.svg'));
        $this->assertStringContainsString('claveunica', $svg);
        $this->assertStringContainsString('Atoms-/-Icons-/-white-/-24px-/-logo-/-claveunica', $svg);
    }

    public function test_el_boton_es_un_enlace_con_texto_alternativo_y_no_hay_boton_provisional(): void
    {
        $r = $this->get('/ingreso');
        $r->assertDontSee('btn-claveunica', false)->assertDontSee('btn-claveunica-ico', false);
        $this->assertStringNotContainsString('<img', substr($r->getContent(), (int) strpos($r->getContent(), 'data-claveunica'), 400));
    }

    public function test_los_endpoints_de_claveunica_son_accounts_y_el_scope_el_del_manual(): void
    {
        $c = config('sgd.claveunica');
        foreach (['authorize_url', 'token_url', 'userinfo_url', 'logout_url'] as $k) {
            $this->assertStringStartsWith('https://accounts.claveunica.gob.cl/', $c[$k], $k);
        }
        $this->assertSame('openid run name', $c['scope']);
        $url = (new ClaveUnicaService(array_merge($c, ['client_id' => 'abc', 'redirect_uri' => 'https://x.gob.cl/cb'])))->urlAutorizacion('estado');
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=openid%20run%20name', $url);
    }

    public function test_el_cierre_de_sesion_llama_al_logout_de_claveunica_solo_si_entro_con_claveunica(): void
    {
        $this->sembrar();
        config(['sgd.claveunica.logout_uri' => 'https://gestor.santodomingo.cl/']);
        $u = $this->usuario('funcionario', 1);
        $r = $this->actingAs($u)->withSession(['via_cu' => true, 'login_at' => time(), 'last_seen' => time()])->post('/salir');
        $r->assertRedirect('https://accounts.claveunica.gob.cl/api/v1/accounts/app/logout?redirect=https%3A%2F%2Fgestor.santodomingo.cl%2F');
        $this->assertGuest();
        $this->actingAs($u)->withSession(['login_at' => time(), 'last_seen' => time()])->post('/salir')->assertRedirect(route('inicio'));
    }

    public function test_la_csp_permite_el_formulario_de_cierre_hacia_claveunica(): void
    {
        $csp = (string) $this->get('/ingreso')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("form-action 'self' https://accounts.claveunica.gob.cl", $csp);
    }

    public function test_el_callback_usa_token_y_userinfo_de_accounts_y_cierra_la_sesion_si_el_run_no_esta_habilitado(): void
    {
        $this->sembrar();
        config(['sgd.claveunica.mode' => 'real', 'sgd.claveunica.client_id' => 'cid', 'sgd.claveunica.client_secret' => 'sec', 'sgd.claveunica.redirect_uri' => 'https://x.gob.cl/cb', 'sgd.claveunica.logout_uri' => 'https://x.gob.cl/']);
        $this->app->forgetInstance(ClaveUnicaService::class);
        Http::fake([
            'https://accounts.claveunica.gob.cl/openid/token/' => Http::response(['access_token' => 't']),
            'https://accounts.claveunica.gob.cl/openid/userinfo/' => Http::response(['RolUnico' => ['numero' => 12345678, 'DV' => '5'], 'name' => ['nombres' => ['Ana'], 'apellidos' => ['Pérez']]]),
        ]);
        $r = $this->withSession(['cu' => ['state' => hash('sha256', 'abc'), 'via' => 'funcionario', 'exp' => time() + 60]])->get('/claveunica/callback?code=c&state=abc');
        $r->assertRedirectContains('https://accounts.claveunica.gob.cl/api/v1/accounts/app/logout?redirect=');
        $this->assertGuest();
        Http::assertSent(fn ($q) => $q->url() === 'https://accounts.claveunica.gob.cl/openid/token/' && $q['state'] === 'abc' && $q['grant_type'] === 'authorization_code' && $q['code'] === 'c');
        Http::assertSent(fn ($q) => $q->url() === 'https://accounts.claveunica.gob.cl/openid/userinfo/' && $q->method() === 'POST');
    }
}
