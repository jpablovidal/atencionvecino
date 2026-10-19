<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\ComunicacionOficial;
use App\Models\Tramite;
use App\Models\TransparenciaFeriado;
use App\Models\Unidad;
use App\Services\Admin\AreaService;
use App\Services\ExpedienteService;
use App\Services\Interop\DocDigitalDashboardService;
use App\Services\SolicitudService;
use App\Services\TiemposRespuestaService;
use App\Support\Plazos;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Escenario;
use Tests\TestCase;

class TiemposDocDigitalDashboardTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Plazos::olvidarFeriados();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Plazos::olvidarFeriados();
        parent::tearDown();
    }

    /** Solicitud del trámite $tid ingresada $ingreso y cerrada $cierre por el funcionario de la dirección. */
    private function terminada(int $tid, string $run, string $ingreso, ?string $cierre, int $funcionario): void
    {
        $t = Tramite::query()->with('anexos')->findOrFail($tid);
        Carbon::setTestNow(Carbon::parse($ingreso, 'America/Santiago'));
        $anexos = $t->anexos->mapWithKeys(fn ($a) => [$a->id => $this->archivo('a'.$a->id.'.pdf', $this->pdfValido($run.$a->id))])->all();
        $s = app(SolicitudService::class)->ingresar(['run' => $run.'-'.Run::dv($run), 'nombres' => 'V', 'apellidos' => 'T'], $t, 'v@example.invalid', 'Solicitud de prueba de tiempos de respuesta', 'correo', $anexos, []);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->usuario('funcionario', $funcionario));
        if ($cierre) {
            Carbon::setTestNow(Carbon::parse($cierre, 'America/Santiago'));
            app(ExpedienteService::class)->cerrar($e);
        }
    }

    private function escenario(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'America/Santiago'));
        $ids = Unidad::query()->whereIn('codigo', ['DOM', 'DIDECO'])->pluck('id', 'codigo');
        $admin = $this->usuario('admin');
        app(AreaService::class)->guardar($admin, ['salud' => ['habilitada' => 1]]);
        app(AreaService::class)->asignarUnidad(Unidad::query()->find($ids['DOM']), Area::query()->where('codigo', 'municipal')->value('id'));
        app(AreaService::class)->asignarUnidad(Unidad::query()->find($ids['DIDECO']), Area::query()->where('codigo', 'salud')->value('id'));
        // DOM (trámite 1): lunes → viernes = 4 días corridos; DIDECO (trámite 2): lunes → lunes siguiente = 7 corridos
        $this->terminada(1, '20000011', '2026-10-05 09:00', '2026-10-09 09:00', 1);
        $this->terminada(3, '20000012', '2026-10-05 09:00', '2026-10-12 09:00', 3);
        // sin terminar: no cuenta
        $this->terminada(1, '20000013', '2026-10-06 09:00', null, 1);
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'America/Santiago'));
    }

    public function test_tiempos_por_direccion_area_y_municipio(): void
    {
        $this->escenario();
        $t = app(TiemposRespuestaService::class)->calcular();
        $this->assertSame(2, $t['municipio']['n'], 'solo cuentan las terminadas');
        $this->assertEqualsWithDelta(5.5, $t['municipio']['corridos'], 0.1);
        $this->assertCount(2, $t['direcciones']);
        $dom = collect($t['direcciones'])->firstWhere('nombre', 'Dirección de Obras Municipales');
        $did = collect($t['direcciones'])->firstWhere('nombre', 'Dirección de Desarrollo Comunitario');
        $this->assertEqualsWithDelta(4.0, $dom['corridos'], 0.1);
        $this->assertEqualsWithDelta(7.0, $did['corridos'], 0.1);
        $this->assertSame(4.0, $dom['habiles']);
        $this->assertSame(5.0, $did['habiles']);
        $this->assertEqualsWithDelta(4.5, $t['municipio']['habiles'], 0.05);
        $nombres = collect($t['areas'])->pluck('nombre')->all();
        $this->assertContains('Área municipal', $nombres);
        $this->assertContains('Área de salud', $nombres);
        $this->assertSame(1, collect($t['areas'])->firstWhere('nombre', 'Área de salud')['n']);
    }

    public function test_los_feriados_no_cuentan_como_dias_habiles_en_el_indicador(): void
    {
        $this->escenario();
        TransparenciaFeriado::query()->create(['fecha' => '2026-10-12', 'nombre' => 'Encuentro de Dos Mundos', 'tipo' => 'nacional']);
        Plazos::olvidarFeriados();
        $did = collect(app(TiemposRespuestaService::class)->calcular()['direcciones'])->firstWhere('nombre', 'Dirección de Desarrollo Comunitario');
        $this->assertSame(4.0, $did['habiles'], 'el feriado del 12 no cuenta');
    }

    public function test_la_ventana_excluye_lo_antiguo(): void
    {
        $this->escenario();
        $this->assertSame(0, app(TiemposRespuestaService::class)->calcular(null, 3)['municipio']['n']);
        $this->assertNull(app(TiemposRespuestaService::class)->calcular(null, 3)['municipio']['habiles']);
    }

    public function test_el_dashboard_general_muestra_el_indicador_a_quienes_corresponde(): void
    {
        $this->escenario();
        foreach (['autoridad', 'alcalde', 'admin', 'oirs'] as $rol) {
            $this->comoFuncionario($this->usuario($rol))->get('/app')->assertOk()->assertSee('Tiempo de respuesta por área')->assertSee('Tiempo de respuesta por dirección municipal')->assertSee('Todo el municipio')
                ->assertSee('Días hábiles en promedio que demora el municipio');
        }
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertOk()->assertDontSee('Tiempo de respuesta por área');
    }

    public function test_dashboard_de_docdigital(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'America/Santiago'));
        $mk = fn (array $x) => ComunicacionOficial::query()->create($x + ['asunto' => 'Asunto', 'estado' => 'recibida', 'evidencia_en' => now()->subDays(1), 'unidad_id' => 3]);
        $a = $mk(['direccion' => 'entrante', 'evidencia_tipo' => 'docdigital_id', 'evidencia' => 'E1', 'institucion_nombre' => 'Gobierno Regional', 'acuse_estado' => 'registrado', 'acuse_tipo' => 'manual', 'acuse_en' => now()->subDays(1)->addHours(5), 'evidencia_en' => now()->subDays(1)]);
        $mk(['direccion' => 'entrante', 'evidencia_tipo' => 'docdigital_id', 'evidencia' => 'E2', 'institucion_nombre' => 'SEREMI', 'acuse_estado' => 'pendiente', 'evidencia_en' => now()->subDays(6)]);
        $mk(['direccion' => 'saliente', 'evidencia_tipo' => 'docdigital_id', 'evidencia' => 'S1', 'institucion_nombre' => 'Gobierno Regional', 'estado' => 'enviada', 'responde_a_id' => $a->id, 'created_at' => now()->subHours(12)]);
        $mk(['direccion' => 'saliente', 'evidencia_tipo' => 'docdigital_manual', 'evidencia' => 'S2', 'institucion_nombre' => 'SEREMI', 'estado' => 'fallida']);
        $p = app(DocDigitalDashboardService::class)->para($this->usuario('funcionario'));
        $k = collect($p['kpis'])->keyBy('etiqueta');
        $this->assertSame(2, $k['Comunicaciones recibidas']['valor']);
        $this->assertSame(2, $k['Comunicaciones enviadas']['valor']);
        $this->assertSame(1, $k['Acuses atrasados (más de 3 días)']['valor']);
        $this->assertSame(1, $k['Envíos fallidos']['valor']);
        $this->assertSame('5,0 h', $k['Tiempo medio para registrar el acuse']['valor']);
        $this->assertCount(5, $p['bloques']);
        // otra dirección no ve esas comunicaciones; el auditor ve todo
        $this->assertSame(0, collect(app(DocDigitalDashboardService::class)->para($this->usuario('funcionario', 3))['kpis'])->firstWhere('etiqueta', 'Comunicaciones recibidas')['valor']);
        $this->assertSame(2, collect(app(DocDigitalDashboardService::class)->para($this->usuario('auditor'))['kpis'])->firstWhere('etiqueta', 'Comunicaciones recibidas')['valor']);
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/docdigital')->assertOk()->assertSee('Panel de DocDigital')->assertSee('Instituciones que más nos escriben')->assertSee('Gobierno Regional');
    }
}
