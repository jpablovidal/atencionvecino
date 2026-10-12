<?php

namespace Tests\Feature;

use App\Services\Sistema\ConexionesService;
use App\Support\GeoIp;
use App\Support\MapaMundo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Escenario;
use Tests\TestCase;

class ConexionesMapaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        config(['sgd.registrar_conexiones' => true]);
    }

    private function desde(string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    // ------------------------------------------------------------------ geolocalización local
    public function test_geolocalizacion_por_pais_sin_red(): void
    {
        $this->assertSame('US', GeoIp::pais('8.8.8.8'));
        $this->assertSame('AU', GeoIp::pais('1.1.1.1'));
        $this->assertSame('CL', GeoIp::pais('1.179.102.0'));
        $this->assertSame('US', GeoIp::pais('2001:0:abcd::1'));
        $this->assertSame('US', GeoIp::pais('::ffff:8.8.8.8'));
        foreach (['127.0.0.1', '10.1.2.3', '192.168.0.9', '172.16.5.5', '::1', '169.254.169.254'] as $ip) {
            $this->assertSame('LAN', GeoIp::pais($ip), $ip);
        }
        foreach (['no-es-ip', '', '999.1.1.1', "8.8.8.8'; DROP TABLE x"] as $mal) {
            $this->assertNull(GeoIp::pais($mal), $mal);
        }
        $this->assertSame('Chile', MapaMundo::nombre('CL'));
        $this->assertSame('Red interna / local', MapaMundo::nombre('LAN'));
        $this->assertGreaterThan(150, count(MapaMundo::datos()));
    }

    public function test_clase_de_color_logaritmica(): void
    {
        $this->assertSame(0, ConexionesService::clase(0, 1000));
        $this->assertSame(5, ConexionesService::clase(1000, 1000));
        $this->assertSame(1, ConexionesService::clase(1, 1000000));
        $this->assertGreaterThan(ConexionesService::clase(10, 10000), ConexionesService::clase(5000, 10000));
    }

    // ------------------------------------------------------------------ registro entrante
    public function test_cada_conexion_entrante_se_cuenta_por_ip_y_tipo_de_ruta_sin_guardar_la_url(): void
    {
        $this->desde('8.8.8.8')->get('/ingreso')->assertOk();
        $this->desde('8.8.8.8')->get('/ingreso')->assertOk();
        $this->desde('8.8.8.8')->get('/ruta-inexistente-secreta?token=abc')->assertNotFound();
        $this->desde('200.1.1.1')->get('/publico')->assertOk();
        $f = DB::table('conexiones_resumen')->where('clave', '8.8.8.8')->get();
        $this->assertSame(2, (int) $f->where('grupo', 'ingreso')->first()->hits);
        $this->assertSame(1, (int) $f->where('grupo', 'publico')->first()->errores);
        $this->assertSame('publico', DB::table('conexiones_resumen')->where('clave', '200.1.1.1')->value('grupo'));
        // no se guarda la URL, los parámetros ni el contenido
        $todo = json_encode(DB::table('conexiones_resumen')->get());
        foreach (['secreta', 'token', 'abc'] as $t) {
            $this->assertStringNotContainsString($t, $todo);
        }
    }

    public function test_no_cuenta_recursos_de_marca_ni_con_el_registro_desactivado_y_normaliza_ipv6(): void
    {
        $this->desde('8.8.4.4')->get('/marca/estilo.css');
        $this->assertSame(0, DB::table('conexiones_resumen')->count());
        config(['sgd.registrar_conexiones' => false]);
        $this->desde('8.8.4.4')->get('/ingreso');
        $this->assertSame(0, DB::table('conexiones_resumen')->count());
        config(['sgd.registrar_conexiones' => true]);
        $this->desde('2001:4860:4860:0:0:0:0:8888')->get('/ingreso');
        $this->assertSame('2001:4860:4860::8888', DB::table('conexiones_resumen')->value('clave'));
        // valores inválidos no se registran
        ConexionesService::registrar('entrante', 'no-es-ip', 'publico', false);
        ConexionesService::registrar('entrante', '', 'publico', false);
        $this->assertSame(1, DB::table('conexiones_resumen')->count());
    }

    public function test_funcionarios_y_ciudadanos_se_clasifican(): void
    {
        $this->desde('8.8.8.8')->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertOk();
        $this->desde('8.8.8.8')->comoCiudadano()->get('/ciudadano')->assertOk();
        $g = DB::table('conexiones_resumen')->where('clave', '8.8.8.8')->pluck('grupo')->all();
        $this->assertContains('funcionarios', $g);
        $this->assertContains('ciudadanos', $g);
    }

    // ------------------------------------------------------------------ registro saliente
    public function test_las_conexiones_salientes_guardan_solo_el_host(): void
    {
        Http::fake(['api.ejemplo.cl/*' => Http::response('x', 200), 'falla.ejemplo.cl/*' => Http::response('x', 500), 'caida.ejemplo.cl/*' => fn () => throw new ConnectionException('sin red')]);
        Http::get('https://api.ejemplo.cl/v1/privado?clave=SECRETO');
        Http::get('https://falla.ejemplo.cl/x');
        try {
            Http::get('https://caida.ejemplo.cl/x');
        } catch (\Throwable) {
        }
        $s = DB::table('conexiones_resumen')->where('direccion', 'saliente')->get()->keyBy('clave');
        $this->assertSame(0, (int) $s['api.ejemplo.cl']->errores);
        $this->assertSame(1, (int) $s['falla.ejemplo.cl']->errores);
        $this->assertSame(1, (int) $s['caida.ejemplo.cl']->errores);
        $this->assertStringNotContainsString('SECRETO', json_encode(DB::table('conexiones_resumen')->get()));
        $this->assertStringNotContainsString('privado', json_encode(DB::table('conexiones_resumen')->get()));
    }

    // ------------------------------------------------------------------ reporte
    private function fila(string $dir, string $clave, int $hits, int $err, string $grupo, string $cuando = 'now'): void
    {
        DB::table('conexiones_resumen')->insert(['bucket' => ($cuando === 'now' ? now() : now()->sub($cuando))->format('Y-m-d H:00:00'), 'direccion' => $dir, 'clave' => $clave, 'grupo' => $grupo, 'hits' => $hits, 'errores' => $err, 'ultimo' => now()]);
    }

    public function test_ranking_mapa_y_filtros(): void
    {
        $this->fila('entrante', '8.8.8.8', 500, 10, 'publico');
        $this->fila('entrante', '8.8.8.8', 100, 0, 'ingreso');
        $this->fila('entrante', '1.1.1.1', 50, 45, 'publico');
        $this->fila('entrante', '10.0.0.5', 30, 0, 'funcionarios');
        $this->fila('entrante', '1.179.102.0', 20, 0, 'ciudadanos');
        $this->fila('entrante', '9.9.9.9', 9999, 0, 'publico', '10 days'); // fuera de 24 h
        $this->fila('saliente', '8.8.4.4', 40, 4, 'integracion');
        $svc = app(ConexionesService::class);
        $r = $svc->reporte('24h', 'entrante');
        $this->assertSame(700, $r['total']);
        $this->assertSame('8.8.8.8', $r['ranking_ips'][0]->clave);
        $this->assertSame(600, (int) $r['ranking_ips'][0]->hits);
        $this->assertStringContainsString('publico', $r['ranking_ips'][0]->grupos);
        $this->assertSame(['US' => 600, 'AU' => 50, 'LAN' => 30, 'CL' => 20], $r['paises']);
        $this->assertSame(4, $r['ips_unicas']);
        $this->assertSame(3, $r['paises_n']); // LAN no cuenta como país
        // el rango de 30 días incluye la fila antigua y reordena el ranking
        $this->assertSame('9.9.9.9', $svc->reporte('30d', 'entrante')['ranking_ips'][0]->clave);
        // direcciones
        $s = $svc->reporte('24h', 'saliente');
        $this->assertSame(40, $s['total']);
        $this->assertSame('US', $s['ranking_destinos'][0]->pais);
        $this->assertSame(740, $svc->reporte('24h', 'todas')['total']);
        // caché de resolución para IP literal
        $this->assertSame('US', $svc->paisDeHost('8.8.4.4'));
        $this->assertDatabaseHas('geo_dns_cache', ['host' => '8.8.4.4', 'pais' => 'US']);
    }

    public function test_la_pagina_muestra_mapa_ranking_y_tabla_accesible(): void
    {
        $this->fila('entrante', '8.8.8.8', 500, 450, 'publico');
        $this->fila('entrante', '1.1.1.1', 50, 1, 'publico');
        $this->fila('saliente', '8.8.4.4', 40, 0, 'integracion');
        $r = $this->comoFuncionario($this->usuario('admin'))->get('/app/sistema/conexiones?direccion=entrante')->assertOk();
        $r->assertSee('Mapa por país')->assertSee('<svg viewBox="0 0 1000 500" role="img"', false)->assertSee('Ranking de IP con más conexiones')->assertSee('<code>8.8.8.8</code>', false)
            ->assertSee('Estados Unidos')->assertSee('posible escaneo')->assertSee('DB-IP.com');
        $this->assertStringContainsString('<title>Estados Unidos: 500 conexiones</title>', $r->getContent());
        $this->get('/app/sistema/conexiones?rango=7d&direccion=saliente')->assertOk()->assertDontSee('Ranking de IP con más conexiones')->assertSee('Destinos con más conexiones');
        $this->get('/app/sistema/conexiones?rango=otro')->assertSessionHasErrors('rango');
        $this->get('/app/sistema/conexiones?direccion=otra')->assertSessionHasErrors('direccion');
    }

    public function test_solo_administracion_y_auditoria_ven_las_conexiones(): void
    {
        foreach (['funcionario', 'alcalde', 'secretario'] as $rol) {
            $this->comoFuncionario($this->usuario($rol))->get('/app/sistema/conexiones')->assertForbidden();
        }
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/sistema/conexiones')->assertOk();
    }

    public function test_depuracion_por_retencion(): void
    {
        $this->fila('entrante', '8.8.8.8', 1, 0, 'publico');
        $this->fila('entrante', '8.8.8.8', 1, 0, 'publico', '100 days');
        config(['sgd.conexiones_retencion_dias' => 90]);
        $this->artisan('conexiones:depurar')->assertSuccessful();
        $this->assertSame(1, DB::table('conexiones_resumen')->count());
    }

    public function test_un_fallo_de_la_bd_al_contar_no_rompe_la_peticion(): void
    {
        Schema::drop('conexiones_resumen');
        $this->desde('8.8.8.8')->get('/ingreso')->assertOk();
    }
}
