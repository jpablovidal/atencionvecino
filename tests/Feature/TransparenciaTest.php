<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\AuditLog;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\TransparenciaActivaItem;
use App\Models\TransparenciaActivaRegistro;
use App\Models\TransparenciaDerivacion;
use App\Models\TransparenciaFeriado;
use App\Models\TransparenciaRequerimiento;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\SeguimientoService;
use App\Services\TramiteService;
use App\Services\TransparenciaReporte;
use App\Services\TransparenciaService;
use App\Support\Plazos;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class TransparenciaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Plazos::olvidarFeriados();
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Santiago')); // miércoles
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Plazos::olvidarFeriados();
        parent::tearDown();
    }

    private function transp(): Usuario
    {
        return $this->usuario('transparencia');
    }

    private function svc(): TransparenciaService
    {
        return app(TransparenciaService::class);
    }

    private function sai(string $cuerpo = '20000001', ?string $ingreso = null, int $tramite = 5): Solicitud
    {
        $s = Solicitud::query()->create(['folio' => 'SD-2026-S'.str_pad((string) ++$this->n, 5, '0', STR_PAD_LEFT), 'run' => $cuerpo.'-'.Run::dv($cuerpo), 'nombres' => 'Vecino'.$this->n, 'apellidos' => 'Secreto',
            'correo' => 'v'.$this->n.'@example.com', 'tramite_id' => $tramite, 'detalle' => 'Solicito copia del contrato de aseo '.$this->n, 'canal_notificacion' => 'correo', 'estado' => 'ingresada']);
        if ($ingreso) {
            $s->forceFill(['created_at' => Carbon::parse($ingreso, 'America/Santiago')])->saveQuietly();
        }

        return $s->refresh();
    }

    private function asignada(?Solicitud $s = null): Solicitud
    {
        $s ??= $this->sai();
        app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->transp());

        return $s->refresh()->load('expediente', 'tramite');
    }

    private function permisoDenegado(callable $fn, string $msg = 'permiso'): void
    {
        try {
            $fn();
            $this->fail('debía rechazarse');
        } catch (FlujoException $e) {
            $this->assertStringContainsString($msg, mb_strtolower($e->getMessage()));
        }
    }

    // ------------------------------------------------------------------ rol y permisos
    public function test_rol_del_sistema_y_permisos(): void
    {
        $this->assertSame(1, Rol::query()->where('clave', 'transparencia')->where('sistema', 1)->count());
        $t = $this->transp();
        $this->assertTrue($t->operaExpedientes());
        foreach (['transparencia.ver', 'transparencia.gestionar', 'transparencia.responder', 'transparencia.reportes', 'expedientes.operar', 'expedientes.derivar', 'expedientes.cerrar', 'documentos.crear', 'notificaciones.gestionar', 'comunicaciones.despachar', 'vigentes.ver'] as $p) {
            $this->assertTrue($t->tienePermiso($p), $p);
        }
        foreach (['mantenedores.gestionar', 'roles.gestionar', 'logs.ver', 'seguimiento.consultar', 'oirs.ver', 'docdigital.gestionar'] as $p) {
            $this->assertFalse($t->tienePermiso($p), $p);
        }
        $f = $this->usuario('funcionario');
        $this->assertTrue($f->tienePermiso('transparencia.responder'));
        foreach (['transparencia.ver', 'transparencia.gestionar', 'transparencia.reportes'] as $p) {
            $this->assertFalse($f->tienePermiso($p), 'funcionario no debe tener '.$p);
            $this->assertFalse($this->usuario('admin')->tienePermiso($p));
            $this->assertFalse($this->usuario('auditor')->tienePermiso($p));
        }
        $this->assertFalse($this->usuario('autoridad')->tienePermiso('transparencia.responder'));
        $this->assertFalse($this->usuario('oirs')->tienePermiso('transparencia.responder'));
    }

    public function test_un_rol_personalizado_de_base_transparencia_respeta_su_techo(): void
    {
        $rol = Rol::query()->create(['clave' => 'transp_lector', 'nombre' => 'Lector', 'descripcion' => '', 'rol_base' => 'transparencia', 'sistema' => false, 'activo' => true]);
        foreach (['transparencia.ver', 'transparencia.gestionar', 'roles.gestionar', 'oirs.ver'] as $p) {
            DB::table('rol_permisos')->insert(['rol_id' => $rol->id, 'permiso' => $p]);
        }
        $u = $this->transp();
        $u->forceFill(['rol' => 'transp_lector'])->save();
        $u = Usuario::query()->find($u->id);
        $this->assertTrue($u->tienePermiso('transparencia.ver'));
        $this->assertTrue($u->tienePermiso('transparencia.gestionar'));
        $this->assertFalse($u->tienePermiso('roles.gestionar'));
        $this->assertFalse($u->tienePermiso('oirs.ver'));
    }

    public function test_matriz_de_acceso(): void
    {
        $s = $this->asignada();
        $ok = ['/app/transparencia', '/app/transparencia/requerimientos', '/app/transparencia/activa', '/app/transparencia/plazos', '/app/transparencia/reportes'];
        foreach ($ok as $r) {
            auth()->forgetGuards();
            $this->comoFuncionario($this->transp())->get($r)->assertOk();
        }
        foreach (['funcionario', 'admin', 'autoridad', 'oirs', 'auditor'] as $rol) {
            auth()->forgetGuards();
            $t = $this->comoFuncionario($this->usuario($rol));
            foreach (['/app/transparencia', '/app/transparencia/activa', '/app/transparencia/plazos', '/app/transparencia/reportes'] as $r) {
                $t->get($r)->assertForbidden();
            }
            $t->post("/app/transparencia/solicitudes/{$s->folio}/prorroga", ['motivo' => 'motivo suficiente'])->assertForbidden();
            $t->post("/app/transparencia/solicitudes/{$s->folio}/requerimientos", ['unidad_id' => 3, 'instruccion' => 'entregue la información'])->assertForbidden();
            $t->post('/app/transparencia/plazos', ['prorroga_dias' => 10])->assertForbidden();
        }
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/transparencia/requerimientos')->assertOk(); // responde por su dirección
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('oirs'))->get('/app/transparencia/requerimientos')->assertForbidden();
        auth()->forgetGuards();
        $this->flushSession();
        $this->get('/app/transparencia')->assertRedirect('/ingreso');
        $this->comoCiudadano()->get('/app/transparencia')->assertRedirect('/ingreso');
    }

    // ------------------------------------------------------------------ flujo de la solicitud
    public function test_la_solicitud_de_acceso_llega_a_transparencia_y_se_gestiona_como_expediente(): void
    {
        $s = $this->sai();
        $t = $this->comoFuncionario($this->transp());
        $t->get('/app')->assertOk()->assertSee($s->folio);                       // bandeja: por asignar
        $this->usuario('funcionario')->unidad_id !== $this->transp()->unidad_id || $this->fail('unidades distintas');
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertDontSee($s->folio);
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/solicitudes/'.$s->folio)->assertForbidden();
        auth()->forgetGuards();
        $r = $this->comoFuncionario($this->transp())->post('/app/solicitudes/'.$s->folio.'/asignar');
        $e = $s->refresh()->expediente;
        $r->assertRedirect(route('app.expediente', $e));
        $h = $this->get('/app/expedientes/'.$e->id)->assertOk()->getContent();
        $this->assertStringContainsString('Transparencia pasiva (Ley 20.285)', $h);
        $this->assertStringContainsString('Vence el', $h);
        $this->assertStringContainsString('Requerir información a una dirección', $h);
        $this->assertStringContainsString('Declarar incompetencia', $h);
        $this->get('/app/transparencia')->assertOk()->assertSee($s->folio);
    }

    public function test_la_ficha_de_transparencia_solo_se_ve_con_el_permiso_y_en_solicitudes_de_acceso(): void
    {
        $normal = $this->sai('20000002', null, 1); // DOM, no es de transparencia
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($normal, $dom);
        $h = $this->comoFuncionario($dom)->get('/app/expedientes/'.$e->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('Transparencia pasiva', $h);
    }

    // ------------------------------------------------------------------ plazos hábiles, feriados y prórroga
    public function test_plazo_de_20_dias_habiles_con_feriados_y_prorroga(): void
    {
        $s = $this->sai('20000001', '2026-10-05'); // lunes
        $this->assertSame('2026-11-02', Plazos::deSolicitud($s)->format('Y-m-d'));
        $this->svc()->agregarFeriado($this->transp(), '2026-10-12', 'Encuentro de Dos Mundos (ejemplo)');
        $this->assertSame('2026-11-03', Plazos::deSolicitud($s)->format('Y-m-d'));
        $s->forceFill(['prorroga_dias' => 10])->save();
        $this->assertSame('2026-11-17', Plazos::deSolicitud($s->refresh())->format('Y-m-d')); // +10 hábiles (sin 2026-11-… feriados registrados)
        $this->assertSame(19, Plazos::diasHabiles(Carbon::parse('2026-10-05', 'America/Santiago'), Carbon::parse('2026-11-02', 'America/Santiago'))); // sin el feriado ya registrado
    }

    public function test_los_feriados_se_administran_con_validacion(): void
    {
        $t = $this->transp();
        $this->svc()->agregarFeriado($t, '2026-12-25', 'Navidad');
        $this->permisoDenegado(fn () => $this->svc()->agregarFeriado($t, '2026-12-25', 'Navidad'), 'registrada');
        $this->permisoDenegado(fn () => $this->svc()->agregarFeriado($t, 'no-fecha', 'X'), 'inválida');
        $this->permisoDenegado(fn () => $this->svc()->agregarFeriado($t, '1999-01-01', 'Antiguo'), 'rango');
        $this->permisoDenegado(fn () => $this->svc()->agregarFeriado($this->usuario('funcionario'), '2026-12-26', 'Otro'), 'permiso');
        $f = TransparenciaFeriado::query()->first();
        $this->comoFuncionario($t)->post("/app/feriados/{$f->id}/eliminar")->assertSessionHas('ok');
        $this->assertSame(0, TransparenciaFeriado::query()->count());
        $this->post('/app/feriados', ['fecha' => '2026-09-18', 'nombre' => '<b>Fiestas</b> Patrias', 'tipo' => 'nacional'])->assertSessionHas('ok');
        $this->assertStringNotContainsString('<b>', TransparenciaFeriado::query()->first()->nombre);
        $this->assertDatabaseHas('audit_log', ['accion' => 'feriado.agregar']);
    }

    public function test_prorroga_unica_antes_del_vencimiento_y_visible_para_el_solicitante(): void
    {
        $s = $this->asignada($this->sai('20000001', '2026-10-05'));
        $t = $this->transp();
        $this->comoFuncionario($t)->post("/app/transparencia/solicitudes/{$s->folio}/prorroga", ['motivo' => 'La información está en archivos físicos de varias unidades.'])->assertSessionHas('ok');
        $s->refresh();
        $this->assertSame(10, $s->prorroga_dias);
        $this->assertSame('2026-11-16', Plazos::deSolicitud($s)->format('Y-m-d'));
        $this->permisoDenegado(fn () => $this->svc()->prorrogar($s, $t, 'Segundo intento de prórroga'), 'única');
        $this->assertDatabaseHas('audit_log', ['accion' => 'transparencia.prorroga']);
        $c = ['run' => $s->run, 'nombres' => 'V', 'apellidos' => 'S'];
        auth()->forgetGuards();
        $this->withSession(['ciudadano' => $c, 'login_at' => time(), 'last_seen' => time()])->get('/ciudadano/solicitudes/'.$s->folio)->assertOk()->assertSee('Plazo ampliado')->assertSee('archivos físicos');
    }

    public function test_prorroga_rechazada_si_venció_si_esta_devuelta_si_no_es_de_acceso_o_motivo_corto(): void
    {
        $t = $this->transp();
        $vencida = $this->asignada($this->sai('20000002', '2026-08-01'));
        $this->permisoDenegado(fn () => $this->svc()->prorrogar($vencida, $t, 'El plazo ya venció pero igual lo pido'), 'venció');
        $devuelta = $this->asignada($this->sai('20000003', '2026-10-05'));
        $devuelta->forceFill(['estado' => 'devuelta'])->save();
        $this->permisoDenegado(fn () => $this->svc()->prorrogar($devuelta, $t, 'Motivo suficientemente largo'), 'suspendido');
        $ok = $this->asignada($this->sai('20000004', '2026-10-05'));
        $this->permisoDenegado(fn () => $this->svc()->prorrogar($ok, $t, 'corto'), 'motivo');
        $normal = $this->sai('20000005', null, 1);
        $this->permisoDenegado(fn () => $this->svc()->prorrogar($normal, $t, 'Motivo suficientemente largo'), 'acceso a la información');
        $this->svc()->prorrogar($ok, $t, '<script>alert(1)</script>Motivo fundado y suficiente');
        $this->assertStringNotContainsString('<script>', (string) $ok->refresh()->prorroga_motivo);
    }

    // ------------------------------------------------------------------ requerimientos a las direcciones
    public function test_requerimiento_a_una_direccion_y_respuesta_solo_de_esa_direccion(): void
    {
        $t = $this->transp();
        $s = $this->asignada($this->sai('20000001', '2026-10-05'));
        $dom = $this->usuario('funcionario', 1);          // DOM (unidad 3)
        $dideco = $this->usuario('funcionario', 3);       // DIDECO (unidad 4)
        $this->comoFuncionario($t)->post("/app/transparencia/solicitudes/{$s->folio}/requerimientos", ['unidad_id' => $dom->unidad_id, 'instruccion' => 'Entregue el contrato y sus anexos.', 'plazo_dias' => 3])->assertSessionHas('ok');
        $q = TransparenciaRequerimiento::query()->first();
        $this->assertSame('pendiente', $q->estado);
        $this->assertSame('2026-10-19', $q->vence_en->setTimezone('America/Santiago')->format('Y-m-d')); // 3 hábiles desde el miércoles 14
        // la dirección ve su requerimiento (con la consulta, sin RUN ni nombre) y la otra no
        auth()->forgetGuards();
        $h = $this->comoFuncionario($dom)->get('/app/transparencia/requerimientos')->assertOk()->getContent();
        $this->assertStringContainsString($s->folio, $h);
        $this->assertStringContainsString('Entregue el contrato', $h);
        $this->assertStringNotContainsString($s->run, $h);
        $this->assertStringNotContainsString('Secreto', $h);
        auth()->forgetGuards();
        $this->comoFuncionario($dideco)->get('/app/transparencia/requerimientos')->assertOk()->assertDontSee($s->folio);
        $this->comoFuncionario($dideco)->post("/app/transparencia/requerimientos/{$q->id}/responder", ['respuesta' => 'No me corresponde responder'])->assertSessionHas('error');
        $this->assertSame('pendiente', $q->refresh()->estado);
        auth()->forgetGuards();
        $this->comoFuncionario($dom)->post("/app/transparencia/requerimientos/{$q->id}/responder", ['respuesta' => '<b>Se adjunta</b> el contrato en la carpeta compartida.'])->assertSessionHas('ok');
        $q->refresh();
        $this->assertSame('respondido', $q->estado);
        $this->assertStringNotContainsString('<b>', $q->respuesta);
        $this->assertSame($dom->id, $q->respondido_por);
        $this->comoFuncionario($dom)->post("/app/transparencia/requerimientos/{$q->id}/responder", ['respuesta' => 'Segunda respuesta'])->assertSessionHas('error');
        $this->assertDatabaseHas('audit_log', ['accion' => 'transparencia.respuesta']);
        // Transparencia ve todo y la respuesta
        auth()->forgetGuards();
        $this->comoFuncionario($t)->get('/app/transparencia/requerimientos')->assertOk()->assertSee('Se adjunta');
    }

    public function test_requerir_exige_expediente_abierto_direccion_activa_y_plazo_valido(): void
    {
        $t = $this->transp();
        $sinAsignar = $this->sai('20000002');
        $this->permisoDenegado(fn () => $this->svc()->requerir($sinAsignar, $t, 3, 'Entregue la información solicitada'), 'asigne');
        $s = $this->asignada();
        $this->permisoDenegado(fn () => $this->svc()->requerir($s, $t, 9999, 'Entregue la información solicitada'), 'inválida');
        $this->permisoDenegado(fn () => $this->svc()->requerir($s, $t, 3, 'corta'), 'instrucción');
        $this->permisoDenegado(fn () => $this->svc()->requerir($s, $t, 3, 'Entregue la información solicitada', 99), 'plazo');
        Unidad::query()->where('id', 4)->update(['activo' => false]);
        $this->permisoDenegado(fn () => $this->svc()->requerir($s, $t, 4, 'Entregue la información solicitada'), 'inválida');
        $this->permisoDenegado(fn () => $this->svc()->requerir($s, $this->usuario('funcionario'), 3, 'Entregue la información solicitada'), 'permiso');
    }

    // ------------------------------------------------------------------ derivación a otro órgano
    public function test_derivar_a_otro_organo_registra_y_cierra_el_expediente(): void
    {
        $s = $this->asignada($this->sai('20000001', '2026-10-12'));
        $d = ['organo' => 'Ministerio de Salud', 'motivo' => 'La información corresponde a ese ministerio.', 'fecha' => '2026-10-14', 'canal' => 'docdigital', 'referencia' => 'DD-123'];
        $this->comoFuncionario($this->transp())->post("/app/transparencia/solicitudes/{$s->folio}/derivar-organo", $d)->assertRedirect(route('app.expediente', $s->expediente_id))->assertSessionHas('ok');
        $s->refresh()->load('expediente');
        $this->assertSame('finalizada', $s->estado);
        $this->assertSame('cerrado', $s->expediente->estado);
        $x = TransparenciaDerivacion::query()->first();
        $this->assertSame('Ministerio de Salud', $x->organo);
        $this->assertSame('DD-123', $x->referencia);
        $fila = app(SeguimientoService::class)->filas(Solicitud::query()->get())->first();
        $this->assertSame('terminada', $fila['etapa']);
        $this->assertDatabaseHas('audit_log', ['accion' => 'transparencia.derivar_organo']);
    }

    public function test_derivar_valida_fecha_canal_y_revierte_si_hay_documentos_en_flujo(): void
    {
        $t = $this->transp();
        $s = $this->asignada($this->sai('20000001', '2026-10-12'));
        $base = ['organo' => 'Ministerio de Salud', 'motivo' => 'La información corresponde a ese ministerio.', 'fecha' => '2026-10-14', 'canal' => 'oficio'];
        $this->permisoDenegado(fn () => $this->svc()->derivarOtroOrgano($s, $t, ['fecha' => '2026-12-01'] + $base), 'fecha');
        $this->permisoDenegado(fn () => $this->svc()->derivarOtroOrgano($s, $t, ['fecha' => '2026-10-01'] + $base), 'fecha');
        $this->permisoDenegado(fn () => $this->svc()->derivarOtroOrgano($s, $t, ['canal' => 'paloma'] + $base), 'canal');
        $this->permisoDenegado(fn () => $this->svc()->derivarOtroOrgano($s, $t, ['organo' => 'X'] + $base), 'órgano');
        $this->permisoDenegado(fn () => $this->svc()->derivarOtroOrgano($s, $this->usuario('funcionario'), $base), 'permiso');
        $doc = app(DocumentoService::class)->crear($s->expediente, TipoDocumento::query()->where('codigo', 'OFICIO')->first(), null, 'Oficio', $t);
        app(DocumentoService::class)->definirFlujo($doc, 'agil', [], [$this->usuario('secretario')->id]);
        $this->permisoDenegado(fn () => $this->svc()->derivarOtroOrgano($s->refresh(), $t, $base), 'flujo');
        $this->assertSame(0, TransparenciaDerivacion::query()->count(), 'sin derivación si no se pudo cerrar');
        $this->assertSame('abierto', $s->expediente->refresh()->estado);
    }

    // ------------------------------------------------------------------ transparencia activa
    public function test_calendario_de_transparencia_activa_estados_y_registro(): void
    {
        $this->assertGreaterThanOrEqual(10, TransparenciaActivaItem::query()->count());
        $i = TransparenciaActivaItem::query()->where('codigo', 'PERSONAL')->first();
        $svc = $this->svc();
        $this->assertSame('2026-09', $svc->periodoVigente());
        $this->assertSame('vencida', $svc->estadoActiva($i)['estado']);                       // 14-oct, límite día 10
        $this->assertSame('por_vencer', $svc->estadoActiva($i, Carbon::parse('2026-10-08', 'America/Santiago'))['estado']);
        $this->assertSame('pendiente', $svc->estadoActiva($i, Carbon::parse('2026-10-02', 'America/Santiago'))['estado']);
        $t = $this->transp();
        $svc->registrarActiva($t, $i, '2026-09', '2026-10-12', 'https://municipalidad.example/transparencia/personal', 'Actualizada');
        $this->assertSame('al_dia', $svc->estadoActiva($i)['estado']);
        $svc->registrarActiva($t, $i, '2026-09', '2026-10-13', null, 'Corrección');
        $this->assertSame(2, TransparenciaActivaRegistro::query()->where('item_id', $i->id)->count(), 'historial: solo se agregan filas');
        $this->assertSame('vencida', $svc->estadoActiva($i, Carbon::parse('2026-11-20', 'America/Santiago'))['estado']); // ya corresponde el periodo siguiente
    }

    public function test_registro_de_activa_valida_periodo_fecha_y_enlace_https(): void
    {
        $i = TransparenciaActivaItem::query()->first();
        $t = $this->transp();
        $svc = $this->svc();
        $this->permisoDenegado(fn () => $svc->registrarActiva($t, $i, '2026-13', '2026-10-01', null, null), 'periodo');
        $this->permisoDenegado(fn () => $svc->registrarActiva($t, $i, '2027-01', '2026-10-01', null, null), 'futuro');
        $this->permisoDenegado(fn () => $svc->registrarActiva($t, $i, '2026-09', '2026-12-01', null, null), 'futura');
        $this->permisoDenegado(fn () => $svc->registrarActiva($t, $i, '2026-09', '2026-10-01', 'http://inseguro.example', null), 'https');
        $this->permisoDenegado(fn () => $svc->registrarActiva($t, $i, '2026-09', '2026-10-01', 'javascript:alert(1)', null), 'https');
        $this->permisoDenegado(fn () => $svc->registrarActiva($this->usuario('funcionario'), $i, '2026-09', '2026-10-01', null, null), 'permiso');
        $this->assertSame(0, TransparenciaActivaRegistro::query()->count());
    }

    public function test_administrar_items_del_calendario(): void
    {
        $t = $this->comoFuncionario($this->transp());
        $t->post('/app/transparencia/activa', ['codigo' => 'convenios', 'nombre' => 'Convenios con otras instituciones', 'dia_limite' => 15, 'unidad_id' => 2, 'activo' => 1])->assertSessionHas('ok');
        $i = TransparenciaActivaItem::query()->where('codigo', 'CONVENIOS')->first();
        $this->assertSame(15, $i->dia_limite);
        $t->post('/app/transparencia/activa', ['codigo' => 'CONVENIOS', 'nombre' => 'Repetido item', 'dia_limite' => 10])->assertSessionHas('error');
        $t->post('/app/transparencia/activa', ['codigo' => 'x y', 'nombre' => 'Código inválido', 'dia_limite' => 10])->assertSessionHas('error');
        $t->post('/app/transparencia/activa', ['codigo' => 'OTRO', 'nombre' => 'Día inválido', 'dia_limite' => 31])->assertSessionHasErrors('dia_limite');
        $t->post('/app/transparencia/activa/'.$i->id, ['nombre' => 'Convenios (editado)', 'dia_limite' => 12, 'unidad_id' => 2])->assertSessionHas('ok');
        $this->assertFalse($i->refresh()->activo, 'sin la casilla «activo» queda inactivo');
        $this->assertSame(12, $i->dia_limite);
        $this->get('/app/transparencia/activa')->assertOk()->assertSee('Convenios (editado)');
    }

    public function test_parametros_de_plazo_con_limites(): void
    {
        $svc = $this->svc();
        $this->assertSame(10, $svc->param('prorroga_dias'));
        $svc->guardarParametros($this->transp(), ['prorroga_dias' => 7, 'requerimiento_dias' => 4]);
        $this->assertSame(7, $svc->param('prorroga_dias'));
        $this->permisoDenegado(fn () => $svc->guardarParametros($this->transp(), ['prorroga_dias' => 99]), 'entre');
        $this->permisoDenegado(fn () => $svc->guardarParametros($this->transp(), ['activa_dia_limite' => 'abc']), 'entre');
        $this->permisoDenegado(fn () => $svc->guardarParametros($this->usuario('funcionario'), ['prorroga_dias' => 5]), 'permiso');
        $s = $this->asignada($this->sai('20000001', '2026-10-05'));
        $svc->prorrogar($s, $this->transp(), 'Motivo suficientemente fundado');
        $this->assertSame(7, $s->refresh()->prorroga_dias);
    }

    // ------------------------------------------------------------------ reportes
    public function test_reporte_pasivo_sin_datos_personales_y_con_totales(): void
    {
        $t = $this->transp();
        $enPlazo = $this->asignada($this->sai('20000001', '2026-10-12'));
        $vencida = $this->asignada($this->sai('20000002', '2026-08-03'));
        $cerrada = $this->asignada($this->sai('20000003', '2026-10-01'));
        app(ExpedienteService::class)->cerrar($cerrada->expediente);                                  // cerrada hoy: dentro del plazo
        $tarde = $this->asignada($this->sai('20000004', '2026-07-01'));
        app(ExpedienteService::class)->cerrar($tarde->expediente);                                    // cerrada fuera de plazo
        $der = $this->asignada($this->sai('20000005', '2026-10-12'));
        $this->svc()->derivarOtroOrgano($der, $t, ['organo' => '=HYPERLINK("http://x")', 'motivo' => 'Corresponde a otro órgano.', 'fecha' => '2026-10-13', 'canal' => 'correo']);
        $this->svc()->prorrogar($enPlazo, $t, 'Motivo suficientemente fundado');
        $this->svc()->requerir($enPlazo, $t, 3, 'Entregue la información solicitada', 2);
        $this->sai('20000006', null, 1); // trámite normal: no entra
        $r = app(TransparenciaReporte::class)->pasiva(null, null);
        $tot = $r['totales'];
        $this->assertSame(5, $tot['total']);
        $this->assertSame(1, $tot['respondidas_en_plazo']);
        $this->assertSame(1, $tot['respondidas_fuera_de_plazo']);
        $this->assertSame(1, $tot['derivadas']);
        $this->assertSame(1, $tot['prorrogadas']);
        $this->assertSame(1, $tot['en_curso_vencidas']);
        $this->assertSame(1, $tot['requerimientos_pendientes']);
        $this->assertNotNull($tot['dias_habiles_promedio']);
        // rango de fechas
        $r2 = app(TransparenciaReporte::class)->pasiva('2026-10-10', '2026-10-31');
        $this->assertSame(2, $r2['totales']['total']);
        // por dirección requerida
        $this->assertSame(1, app(TransparenciaReporte::class)->pasiva(null, null, 3)['totales']['total']);
        // pantalla y CSV: sin RUN, nombre ni correo
        $c = $this->comoFuncionario($t);
        $h = $c->get('/app/transparencia/reportes')->assertOk()->getContent();
        $csv = $c->get('/app/transparencia/reportes?formato=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        foreach ([$h, $csv] as $salida) {
            foreach (['20000001', '20.000.001', 'Vecino', 'Secreto', '@example.com'] as $dato) {
                $this->assertStringNotContainsString($dato, $salida, "no debe filtrar $dato");
            }
        }
        $this->assertStringStartsWith("\xEF\xBB\xBF\"Folio\";", $csv);
        $this->assertStringContainsString("\"'=HYPERLINK", $csv, 'inyección de fórmulas neutralizada');
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
        $this->assertDatabaseHas('audit_log', ['accion' => 'transparencia.reporte_csv']);
        $c->get('/app/transparencia/reportes?tipo=activa&formato=csv')->assertOk()->assertSee('PERSONAL');
        $c->get('/app/transparencia/reportes?desde=2026-12-01&hasta=2026-01-01')->assertSessionHasErrors('hasta');
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/transparencia/reportes?formato=csv')->assertForbidden();
    }

    // ------------------------------------------------------------------ integración con seguimiento y tableros
    public function test_el_seguimiento_de_la_autoridad_respeta_dias_habiles_y_prorroga(): void
    {
        $s = $this->asignada($this->sai('20000001', '2026-10-05'));
        $this->svc()->prorrogar($s, $this->transp(), 'Motivo suficientemente fundado');
        $fila = app(SeguimientoService::class)->filas(Solicitud::query()->get())->first();
        $this->assertSame(Plazos::deSolicitud($s->refresh())->format('Y-m-d'), $fila['vence']->format('Y-m-d'));
        $this->assertSame('2026-11-16', $fila['vence']->format('Y-m-d'));
        $this->assertSame('en_plazo', $fila['plazo']);
        $idsVencidos = app(SeguimientoService::class)->idsPorPlazo('vencido');
        $this->assertNotContains($s->id, $idsVencidos);
    }

    public function test_tableros_de_transparencia_y_aviso_a_las_direcciones(): void
    {
        $s = $this->asignada($this->sai('20000001', '2026-10-05'));
        $this->svc()->requerir($s, $this->transp(), 3, 'Entregue la información solicitada', 1);
        $h = $this->comoFuncionario($this->transp())->get('/app')->assertOk()->getContent();
        foreach (['Consultas por etapa', 'Plazos de las consultas', 'Requerimientos pendientes por dirección', 'Transparencia activa (periodo vigente)', 'Consultas abiertas'] as $t) {
            $this->assertStringContainsString($t, $h);
        }
        auth()->forgetGuards();
        $h = $this->comoFuncionario($this->usuario('funcionario', 1))->get('/app')->assertOk()->getContent();
        $this->assertStringContainsString('Requerimientos de transparencia por responder', $h);
        auth()->forgetGuards();
        $h = $this->comoFuncionario($this->usuario('funcionario', 3))->get('/app')->getContent();
        $this->assertStringNotContainsString('Requerimientos de transparencia por responder', $h);
    }

    public function test_menu_de_transparencia(): void
    {
        $h = $this->comoFuncionario($this->transp())->get('/app')->getContent();
        foreach (['app/transparencia"', 'app/transparencia/activa', 'app/transparencia/plazos', 'app/transparencia/reportes', 'app/transparencia/requerimientos'] as $r) {
            $this->assertStringContainsString($r, $h, $r);
        }
        auth()->forgetGuards();
        $h = $this->comoFuncionario($this->usuario('admin'))->get('/app')->getContent();
        $this->assertStringNotContainsString('app/transparencia', $h);
    }

    // ------------------------------------------------------------------ trámite marcado como solicitud de acceso
    public function test_solo_la_administracion_marca_un_tramite_como_solicitud_de_acceso(): void
    {
        $adm = $this->usuario('admin');
        $svc = app(TramiteService::class);
        $t = $svc->crear($adm, ['nombre' => 'Acceso a la información alternativo', 'plazo_dias' => 20, 'unidad_propietaria_id' => 6, 'unidades' => [6], 'es_transparencia' => 1, 'plazo_habiles' => 1, 'anexos' => []]);
        $this->assertTrue($t->es_transparencia);
        $this->assertTrue($t->plazo_habiles);
        $t = $svc->actualizar($t, $adm, ['nombre' => 'Acceso a la información alternativo', 'plazo_dias' => 20, 'anexos' => []]);
        $this->assertFalse($t->es_transparencia, 'si el formulario no lo marca, se desmarca');
        $this->comoFuncionario($adm)->get('/app/tramites/'.$t->id)->assertOk()->assertSee('solicitud de acceso a la información pública');
    }

    // ------------------------------------------------------------------ comando de cuentas de prueba
    public function test_el_comando_crea_unidad_tramite_y_cuenta_solo_en_pruebas(): void
    {
        Usuario::query()->where('rol', 'transparencia')->delete();
        DB::table('tramite_unidad')->where('tramite_id', 5)->delete();
        Tramite::query()->where('id', 5)->delete();
        Unidad::query()->where('codigo', 'TRANSP')->delete();
        config(['sgd.ambiente' => 'produccion']);
        $this->artisan('pruebas:transparencia')->assertFailed();
        $this->assertSame(0, Usuario::query()->where('rol', 'transparencia')->count());
        config(['sgd.ambiente' => 'pruebas']);
        $this->artisan('pruebas:transparencia')->assertSuccessful();
        $this->assertSame(1, Usuario::query()->where('rol', 'transparencia')->count());
        $this->assertSame(1, Tramite::query()->where('es_transparencia', true)->count());
        $u = Usuario::query()->where('rol', 'transparencia')->first();
        $this->assertSame(Unidad::query()->where('codigo', 'TRANSP')->value('id'), $u->unidad_id);
        $this->artisan('pruebas:transparencia')->assertSuccessful();
        $this->assertSame(1, Usuario::query()->where('rol', 'transparencia')->count());
        $this->assertSame(1, Tramite::query()->where('es_transparencia', true)->count());
    }

    // ------------------------------------------------------------------ robustez
    public function test_filtros_del_panel_son_seguros(): void
    {
        $t = $this->comoFuncionario($this->transp());
        $t->get('/app/transparencia?folio='.urlencode("' OR 1=1 --"))->assertOk();
        $h = $t->get('/app/transparencia?folio='.urlencode('<script>alert(1)</script>'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $h);
        $t->get('/app/transparencia?run=11.111.111-9')->assertSessionHasErrors('run');
        $t->get('/app/transparencia?etapa=inventada')->assertSessionHasErrors('etapa');
    }

    public function test_auditoria_de_las_acciones_sensibles(): void
    {
        $s = $this->asignada();
        $this->svc()->requerir($s, $this->transp(), 3, 'Entregue la información solicitada');
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('accion', 'transparencia.requerimiento')->count());
        $dump = json_encode(AuditLog::query()->where('accion', 'like', 'transparencia.%')->get()->toArray());
        $this->assertStringNotContainsString($s->run, $dump);
    }
}
