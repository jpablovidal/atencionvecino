<?php

namespace Tests\Feature;

use App\Services\ClaveUnicaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
