<?php

namespace Tests\Feature;

use App\Services\Sistema\ComponentesService;
use App\Services\Sistema\RecursosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class SistemaRecursosComponentesTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function admin()
    {
        return $this->comoFuncionario($this->usuario('admin'));
    }

    // ------------------------------------------------------------------ recursos
    public function test_la_instantanea_tiene_estructura_y_valores_coherentes(): void
    {
        $s = app(RecursosService::class)->instantanea(false);
        foreach (['cpu', 'memoria', 'disco', 'php', 'bd', 'app', 'sistema'] as $k) {
            $this->assertArrayHasKey($k, $s);
        }
        $this->assertNull($s['cpu']['pct']); // sin medición pedida
        $this->assertSame(PHP_VERSION, $s['php']['version']);
        $this->assertGreaterThan(0, $s['php']['memoria_pico']);
        $this->assertSame('sqlite', $s['bd']['driver']);
        $this->assertSame(0, $s['app']['cola_fallida']);
        if ($s['memoria']['pct'] !== null) {
            $this->assertGreaterThanOrEqual(0, $s['memoria']['pct']);
            $this->assertLessThanOrEqual(100, $s['memoria']['pct']);
        }
        $cpu = app(RecursosService::class)->instantanea(true)['cpu']['pct'];
        $this->assertTrue($cpu === null || ($cpu >= 0 && $cpu <= 100));
    }

    public function test_niveles_y_formatos(): void
    {
        $this->assertSame('ok', RecursosService::nivel(10.0));
        $this->assertSame('aviso', RecursosService::nivel(70.0));
        $this->assertSame('critico', RecursosService::nivel(90.0));
        $this->assertSame('sin_dato', RecursosService::nivel(null));
        $this->assertSame('—', RecursosService::humano(null));
        $this->assertSame('1,0 KB', RecursosService::humano(1024));
        $this->assertSame('512 B', RecursosService::humano(512));
        $this->assertSame('2,5 MB', RecursosService::humano(2621440));
        $this->assertSame('1 d 1 h 1 min', RecursosService::duracion(90060));
    }

    public function test_el_muestreo_guarda_historico_y_depura(): void
    {
        DB::table('metricas_recursos')->insert(['ts' => now()->subDays(40)]);
        $this->artisan('recursos:muestrear')->assertSuccessful();
        $this->assertSame(1, DB::table('metricas_recursos')->count()); // la antigua se depuró
        $m = DB::table('metricas_recursos')->first();
        $this->assertNotNull($m->disco_total);
        $h = app(RecursosService::class)->historico(24);
        $this->assertCount(1, $h);
        // reducción de puntos
        for ($i = 0; $i < 400; $i++) {
            DB::table('metricas_recursos')->insert(['ts' => now()->subMinutes($i), 'cpu_pct' => 10]);
        }
        $this->assertLessThanOrEqual(144, count(app(RecursosService::class)->historico(24)));
    }

    public function test_el_panel_muestra_indicadores_historico_y_exige_permiso(): void
    {
        $this->artisan('recursos:muestrear');
        $this->artisan('recursos:muestrear');
        $r = $this->admin()->get('/app/sistema/recursos')->assertOk();
        foreach (['CPU del servidor', 'Memoria (RAM)', 'Disco del almacenamiento', 'Memoria PHP', 'Cola de trabajos', 'Estado:', 'Histórico', 'OPcache'] as $t) {
            $r->assertSee($t);
        }
        $r->assertSee('<svg', false)->assertSee('role="img"', false);
        $this->get('/app/sistema/recursos?rango=7d')->assertOk();
        $this->get('/app/sistema/recursos?rango=30d')->assertOk();
        $this->get('/app/sistema/recursos?rango=otro')->assertSessionHasErrors('rango');
        foreach (['funcionario', 'alcalde', 'secretario', 'auditor'] as $rol) {
            $this->comoFuncionario($this->usuario($rol))->get('/app/sistema/recursos')->assertForbidden();
        }
    }

    public function test_el_panel_funciona_sin_historico(): void
    {
        $this->admin()->get('/app/sistema/recursos')->assertOk()->assertSee('Aún no hay suficientes muestras');
    }

    // ------------------------------------------------------------------ componentes
    public function test_inventario_de_plataforma_paquetes_y_frontend(): void
    {
        $svc = app(ComponentesService::class);
        $p = $svc->plataforma();
        $this->assertSame(PHP_VERSION, $p['php']);
        $this->assertSame(app()->version(), $p['laravel']);
        $this->assertContains('openssl', array_column($p['extensiones'], 'nombre'));
        $this->assertArrayHasKey('OpenSSL', $p['bibliotecas']);
        $paq = collect($svc->paquetes());
        $this->assertTrue($paq->contains('nombre', 'laravel/framework'));
        $this->assertTrue($paq->contains('nombre', 'dompdf/dompdf'));
        $this->assertNotEmpty($paq->firstWhere('nombre', 'laravel/framework')['licencia']);
        $f = collect($svc->frontend())->keyBy('nombre');
        $this->assertSame('3.7.1', $f['jQuery']['version']);
        $this->assertSame('4.6.2', $f['Bootstrap (JS + Popper)']['version']);
        $this->assertSame('7.9.3', $f['TinyMCE']['version']);
        foreach ($f as $x) {
            $this->assertTrue($x['presente'], $x['nombre'].' no está');
            $this->assertSame('autoalojado', $x['origen']);
            $this->assertSame(64, strlen((string) $x['sha256']));
        }
        $this->assertContains('image', $svc->pluginsTiny());
    }

    public function test_no_hay_cdn_en_el_sistema(): void
    {
        $e = app(ComponentesService::class)->externos();
        $this->assertSame([], $e['cargados'], json_encode($e['cargados']));
        $this->assertGreaterThan(30, $e['archivos_revisados']);
        $this->assertStringContainsString("script-src 'self'", $e['csp']);
        $this->admin()->get('/app/sistema/componentes')->assertOk()->assertSee('Sin CDN ni recursos externos');
    }

    public function test_detecta_recursos_externos_inyectados(): void
    {
        $f = resource_path('views/_prueba_cdn.blade.php');
        file_put_contents($f, '<link rel="stylesheet" href="https://cdn.ejemplo.com/a.css"><script src="//cdn2.ejemplo.com/j.js"></script><img src="https://img.ejemplo.com/x.png">'
            .'<style>@import url("https://fonts.ejemplo.com/f.css"); .a{background:url(https://x.ejemplo.com/b.png)}</style><link rel="canonical" href="https://ok.cl/"><a href="https://sitio.cl/p">enlace</a>');
        try {
            $e = app(ComponentesService::class)->externos();
            $urls = implode(' ', array_column($e['cargados'], 'url'));
            foreach (['cdn.ejemplo.com/a.css', 'cdn2.ejemplo.com/j.js', 'img.ejemplo.com/x.png', 'fonts.ejemplo.com/f.css', 'x.ejemplo.com/b.png'] as $u) {
                $this->assertStringContainsString($u, $urls, $u);
            }
            $this->assertStringNotContainsString('ok.cl', $urls); // rel=canonical no carga nada
            $this->assertContains('sitio.cl/p', array_column($e['enlaces'], 'url')); // enlace de navegación: solo informativo
            $this->admin()->get('/app/sistema/componentes')->assertOk()->assertSee('recursos cargados desde otros orígenes')->assertSee('cdn.ejemplo.com');
        } finally {
            unlink($f);
        }
    }

    public function test_pagina_filtro_y_permisos_de_componentes(): void
    {
        $this->admin()->get('/app/sistema/componentes')->assertOk()->assertSee('laravel/framework')->assertSee('jQuery')->assertSee('7.9.3');
        $this->get('/app/sistema/componentes?q=dompdf')->assertSee('dompdf/dompdf')->assertDontSee('laravel/framework');
        $this->get('/app/sistema/componentes?q='.str_repeat('a', 61))->assertSessionHasErrors('q');
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/sistema/componentes')->assertOk(); // el auditor revisa la cadena de suministro
        foreach (['funcionario', 'alcalde', 'secretario'] as $rol) {
            $this->comoFuncionario($this->usuario($rol))->get('/app/sistema/componentes')->assertForbidden();
            $this->get('/app/sistema/componentes.json')->assertForbidden();
        }
    }

    public function test_exportar_inventario_json_se_audita(): void
    {
        $r = $this->admin()->get('/app/sistema/componentes.json')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $j = json_decode($r->getContent(), true);
        foreach (['plataforma', 'paquetes_php', 'frontend', 'recursos_externos', 'integraciones_servidor', 'plugins_tinymce'] as $k) {
            $this->assertArrayHasKey($k, $j);
        }
        $this->assertSame([], $j['recursos_externos']['cargados']);
        $this->assertDatabaseHas('audit_log', ['accion' => 'sistema.exportar_inventario']);
    }

    public function test_los_secretos_no_aparecen_en_el_inventario(): void
    {
        $txt = json_encode(app(ComponentesService::class)->inventario());
        $this->assertStringNotContainsString((string) config('app.key'), $txt);
        $this->assertStringNotContainsString((string) config('sgd.audit_hmac_key'), $txt);
        foreach (app(ComponentesService::class)->integraciones() as $dest) {
            $this->assertDoesNotMatchRegularExpression('/[?&](token|key|secret)=/i', $dest);
        }
    }
}
