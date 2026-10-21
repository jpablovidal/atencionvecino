<?php

namespace Tests\Feature;

use App\Services\ClaveUnicaService;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Un solo ingreso (/login) con un solo botón de ClaveÚnica; funcionario(a) o vecino(a) lo decide el directorio. */
class LoginUnicoTest extends TestCase
{
    use Escenario, RefreshDatabase;

    public function test_login_redirige_a_claveunica_con_state_dinamico_y_scope_del_manual(): void
    {
        config(['sgd.claveunica.mode' => 'real', 'sgd.claveunica.client_id' => 'cid', 'sgd.claveunica.client_secret' => 's', 'sgd.claveunica.redirect_uri' => 'https://gestor.santodomingo.cl/claveunica/callback']);
        $this->app->forgetInstance(ClaveUnicaService::class);
        $r = $this->get('/login');
        $loc = (string) $r->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.claveunica.gob.cl/openid/authorize/?', $loc);
        $this->assertStringContainsString('scope=openid%20run%20name', $loc);
        parse_str((string) parse_url($loc, PHP_URL_QUERY), $q);
        $this->assertGreaterThanOrEqual(30, strlen($q['state']));
        $this->assertSame('auto', session('cu.via'));
        $otro = $this->get('/login')->headers->get('Location');
        $this->assertNotSame($loc, $otro, 'el state cambia en cada intento');
    }

    public function test_las_rutas_antiguas_redirigen_a_login_y_ya_no_hay_dos_botones(): void
    {
        foreach (['/ingreso/ciudadano', '/ingreso/funcionario', '/ingreso/auto'] as $u) {
            $this->get($u)->assertStatus(301)->assertRedirect('/login');
        }
        $h = $this->get('/ingreso')->assertOk()->getContent();
        $this->assertSame(1, substr_count($h, 'data-claveunica'));
        $this->assertStringContainsString('href="'.url('/login').'"', $h);
        $this->assertStringNotContainsString('Soy vecino', $h);
        $this->assertStringNotContainsString('Soy funcionario', $h);
        $inicio = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('Acceso funcionarios', $inicio);
        $this->assertStringNotContainsString('/ingreso/ciudadano', $inicio);
        $this->assertStringNotContainsString('/ingreso/funcionario', $inicio);
    }

    public function test_en_modo_simulado_login_lleva_al_formulario_de_desarrollo_con_ingreso_automatico(): void
    {
        config(['sgd.claveunica.mode' => 'mock']);
        $this->get('/login')->assertRedirect()->assertRedirectContains('/dev/login?via=auto');
    }

    public function test_el_callback_certificado_es_claveunica_callback_y_el_alias_sigue_vivo(): void
    {
        $this->assertSame('/claveunica/callback', parse_url(route('claveunica.callback'), PHP_URL_PATH));
        foreach (['/auth/callback', '/claveunica/callback'] as $u) {
            $this->get($u.'?code=abc&state=malo')->assertRedirect('/ingreso')->assertSessionHas('error');
        }
    }

    public function test_un_ingreso_completo_con_claveunica_por_el_callback_certificado(): void
    {
        $this->sembrar();
        config(['sgd.claveunica.mode' => 'real', 'sgd.claveunica.client_id' => 'cid', 'sgd.claveunica.client_secret' => 's', 'sgd.claveunica.redirect_uri' => 'https://gestor.santodomingo.cl/claveunica/callback']);
        $this->app->forgetInstance(ClaveUnicaService::class);
        Http::fake(['https://accounts.claveunica.gob.cl/openid/token/' => Http::response(['access_token' => 't']),
            'https://accounts.claveunica.gob.cl/openid/userinfo/' => Http::response(['RolUnico' => ['numero' => 20000001, 'DV' => (string) Run::dv('20000001')], 'name' => ['nombres' => ['Vera'], 'apellidos' => ['Vecina']]])]);
        $this->withSession(['cu' => ['state' => hash('sha256', 'st'), 'via' => 'auto', 'exp' => time() + 60]])->get('/claveunica/callback?code=c&state=st')->assertRedirect(route('ciudadano.index'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/openid/token/') && $r['redirect_uri'] === 'https://gestor.santodomingo.cl/claveunica/callback');
    }

    public function test_rutas_certificadas_por_defecto_y_retorno_de_logout(): void
    {
        $this->sembrar();
        config(['app.url' => 'https://gestor.santodomingo.cl', 'sgd.claveunica.redirect_uri' => null, 'sgd.claveunica.logout_uri' => null, 'sgd.claveunica.produccion_redirect_uri' => null, 'sgd.claveunica.produccion_logout_uri' => null, 'sgd.claveunica.client_id' => 'c', 'sgd.claveunica.client_secret' => 's']);
        $cu = new ClaveUnicaService(config('sgd.claveunica'));
        parse_str((string) parse_url($cu->urlAutorizacion('st'), PHP_URL_QUERY), $q);
        $this->assertSame('https://gestor.santodomingo.cl/claveunica/callback', $q['redirect_uri']);
        parse_str((string) parse_url($cu->urlCierre(), PHP_URL_QUERY), $c);
        $this->assertSame('https://gestor.santodomingo.cl/logout', $c['redirect']);
        // al volver de ClaveÚnica a /logout la sesión local queda cerrada y se muestra la portada
        $u = $this->usuario('funcionario');
        $this->actingAs($u)->get('/logout')->assertRedirect(route('inicio'));
        $this->assertGuest();
        $this->get('/logout')->assertRedirect(route('inicio')); // sin sesión: no falla
    }
}
