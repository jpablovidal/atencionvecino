<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Solicitud;
use App\Models\TransparenciaFeriado;
use App\Services\ExpedienteService;
use App\Services\FeriadoService;
use App\Services\SeguimientoService;
use App\Services\SolicitudService;
use App\Services\TramiteService;
use App\Support\Plazos;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Escenario;
use Tests\TestCase;

class FeriadosTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Plazos::olvidarFeriados();
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Santiago'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Plazos::olvidarFeriados();
        parent::tearDown();
    }

    private function svc(): FeriadoService
    {
        return app(FeriadoService::class);
    }

    private function falla(callable $fn, string $msg): void
    {
        try {
            $fn();
            $this->fail('debía rechazarse');
        } catch (FlujoException $e) {
            $this->assertStringContainsString($msg, mb_strtolower($e->getMessage()));
        }
    }

    public function test_solo_administracion_y_transparencia_administran_feriados(): void
    {
        foreach (['admin', 'transparencia'] as $rol) {
            auth()->forgetGuards();
            $this->comoFuncionario($this->usuario($rol))->get('/app/feriados')->assertOk()->assertSee('Administrador de feriados');
        }
        foreach (['funcionario', 'secretario', 'alcalde', 'auditor', 'autoridad', 'oirs'] as $rol) {
            auth()->forgetGuards();
            $t = $this->comoFuncionario($this->usuario($rol));
            $t->get('/app/feriados')->assertForbidden();
            $t->post('/app/feriados', ['fecha' => '2026-12-25', 'nombre' => 'Navidad', 'tipo' => 'nacional'])->assertForbidden();
            $t->post('/app/feriados/importar', ['lista' => '2026-12-25;Navidad'])->assertForbidden();
        }
        $this->assertSame(0, TransparenciaFeriado::query()->count());
        auth()->forgetGuards();
        $this->flushSession();
        $this->get('/app/feriados')->assertRedirect('/ingreso');
        $this->comoCiudadano()->get('/app/feriados')->assertRedirect('/ingreso');
    }

    public function test_alta_edicion_baja_y_auditoria(): void
    {
        $t = $this->comoFuncionario($this->usuario('admin'));
        $t->post('/app/feriados', ['fecha' => '2026-10-12', 'nombre' => '<b>Encuentro</b> de Dos Mundos', 'tipo' => 'nacional'])->assertSessionHas('ok');
        $f = TransparenciaFeriado::query()->first();
        $this->assertSame('Encuentro de Dos Mundos', $f->nombre);
        $this->assertSame('nacional', $f->tipo);
        $t->post('/app/feriados', ['fecha' => '2026-10-12', 'nombre' => 'Repetido', 'tipo' => 'nacional'])->assertSessionHas('error');
        $t->post('/app/feriados', ['fecha' => '2026-10-13', 'nombre' => 'X', 'tipo' => 'nacional'])->assertSessionHas('error');
        $t->post('/app/feriados', ['fecha' => '2026-10-13', 'nombre' => 'Día de cierre', 'tipo' => 'inventado'])->assertSessionHas('error');
        $t->post('/app/feriados', ['fecha' => '2026-02-30', 'nombre' => 'Fecha imposible', 'tipo' => 'nacional'])->assertSessionHasErrors('fecha');
        $t->post('/app/feriados', ['fecha' => '1999-01-01', 'nombre' => 'Muy antiguo', 'tipo' => 'nacional'])->assertSessionHas('error');
        $t->post("/app/feriados/{$f->id}", ['nombre' => 'Feriado editado', 'tipo' => 'comunal'])->assertSessionHas('ok');
        $this->assertSame('comunal', $f->refresh()->tipo);
        $t->post("/app/feriados/{$f->id}/eliminar")->assertSessionHas('ok');
        $this->assertSame(0, TransparenciaFeriado::query()->count());
        foreach (['feriado.agregar', 'feriado.editar', 'feriado.quitar'] as $a) {
            $this->assertDatabaseHas('audit_log', ['accion' => $a]);
        }
    }

    public function test_carga_masiva_todo_o_nada_y_omite_repetidos(): void
    {
        $adm = $this->usuario('admin');
        $r = $this->svc()->importar($adm, "2026-09-18;Fiestas Patrias;nacional\n2026-09-19;Glorias del Ejército\n\n2026-12-08;Inmaculada Concepción;regional");
        $this->assertSame(['agregados' => 3, 'omitidos' => 0], $r);
        $this->assertSame('nacional', TransparenciaFeriado::query()->where('fecha', '2026-09-19')->value('tipo'));
        $r = $this->svc()->importar($adm, "2026-09-18;Fiestas Patrias;nacional\n2026-12-25;Navidad");
        $this->assertSame(['agregados' => 1, 'omitidos' => 1], $r);
        $antes = TransparenciaFeriado::query()->count();
        $this->falla(fn () => $this->svc()->importar($adm, "2027-01-01;Año Nuevo\nmalo;Sin fecha\n2027-02-02;;"), 'línea 2');
        $this->assertSame($antes, TransparenciaFeriado::query()->count(), 'si una línea falla no se guarda ninguna');
        $this->falla(fn () => $this->svc()->importar($adm, implode("\n", array_map(fn ($i) => '2030-01-'.str_pad((string) ($i % 28 + 1), 2, '0', STR_PAD_LEFT).';Dia '.$i, range(1, 201)))), '200');
        $this->falla(fn () => $this->svc()->importar($adm, "   \n  "), 'no hay líneas');
        $this->falla(fn () => $this->svc()->importar($this->usuario('funcionario'), '2027-01-01;Año Nuevo'), 'permiso');
    }

    public function test_los_feriados_excluyen_dias_en_todos_los_plazos_de_tramites_y_servicios(): void
    {
        // Trámite 1 (Permiso de edificación, 30 días): sin feriados vs con feriados
        $s = Solicitud::query()->create(['folio' => 'SD-2026-F00001', 'run' => '20000001-'.Run::dv('20000001'), 'nombres' => 'V', 'apellidos' => 'P', 'correo' => 'v@e.cl', 'tramite_id' => 1, 'detalle' => 'detalle de prueba', 'canal_notificacion' => 'correo', 'estado' => 'ingresada']);
        $s->forceFill(['created_at' => Carbon::parse('2026-10-05', 'America/Santiago')])->saveQuietly();
        $s->refresh();
        $this->assertTrue((bool) $s->tramite->plazo_habiles, 'todos los trámites cuentan días administrativos');
        $sin = Plazos::deSolicitud($s)->format('Y-m-d');
        $this->assertSame('2026-11-16', $sin); // 30 hábiles desde el lunes 5-oct
        $this->svc()->agregar($this->usuario('admin'), '2026-10-12', 'Encuentro de Dos Mundos');
        $this->svc()->agregar($this->usuario('admin'), '2026-10-31', 'Día de las Iglesias Evangélicas'); // sábado: ya inhábil, no suma
        $this->svc()->agregar($this->usuario('admin'), '2026-11-02', 'Feriado administrativo', 'administrativo');
        $this->assertSame('2026-11-18', Plazos::deSolicitud($s)->format('Y-m-d'), 'dos feriados en día hábil agregan dos días; el sábado no');
        $fila = app(SeguimientoService::class)->filas(Solicitud::query()->get())->first();
        $this->assertSame('2026-11-18', $fila['vence']->format('Y-m-d'), 'el seguimiento usa el mismo cálculo');
        $this->assertSame('2026-11-18', Plazos::deSolicitud($s)->format('Y-m-d'));
        // quitar un feriado recalcula
        $this->svc()->quitar($this->usuario('admin'), TransparenciaFeriado::query()->where('fecha', '2026-11-02')->first());
        $this->assertSame('2026-11-17', Plazos::deSolicitud($s)->format('Y-m-d'));
    }

    public function test_el_plazo_para_responder_una_devolucion_tambien_es_en_dias_administrativos(): void
    {
        $this->svc()->agregar($this->usuario('admin'), '2026-10-15', 'Feriado de prueba');           // jueves
        $dom = $this->usuario('funcionario', 1);
        $s = Solicitud::query()->create(['folio' => 'SD-2026-F00002', 'run' => '20000002-'.Run::dv('20000002'), 'nombres' => 'V', 'apellidos' => 'P', 'correo' => 'v@e.cl', 'tramite_id' => 1, 'detalle' => 'detalle de prueba', 'canal_notificacion' => 'correo', 'estado' => 'ingresada']);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $dom);
        $d = app(SolicitudService::class)->devolver($s->refresh(), $dom, 'Falta un documento', 3);
        // hoy miércoles 14: hábiles jueves 15 (feriado), viernes 16, lunes 19, martes 20 → vence el 20
        $this->assertSame('2026-10-20', $d->vence_en->setTimezone('America/Santiago')->format('Y-m-d'));
    }

    public function test_tramites_nuevos_nacen_en_dias_administrativos_y_solo_la_administracion_puede_cambiarlo(): void
    {
        $adm = $this->usuario('admin');
        $svc = app(TramiteService::class);
        $a = $svc->crear($adm, ['nombre' => 'Trámite en días corridos', 'plazo_dias' => 10, 'unidad_propietaria_id' => 3, 'unidades' => [3], 'anexos' => []]);
        $this->assertFalse($a->plazo_habiles, 'la administración decidió explícitamente no marcarlo');
        $b = $svc->crear($adm, ['nombre' => 'Trámite en días hábiles', 'plazo_dias' => 10, 'unidad_propietaria_id' => 3, 'unidades' => [3], 'plazo_habiles' => 1, 'anexos' => []]);
        $this->assertTrue($b->plazo_habiles);
        $this->comoFuncionario($adm)->get('/app/tramites/nuevo')->assertOk()->assertSee('días administrativos')->assertSee('administrador de feriados');
    }

    public function test_simulador_muestra_el_vencimiento_y_los_dias_excluidos(): void
    {
        $this->svc()->agregar($this->usuario('admin'), '2026-10-12', 'Encuentro de Dos Mundos');
        $s = $this->svc()->simular('2026-10-05', 5);
        $this->assertSame('2026-10-13', $s['vence']->format('Y-m-d'));       // 5 hábiles saltando el lunes 12
        $this->assertSame('2026-10-10', $s['corridos']->format('Y-m-d'));
        $this->assertSame([['fecha' => '12-10-2026', 'motivo' => 'Encuentro de Dos Mundos']], $s['excluidos']);
        $h = $this->comoFuncionario($this->usuario('admin'))->get('/app/feriados?sim_fecha=2026-10-05&sim_dias=5')->assertOk()->getContent();
        $this->assertStringContainsString('13-10-2026', $h);
        $this->assertStringContainsString('Encuentro de Dos Mundos', $h);
        $this->get('/app/feriados?sim_fecha=mala&sim_dias=5')->assertSessionHasErrors('sim_fecha');
        $this->get('/app/feriados?sim_fecha=2026-10-05&sim_dias=0')->assertSessionHasErrors('sim_dias');
        $this->falla(fn () => $this->svc()->simular('2026-10-05', 999), 'entre 1 y 365');
    }

    public function test_pantalla_por_anio_menu_y_escape_de_textos(): void
    {
        $this->svc()->agregar($this->usuario('admin'), '2027-01-01', 'Año Nuevo');
        $c = $this->comoFuncionario($this->usuario('admin'));
        $c->get('/app/feriados?anio=2027')->assertOk()->assertSee('Año Nuevo');
        $c->get('/app/feriados?anio=2026')->assertOk()->assertDontSee('Año Nuevo');
        $c->get('/app/feriados?anio=1900')->assertSessionHasErrors('anio');
        $h = $c->get('/app')->getContent();
        $this->assertStringContainsString('app/feriados', $h);
        TransparenciaFeriado::query()->create(['fecha' => '2027-03-03', 'nombre' => '<script>alert(1)</script>', 'tipo' => 'nacional']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $c->get('/app/feriados?anio=2027')->getContent());
        auth()->forgetGuards();
        $this->assertStringNotContainsString('app/feriados', $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->getContent());
    }

    public function test_transparencia_ve_los_feriados_en_solo_lectura_con_enlace_al_modulo(): void
    {
        $this->svc()->agregar($this->usuario('admin'), '2026-12-25', 'Navidad');
        $h = $this->comoFuncionario($this->usuario('transparencia'))->get('/app/transparencia/plazos')->assertOk()->getContent();
        $this->assertStringContainsString('Navidad', $h);
        $this->assertStringContainsString(route('app.feriados'), $h);
        $this->assertStringNotContainsString('Agregar feriado', $h);
    }
}
