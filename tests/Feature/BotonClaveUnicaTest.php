<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class BotonClaveUnicaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private string $oficial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oficial = public_path('assets/img/claveunica/boton-claveunica.svg');
        @unlink($this->oficial);
    }

    protected function tearDown(): void
    {
        @unlink($this->oficial);
        parent::tearDown();
    }

    public function test_los_accesos_usan_el_componente_con_la_leyenda_oficial(): void
    {
        $r = $this->get('/ingreso')->assertOk();
        $r->assertSee('Ingresa con ClaveÚnica');
        $this->assertSame(2, substr_count($r->getContent(), 'data-claveunica'), 'vecino y funcionario');
        $this->get('/pagos-proveedores')->assertSee('data-claveunica', false);
        $this->get('/')->assertSee('data-claveunica', false); // encabezado
    }

    public function test_sin_el_boton_oficial_se_usa_uno_de_texto_y_con_el_archivo_se_usa_el_oficial(): void
    {
        $this->get('/ingreso')->assertDontSee('btn-claveunica-oficial', false);
        file_put_contents($this->oficial, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');
        $this->get('/ingreso')->assertSee('btn-claveunica-oficial', false)->assertSee('assets/img/claveunica/boton-claveunica.svg', false)->assertSee('alt="Ingresa con ClaveÚnica"', false);
    }

    public function test_el_boton_es_un_enlace_con_contexto_para_lectores_de_pantalla(): void
    {
        $this->get('/ingreso')->assertSee('como vecino(a)')->assertSee('como funcionario(a)');
    }
}
