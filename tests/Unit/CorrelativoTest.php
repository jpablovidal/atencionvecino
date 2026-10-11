<?php

namespace Tests\Unit;

use App\Services\CorrelativoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class CorrelativoTest extends TestCase
{
    use Escenario, RefreshDatabase;

    public function test_correlativo_por_tipo_y_reinicio_anual(): void
    {
        $this->sembrar();
        $c = new CorrelativoService;
        $this->assertSame(['numero' => 1, 'anio' => 2026], $c->siguiente(1, 2026));
        $this->assertSame(['numero' => 2, 'anio' => 2026], $c->siguiente(1, 2026));
        $this->assertSame(['numero' => 1, 'anio' => 2026], $c->siguiente(2, 2026), 'cada tipo lleva su propio correlativo');
        $this->assertSame(['numero' => 1, 'anio' => 2027], $c->siguiente(1, 2027), 'reinicia en 01 cada año');
        $this->assertSame(['numero' => 3, 'anio' => 2026], $c->siguiente(1, 2026));
    }

    public function test_el_cambio_de_anio_se_calcula_en_hora_de_chile(): void
    {
        $this->sembrar();
        $c = new CorrelativoService;
        // 31-dic 22:30 en Chile (verano, UTC-3) = 1-ene 01:30 UTC => sigue siendo 2026
        Carbon::setTestNow(Carbon::parse('2027-01-01 01:30:00', 'UTC'));
        $this->assertSame(2026, $c->siguiente(1)['anio']);
        // 1-ene 00:10 en Chile = 1-ene 03:10 UTC => ya es 2027 y parte en 1
        Carbon::setTestNow(Carbon::parse('2027-01-01 03:10:00', 'UTC'));
        $this->assertSame(['numero' => 1, 'anio' => 2027], $c->siguiente(1));
        Carbon::setTestNow();
    }

    public function test_codigo_de_validacion_formato_y_unicidad(): void
    {
        $vistos = [];
        for ($i = 0; $i < 2000; $i++) {
            $c = CorrelativoService::generarCodigo();
            $this->assertMatchesRegularExpression('/^SDD-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/', $c);
            $vistos[$c] = true;
        }
        $this->assertCount(2000, $vistos);
    }
}
