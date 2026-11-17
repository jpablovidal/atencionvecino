<?php

namespace Tests\Feature;

use App\Models\Tramite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/** El sitio usa componentes oficiales del Framework kit de Gobierno (docs/KIT_GOBIERNO.md). */
class KitGobiernoTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    public function test_el_pie_sigue_la_estructura_footer_1_del_kit(): void
    {
        $h = $this->get('/')->assertOk()->getContent();
        $pie = explode('</footer>', explode('<footer', $h)[1])[0];
        foreach (['content-always-on-display', 'class="line"', 'Secciones', 'Enlaces internos', 'Atención', 'Santa Teresa', 'Accesibilidad', 'Privacidad y datos personales'] as $t) {
            $this->assertStringContainsString($t, $pie, $t);
        }
        $this->assertStringNotContainsString('href="https://www.santodomingo.cl"', $pie, 'sin enlaces externos en las páginas (CSP/enlaces)');
    }

    public function test_el_portal_usa_banners_y_colapsables_del_kit(): void
    {
        $h = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="banner mb-3"', $h);
        $this->assertStringContainsString('banner-icon cl cl-', $h);
        $this->assertStringContainsString('data-toggle="collapse"', $h);
        $this->assertSame(1, substr_count($h, 'aria-expanded="true"'), 'solo la primera pregunta abierta');
    }

    public function test_las_migas_usan_el_breadcrumb_del_kit_y_conservan_su_etiqueta(): void
    {
        $t = Tramite::query()->where('estado', 'publicado')->firstOrFail();
        $h = $this->followingRedirects()->get('/tramites/'.$t->id)->assertOk()->getContent();
        $this->assertStringContainsString('<ol class="breadcrumb">', $h);
        $this->assertStringContainsString('breadcrumb-item active', $h);
        $this->assertStringContainsString('Usted está en:', $h);
    }
}
