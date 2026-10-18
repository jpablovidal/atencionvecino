<?php

namespace Tests\Feature;

use App\Models\Tramite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class PortalVecinoTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_el_inicio_es_un_portal_con_buscador_populares_y_direcciones(): void
    {
        $h = $this->get('/')->assertOk()->getContent();
        foreach (['portal-hero', 'role="search"', 'role="combobox"', 'Trámites más solicitados', 'Explore por dirección municipal', '¿Cómo funciona?', 'Otros servicios para el vecino', 'Más buscados'] as $t) {
            $this->assertStringContainsString($t, $h, $t);
        }
        $this->assertSame(1, substr_count($h, '<h1'), 'un solo h1');
        $this->assertStringNotContainsString('sgd-sidebar', $h, 'el público no usa menú lateral');
        $this->assertStringContainsString('assets/js/buscador.js', $h);
    }

    public function test_los_borradores_y_deshabilitados_no_aparecen_en_el_portal(): void
    {
        Tramite::query()->where('id', 2)->update(['estado' => 'borrador', 'activo' => false, 'nombre' => 'Certificado secreto de borrador']);
        foreach (['/', '/tramites', '/tramites/sugerencias?q=secreto'] as $r) {
            $this->assertStringNotContainsString('secreto de borrador', $this->get($r)->getContent(), $r);
        }
    }

    public function test_sugerencias_solo_publicados_con_limites_y_sin_cache(): void
    {
        $r = $this->getJson('/tramites/sugerencias?q=permiso')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('Permiso de edificación', $r->json('0.nombre'));
        $this->assertSame(['id', 'nombre', 'direccion', 'url'], array_keys($r->json('0')));
        $this->assertStringContainsString('/tramites/1/', $r->json('0.url'));
        $this->getJson('/tramites/sugerencias?q=p')->assertStatus(422);                       // mínimo 2 letras
        $this->getJson('/tramites/sugerencias?q='.str_repeat('a', 101))->assertStatus(422);
        $this->getJson('/tramites/sugerencias?q=%25%25')->assertOk()->assertExactJson([]);        // % literal, no comodín
        $this->assertCount(0, $this->getJson('/tramites/sugerencias?q=zzzzzz')->json());
        $t = Tramite::query()->find(1);
        $t->forceFill(['nombre' => 'Trámite <script>alert(1)</script> prueba'])->save();
        $this->assertStringNotContainsString('<script>', json_encode($this->getJson('/tramites/sugerencias?q=prueba')->json(), JSON_HEX_TAG));
        $codigos = json_encode($this->getJson('/tramites/sugerencias?q=DOM-PE')->json());
        $this->assertStringNotContainsString('DOM-PE', $codigos, 'el código interno no se busca ni se publica');
    }

    public function test_sugerencias_con_limite_de_tasa(): void
    {
        config(['sgd.validar_rate_per_minute' => 2]);
        $this->getJson('/tramites/sugerencias?q=permiso')->assertOk();
        $this->getJson('/tramites/sugerencias?q=permiso')->assertOk();
        $this->getJson('/tramites/sugerencias?q=permiso')->assertStatus(429);
    }

    public function test_catalogo_ordena_filtra_y_cuenta_por_direccion(): void
    {
        $h = $this->get('/tramites?orden=populares')->assertOk()->getContent();
        $this->assertStringContainsString('aria-current="true"', $h);
        $this->assertStringContainsString('Dirección municipal', $h);
        $this->assertStringContainsString('Más solicitados', $h);
        $this->get('/tramites?orden=inventado')->assertSessionHasErrors('orden');
        $this->get('/tramites?direccion=3&orden=nombre')->assertSee('Certificado de número')->assertSee('Permiso de edificación')->assertDontSee('beneficio social');
        $this->get('/tramites?q=edificaci&orden=recientes')->assertSee('Permiso de edificación')->assertSee('Quitar filtros');
    }

    public function test_la_ficha_muestra_requisitos_pasos_migas_y_relacionados(): void
    {
        $h = $this->get('/tramites/1/permiso-de-edificacion')->assertOk()->getContent();
        foreach (['Ruta de navegación', '¿Qué necesito?', '¿Cómo lo hago?', 'Realizar este trámite en línea', 'Dirección responsable', 'Otros trámites de esta dirección', 'Certificado de número'] as $t) {
            $this->assertStringContainsString($t, $h, $t);
        }
        $this->assertStringNotContainsString('DOM-PE', $h);
    }

    public function test_vecino_con_sesion_ve_el_portal_y_el_personal_el_menu_lateral(): void
    {
        $h = $this->comoCiudadano()->get('/ciudadano')->assertOk()->getContent();
        $this->assertStringContainsString('portal-hero', $h);
        $this->assertStringContainsString('role="combobox"', $h);
        $this->assertStringNotContainsString('sgd-sidebar', $h);
        $this->assertStringContainsString('Mis solicitudes', $h);
        auth()->forgetGuards();
        $this->flushSession();
        $h = $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->getContent();
        $this->assertStringContainsString('sgd-sidebar', $h);
        $this->assertStringNotContainsString('portal-hero', $h);
    }

    public function test_el_javascript_del_buscador_es_accesible_y_seguro(): void
    {
        $js = file_get_contents(public_path('assets/js/buscador.js'));
        foreach (['aria-activedescendant', 'aria-expanded', 'ArrowDown', 'Escape', 'textContent', 'encodeURIComponent'] as $k) {
            $this->assertStringContainsString($k, $js);
        }
        $this->assertStringNotContainsString('innerHTML', $js);
        $this->assertStringNotContainsString('eval(', $js);
    }
}
