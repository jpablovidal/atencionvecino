<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Subrogancia;
use App\Services\ExpedienteService;
use App\Services\SubroganciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Subrogancia entre fechas y horas: el subrogante gestiona los expedientes del titular mientras dure. */
class SubroganciaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00:00', 'America/Santiago'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function svc(): SubroganciaService
    {
        return app(SubroganciaService::class);
    }

    /** Expediente a cargo del titular (DIDECO), que el subrogante (otra dirección) no ve por derecho propio. */
    private function expedienteDelTitular($titular)
    {
        $s = $this->solicitudDOM();
        $titularDom = $this->usuario('funcionario', 1);

        return app(ExpedienteService::class)->crearDesdeSolicitud($s, $titularDom);
    }

    public function test_el_subrogante_ve_y_opera_el_expediente_solo_entre_las_fechas_y_horas(): void
    {
        $titular = $this->usuario('funcionario', 1); // DOM (unidad 3)
        $sub = $this->usuario('funcionario', 3); // DIDECO (otra unidad)
        $e = $this->expedienteDelTitular($titular);
        $e->forceFill(['unidad_id' => 3, 'responsable_id' => $titular->id])->save();
        $this->assertNotSame($sub->unidad_id, $e->unidad_id);

        $this->assertFalse($sub->can('ver', $e), 'sin subrogancia no lo ve');
        $this->svc()->crear($titular, $sub, now()->addHours(2), now()->addHours(30), 'Feriado legal', $this->usuario('admin'));
        $this->assertFalse($sub->fresh()->can('ver', $e), 'programada: todavía no');

        Carbon::setTestNow(now()->addHours(3));
        $this->assertTrue($sub->fresh()->can('ver', $e), 'vigente');
        $this->assertTrue($sub->fresh()->can('operar', $e));
        $r = $this->comoFuncionario($sub->fresh())->get('/app')->assertOk();
        $r->assertSee('Expedientes que subroga')->assertSee($e->codigo);
        $this->comoFuncionario($sub->fresh())->get('/app/expedientes/'.$e->id)->assertOk()->assertSee('subrogante de');

        Carbon::setTestNow(now()->addHours(40));
        $this->assertFalse($sub->fresh()->can('ver', $e), 'vencida: el acceso se cierra solo');
    }

    public function test_terminar_antes_de_tiempo_cierra_el_acceso_y_valida_reglas(): void
    {
        $titular = $this->usuario('funcionario', 1);
        $sub = $this->usuario('funcionario', 3);
        $admin = $this->usuario('admin');
        $s = $this->svc()->crear($titular, $sub, now()->subHour(), now()->addDays(2), null, $admin);
        $this->assertSame('vigente', $s->estado());
        $this->assertTrue($this->svc()->subrogaA($sub, $titular->id));
        $this->svc()->terminar($s, $admin);
        $this->assertSame('terminada', $s->fresh()->estado());
        $this->assertFalse($this->svc()->subrogaA($sub, $titular->id));
        foreach ([
            fn () => $this->svc()->crear($titular, $titular, now(), now()->addDay(), null, $admin),
            fn () => $this->svc()->crear($titular, $sub, now()->addDay(), now(), null, $admin),
            fn () => $this->svc()->crear($titular, $sub, now()->subDays(3), now()->subDay(), null, $admin),
            fn () => $this->svc()->crear($titular, $admin, now(), now()->addDay(), null, $admin),
            fn () => $this->svc()->crear($titular, $sub, now(), now()->addDays(400), null, $admin),
        ] as $caso) {
            try {
                $caso();
                $this->fail('debía rechazarse');
            } catch (FlujoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_no_permite_subrogancias_que_se_cruzan_para_el_mismo_titular(): void
    {
        $titular = $this->usuario('funcionario', 1);
        $admin = $this->usuario('admin');
        $this->svc()->crear($titular, $this->usuario('funcionario', 3), now(), now()->addDays(5), null, $admin);
        $this->expectException(FlujoException::class);
        $this->svc()->crear($titular, $this->usuario('funcionario', 2), now()->addDays(2), now()->addDays(8), null, $admin);
    }

    public function test_no_hereda_firmas_ni_cadenas_y_el_mantenedor_exige_permiso(): void
    {
        $titular = $this->usuario('funcionario', 1);
        $sub = $this->usuario('funcionario', 3);
        $admin = $this->usuario('admin');
        $this->svc()->crear($titular, $sub, now()->subHour(), now()->addDay(), null, $admin);
        $this->assertTrue($this->svc()->subrogaA($sub, $titular->id));
        $this->assertFalse($this->svc()->subrogaA($this->usuario('funcionario', 2), $titular->id));
        $this->comoFuncionario($sub)->get('/app/subrogancias')->assertForbidden();
        $this->comoFuncionario($admin)->get('/app/subrogancias')->assertOk()->assertSee('Subrogancias')->assertSee('Terminar ahora');
    }

    public function test_el_mantenedor_registra_y_termina_desde_la_pantalla(): void
    {
        $admin = $this->usuario('admin');
        $t = $this->usuario('funcionario', 1);
        $s = $this->usuario('funcionario', 3);
        $this->comoFuncionario($admin)->post('/app/subrogancias', ['titular_id' => $t->id, 'subrogante_id' => $s->id, 'desde' => '2026-10-24T08:30', 'hasta' => '2026-10-30T18:00', 'motivo' => 'Licencia'])->assertRedirect('/app/subrogancias');
        $sub = Subrogancia::query()->firstOrFail();
        $this->assertSame('2026-10-24 08:30:00', $sub->desde->format('Y-m-d H:i:s'));
        $this->assertSame('programada', $sub->estado());
        $this->assertDatabaseHas('audit_log', ['accion' => 'subrogancia.crear']);
        $this->comoFuncionario($admin)->post('/app/subrogancias', ['titular_id' => $t->id, 'subrogante_id' => $t->id, 'desde' => '2026-10-24T08:30', 'hasta' => '2026-10-25T08:30'])->assertSessionHas('error');
        $this->comoFuncionario($admin)->post('/app/subrogancias/'.$sub->id.'/terminar')->assertSessionHas('ok');
        $this->assertSame('terminada', $sub->fresh()->estado());
    }
}
