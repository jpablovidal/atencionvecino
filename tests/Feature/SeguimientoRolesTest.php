<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Derivacion;
use App\Models\Devolucion;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\SeguimientoService;
use App\Support\Permisos;
use App\Support\Plazos;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class SeguimientoRolesTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Santiago'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function autoridad(): Usuario
    {
        return $this->usuario('autoridad');
    }

    private function oirs(): Usuario
    {
        return $this->usuario('oirs');
    }

    /** Solicitud directa en BD (sin archivos): run, trámite y fecha de ingreso. */
    private function sol(string $cuerpo = '20000001', int $tramite = 1, ?string $ingreso = null, string $estado = 'ingresada'): Solicitud
    {
        $s = Solicitud::query()->create(['folio' => 'SD-2026-T'.str_pad((string) ++$this->n, 5, '0', STR_PAD_LEFT), 'run' => $cuerpo.'-'.Run::dv($cuerpo), 'nombres' => 'Vecino'.$this->n, 'apellidos' => 'Prueba',
            'correo' => 'v@example.com', 'tramite_id' => $tramite, 'detalle' => 'detalle de prueba', 'canal_notificacion' => 'correo', 'estado' => $estado]);
        if ($ingreso) {
            $s->forceFill(['created_at' => Carbon::parse($ingreso, 'America/Santiago')])->saveQuietly();
        }

        return $s->refresh();
    }

    private function expediente(Solicitud $s, string $rolUsuario = 'funcionario', int $n = 1)
    {
        $u = $this->usuario($rolUsuario, $n);

        return [app(ExpedienteService::class)->crearDesdeSolicitud($s, $u), $u];
    }

    // ---------------------------------------------------------------- roles y permisos
    public function test_roles_del_sistema_y_permisos_por_defecto(): void
    {
        $this->assertSame(1, Rol::query()->where('clave', 'autoridad')->where('sistema', 1)->count());
        $this->assertSame(1, Rol::query()->where('clave', 'oirs')->where('sistema', 1)->count());
        $a = $this->autoridad();
        $o = $this->oirs();
        $this->assertTrue($a->tienePermiso('seguimiento.consultar'));
        $this->assertFalse($a->tienePermiso('oirs.ver'));
        $this->assertTrue($o->tienePermiso('oirs.ver'));
        $this->assertFalse($o->tienePermiso('seguimiento.consultar'));
        foreach ([$a, $o] as $u) {
            $this->assertFalse($u->operaExpedientes());
            foreach (['expedientes.operar', 'documentos.crear', 'vigentes.ver', 'notificaciones.gestionar', 'comunicaciones.ver', 'archivo.ver', 'mantenedores.gestionar', 'logs.ver'] as $p) {
                $this->assertFalse($u->tienePermiso($p), $u->rol.' no debe tener '.$p);
            }
        }
        $this->assertTrue($this->usuario('alcalde')->tienePermiso('seguimiento.consultar'));
        $this->assertTrue($this->usuario('auditor')->tienePermiso('seguimiento.consultar'));
        $this->assertFalse($this->usuario('funcionario')->tienePermiso('seguimiento.consultar'));
        $this->assertFalse($this->usuario('admin')->tienePermiso('seguimiento.consultar'));
        $this->assertFalse($this->usuario('admin')->tienePermiso('oirs.ver'));
        $this->assertSame(['oirs', 'auditor'], Permisos::catalogo()['oirs.ver'][2]);
    }

    public function test_un_rol_personalizado_no_supera_el_techo_de_su_base(): void
    {
        $rol = Rol::query()->create(['clave' => 'lector_seg', 'nombre' => 'Lector', 'descripcion' => '', 'rol_base' => 'autoridad', 'sistema' => false, 'activo' => true]);
        foreach (['seguimiento.consultar', 'expedientes.operar', 'oirs.ver'] as $p) {
            DB::table('rol_permisos')->insert(['rol_id' => $rol->id, 'permiso' => $p]);
        }
        $u = $this->autoridad();
        $u->forceFill(['rol' => 'lector_seg'])->save();
        $u = Usuario::query()->find($u->id);
        $this->assertTrue($u->tienePermiso('seguimiento.consultar'));
        $this->assertFalse($u->tienePermiso('expedientes.operar'));
        $this->assertFalse($u->tienePermiso('oirs.ver'));
    }

    public function test_matriz_de_acceso_a_las_pantallas(): void
    {
        $s = $this->sol();
        $this->comoFuncionario($this->autoridad())->get('/app/seguimiento')->assertOk();
        $this->get('/app/oirs')->assertForbidden();
        $this->get('/app/seguimiento/'.$s->folio)->assertOk();
        auth()->forgetGuards();
        $this->comoFuncionario($this->oirs())->get('/app/oirs')->assertOk();
        $this->get('/app/seguimiento')->assertForbidden();
        $this->get('/app/seguimiento/'.$s->folio)->assertOk();
        foreach (['funcionario', 'admin', 'secretario'] as $rol) {
            auth()->forgetGuards();
            $t = $this->comoFuncionario($this->usuario($rol));
            $t->get('/app/seguimiento')->assertForbidden();
            $t->get('/app/oirs')->assertForbidden();
            $t->get('/app/seguimiento/'.$s->folio)->assertForbidden();
        }
        auth()->forgetGuards();
        $this->flushSession();
        $this->get('/app/seguimiento')->assertRedirect('/ingreso');
        $this->comoCiudadano()->get('/app/oirs')->assertRedirect('/ingreso');
    }

    public function test_autoridad_y_oirs_no_ven_expedientes_ni_documentos_ni_operan(): void
    {
        [$e, $u] = $this->expediente($this->sol());
        $doc = $this->documentoVigente($e, $u);
        foreach (['autoridad', 'oirs'] as $rol) {
            auth()->forgetGuards();
            $t = $this->comoFuncionario($this->usuario($rol));
            $t->get('/app/expedientes/'.$e->id)->assertForbidden();
            $t->get('/app/documentos/'.$doc->id)->assertForbidden();
            $t->get('/app/documentos/'.$doc->id.'/pdf')->assertForbidden();
            $t->post('/app/expedientes/'.$e->id.'/comentarios', ['texto' => 'x'])->assertForbidden();
            $t->get('/app/vigentes')->assertForbidden();
        }
    }

    // ---------------------------------------------------------------- filtros de la autoridad
    public function test_filtro_por_run_valido_invalido_y_sin_enmascarar_en_pantalla(): void
    {
        $a = $this->sol('20000001');
        $b = $this->sol('20000002');
        $t = $this->comoFuncionario($this->autoridad());
        $html = $t->get('/app/seguimiento?run=20.000.001-'.Run::dv('20000001'))->assertOk()->getContent();
        $this->assertStringContainsString($a->folio, $html);
        $this->assertStringNotContainsString($b->folio, $html);
        $t->get('/app/seguimiento?run=11.111.111-9')->assertSessionHasErrors('run');
        $t->get('/app/seguimiento')->assertOk()->assertSee('Elija uno o más filtros');
    }

    public function test_filtros_de_fechas_direccion_tramite_y_etapa(): void
    {
        $vieja = $this->sol('20000001', 1, '2026-01-10');
        $nueva = $this->sol('20000002', 3, '2026-10-01');
        $t = $this->comoFuncionario($this->autoridad());
        $h = fn (string $q) => $t->get('/app/seguimiento?'.$q)->assertOk()->getContent();
        $r = $h('desde=2026-09-01&hasta=2026-10-31');
        $this->assertStringContainsString($nueva->folio, $r);
        $this->assertStringNotContainsString($vieja->folio, $r);
        $r = $h('hasta=2026-02-01');
        $this->assertStringContainsString($vieja->folio, $r);
        $this->assertStringNotContainsString($nueva->folio, $r);
        $t->get('/app/seguimiento?desde=2026-10-31&hasta=2026-01-01')->assertSessionHasErrors('hasta');
        $r = $h('unidad_id=4'); // DIDECO responde el trámite 3
        $this->assertStringContainsString($nueva->folio, $r);
        $this->assertStringNotContainsString($vieja->folio, $r);
        $r = $h('tramite_id=1');
        $this->assertStringContainsString($vieja->folio, $r);
        $this->assertStringNotContainsString($nueva->folio, $r);
        $r = $h('etapa=por_asignar');
        $this->assertStringContainsString($vieja->folio, $r);
        $this->assertStringContainsString($nueva->folio, $r);
        $this->assertStringNotContainsString('Solicitudes</caption><thead><tr><th scope="col">Folio</th></tr>', $r);
        $this->assertStringContainsString('No hay solicitudes', $h('etapa=terminada&tramite_id=1'));
        $t->get('/app/seguimiento?etapa=inventada')->assertSessionHasErrors('etapa');
    }

    public function test_etapas_quien_la_tiene_y_si_termino(): void
    {
        $porAsignar = $this->sol('20000001', 1);
        [$e1, $f] = $this->expediente($this->sol('20000002', 1));           // en revisión del responsable
        [$e2] = $this->expediente($this->sol('20000003', 1), 'funcionario', 2); // se le agrega un borrador
        app(DocumentoService::class)->crear($e2, TipoDocumento::query()->where('codigo', 'OFICIO')->first(), null, 'Borrador', $this->usuario('funcionario', 2));
        [$e3, $f3] = $this->expediente($this->sol('20000004', 1), 'funcionario', 1); // en visación
        $doc = app(DocumentoService::class)->crear($e3, TipoDocumento::query()->where('codigo', 'OFICIO')->first(), null, 'Doc', $f3);
        $sec = $this->usuario('secretario');
        app(DocumentoService::class)->definirFlujo($doc, 'agil', [], [$sec->id]);
        $dev = $this->sol('20000005', 3, null, 'en_tramite');
        $dev->forceFill(['estado' => 'devuelta'])->save();
        Devolucion::query()->create(['solicitud_id' => $dev->id, 'creado_por' => $f->id, 'observacion' => 'falta un documento', 'plazo_dias' => 5, 'vence_en' => now()->addDays(5)]);
        [$e5] = $this->expediente($this->sol('20000006', 1), 'funcionario', 1);
        app(ExpedienteService::class)->cerrar($e5);

        $svc = app(SeguimientoService::class);
        $filas = $svc->filas(Solicitud::query()->orderBy('id')->get())->keyBy('folio');
        $by = fn (Solicitud $s) => $filas[$s->folio];
        $this->assertSame('por_asignar', $by($porAsignar)['etapa']);
        $this->assertStringContainsString('Dirección', $by($porAsignar)['tiene']);
        $this->assertSame('en_revision', $by($e1->solicitud)['etapa']);
        $this->assertSame($f->nombreCompleto(), $by($e1->solicitud)['tiene']);
        $this->assertSame('en_redaccion', $by($e2->solicitud)['etapa']);
        $this->assertSame('en_visacion', $by($e3->solicitud)['etapa']);
        $this->assertStringContainsString($sec->nombreCompleto(), $by($e3->solicitud)['tiene']);
        $this->assertStringContainsString('(firma)', $by($e3->solicitud)['tiene']);
        $this->assertSame('devuelta', $by($dev)['etapa']);
        $this->assertStringContainsString('solicitante', $by($dev)['tiene']);
        $this->assertSame('suspendido', $by($dev)['plazo']);
        $this->assertSame('terminada', $by($e5->solicitud)['etapa']);
        $this->assertTrue($by($e5->solicitud)['terminada']);
        $this->assertNull($by($e5->solicitud)['tiene']);
        $this->assertSame('cumplido', $by($e5->solicitud)['plazo']);
        // el filtro SQL por etapa coincide con la etapa calculada
        foreach (array_keys(SeguimientoService::ETAPAS) as $etapa) {
            $esperados = $filas->where('etapa', $etapa)->pluck('folio')->sort()->values()->all();
            $obtenidos = $svc->consulta(['etapa' => $etapa])->pluck('folio')->sort()->values()->all();
            $this->assertSame($esperados, $obtenidos, "etapa $etapa");
        }
        // la instantánea de los tableros coincide con las filas
        $snap = $svc->instantanea();
        foreach ($filas as $fila) {
            $this->assertSame($fila['etapa'], $snap->firstWhere('id', $fila['s']->id)['etapa']);
            $this->assertSame($fila['plazo'], $snap->firstWhere('id', $fila['s']->id)['plazo']);
        }
    }

    public function test_derivacion_cambia_quien_la_tiene(): void
    {
        [$e, $f] = $this->expediente($this->sol('20000001', 1));
        $otro = $this->usuario('funcionario', 2);
        Derivacion::query()->create(['expediente_id' => $e->id, 'de_usuario_id' => $f->id, 'a_usuario_id' => $otro->id, 'motivo' => 'revisar']);
        $fila = app(SeguimientoService::class)->filas(Solicitud::query()->get())->first();
        $this->assertSame($otro->nombreCompleto(), $fila['tiene']);
    }

    // ---------------------------------------------------------------- plazos y OIRS
    public function test_plazos_corridos_y_habiles(): void
    {
        $lunes = Carbon::parse('2026-10-12 15:00', 'America/Santiago'); // lunes
        $this->assertSame('2026-10-17', Plazos::vencimiento($lunes, 5, false)->format('Y-m-d'));
        $this->assertSame('2026-10-19', Plazos::vencimiento($lunes, 5, true)->format('Y-m-d')); // salta el fin de semana
        $this->assertSame('23:59', Plazos::vencimiento($lunes, 5, false)->format('H:i'));
        $v = Carbon::parse('2026-10-20 23:59:59', 'America/Santiago');
        $this->assertSame('vencido', Plazos::situacion($v, Carbon::parse('2026-10-21 00:00:01', 'America/Santiago'), 3));
        $this->assertSame('por_vencer', Plazos::situacion($v, Carbon::parse('2026-10-18 09:00', 'America/Santiago'), 3));
        $this->assertSame('en_plazo', Plazos::situacion($v, Carbon::parse('2026-10-10 09:00', 'America/Santiago'), 3));
        $this->assertSame(-2, Plazos::diasRestantes($v, Carbon::parse('2026-10-22 09:00', 'America/Santiago')));
    }

    public function test_oirs_vencidos_por_vencer_y_en_plazo(): void
    {
        // «hoy» es 2026-10-14. Trámite 2 = 5 días, trámite 1 = 30 días.
        $venc = $this->sol('20000001', 2, '2026-10-01');   // vence 10-06: vencido
        $porV = $this->sol('20000002', 2, '2026-10-10');   // vence 10-15: por vencer
        $enP = $this->sol('20000003', 1, '2026-10-12');    // vence 11-11: en plazo
        $t = $this->comoFuncionario($this->oirs());
        $r = $t->get('/app/oirs')->assertOk()->getContent(); // vista por defecto: vencidas
        $this->assertStringContainsString($venc->folio, $r);
        $this->assertStringNotContainsString($porV->folio, $r);
        $r = $t->get('/app/oirs?vista=por_vencer')->getContent();
        $this->assertStringContainsString($porV->folio, $r);
        $this->assertStringNotContainsString($venc->folio, $r);
        $r = $t->get('/app/oirs?vista=todas')->getContent();
        foreach ([$venc, $porV, $enP] as $s) {
            $this->assertStringContainsString($s->folio, $r);
        }
        $this->assertStringContainsString('Plazo vencido', $t->get('/app/oirs?vista=vencidas')->getContent());
        $this->assertStringContainsString('por vencer', mb_strtolower($t->get('/app/oirs?vista=por_vencer')->getContent()));
        $t->get('/app/oirs?vista=por_asignar')->assertOk()->assertSee($enP->folio);
    }

    public function test_terminada_dentro_y_fuera_de_plazo_y_devuelta_no_cuenta_como_vencida(): void
    {
        $viejaDev = $this->sol('20000001', 2, '2026-09-01', 'en_tramite');
        $viejaDev->forceFill(['estado' => 'devuelta'])->save();
        [$e] = $this->expediente($this->sol('20000002', 2, '2026-09-01'));
        app(ExpedienteService::class)->cerrar($e); // se cierra "hoy" (2026-10-14), el plazo era 5 días
        $svc = app(SeguimientoService::class);
        $filas = $svc->filas(Solicitud::query()->get())->keyBy('folio');
        $this->assertSame('suspendido', $filas[$viejaDev->folio]['plazo']);
        $this->assertSame('fuera_de_plazo', $filas[$e->solicitud->folio]['plazo']);
        $this->assertSame([], $svc->idsPorPlazo('vencido'));
    }

    // ---------------------------------------------------------------- auditoría y detalle
    public function test_cada_consulta_se_audita_sin_el_run_completo(): void
    {
        $s = $this->sol('20000001');
        $t = $this->comoFuncionario($this->autoridad());
        $t->get('/app/seguimiento?run=20.000.001-'.Run::dv('20000001'))->assertOk();
        $t->get('/app/seguimiento/'.$s->folio)->assertOk();
        $this->assertDatabaseHas('audit_log', ['accion' => 'seguimiento.consulta']);
        $this->assertDatabaseHas('audit_log', ['accion' => 'seguimiento.detalle']);
        $dump = json_encode(AuditLog::query()->whereIn('accion', ['seguimiento.consulta', 'seguimiento.detalle'])->get()->toArray());
        $this->assertStringNotContainsString('20000001', $dump);
        $this->assertStringNotContainsString('20.000.001', $dump);
        auth()->forgetGuards();
        $this->comoFuncionario($this->oirs())->get('/app/oirs')->assertOk();
        $this->assertDatabaseHas('audit_log', ['accion' => 'oirs.consulta']);
    }

    public function test_detalle_muestra_historial_sin_documentos_y_valida_el_folio(): void
    {
        [$e, $f] = $this->expediente($this->sol('20000001', 1));
        $doc = $this->documentoVigente($e, $f);
        app(ExpedienteService::class)->cerrar($e);
        $h = $this->comoFuncionario($this->autoridad())->get('/app/seguimiento/'.$e->solicitud->folio)->assertOk()->getContent();
        foreach (['Solicitud ingresada', 'Expediente '.$e->codigo, 'trámite terminado', 'Terminada'] as $t) {
            $this->assertStringContainsString($t, $h);
        }
        $this->assertStringNotContainsString($doc->titulo, $h, 'no se muestran títulos de documentos');
        $this->assertStringNotContainsString('/app/documentos/', $h);
        $this->get('/app/seguimiento/SD-0000-NOEXISTE')->assertNotFound();
        $this->get('/app/seguimiento/%3Cscript%3E')->assertNotFound();
    }

    public function test_inyeccion_en_filtros_no_rompe_ni_se_refleja(): void
    {
        $t = $this->comoFuncionario($this->autoridad());
        $t->get('/app/seguimiento?folio='.urlencode("' OR 1=1 --"))->assertOk();
        $html = $t->get('/app/seguimiento?folio='.urlencode('<script>alert(1)</script>'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $t->get('/app/seguimiento?unidad_id=abc')->assertSessionHasErrors('unidad_id');
    }

    // ---------------------------------------------------------------- dashboards por rol
    public function test_cada_rol_tiene_su_panel(): void
    {
        $this->sol('20000001', 2, '2026-10-01');
        $esperado = [
            'autoridad' => ['Mi panel · Autoridad', 'Solicitudes por etapa', 'Solicitudes activas por dirección', 'Solicitudes ingresadas por mes'],
            'oirs' => ['Mi panel · OIRS', 'Las 10 solicitudes más urgentes', 'Plazos vencidos'],
            'funcionario' => ['Mi panel · Funcionario', 'Mis solicitudes a cargo, por etapa', 'Plazos de mi dirección'],
            'secretario' => ['Mi panel · Secretario(a) municipal', 'Plazos de mi dirección'],
            'alcalde' => ['Mi panel · Alcalde / alcaldesa', 'Solicitudes por etapa'],
            'auditor' => ['Mi panel · Auditor', 'Eventos de auditoría (últimos 7 días)', 'Solicitudes por etapa'],
            'admin' => ['Mi panel · Administrador', 'Usuarios activos por rol', 'Modo de las integraciones'],
        ];
        foreach ($esperado as $rol => $textos) {
            auth()->forgetGuards();
            $h = $this->comoFuncionario($this->usuario($rol))->get('/app')->assertOk()->getContent();
            foreach ($textos as $t) {
                $this->assertStringContainsString($t, $h, "$rol: $t");
            }
            $this->assertStringContainsString('<progress', $h, $rol.' usa gráficos accesibles');
        }
    }

    public function test_el_panel_de_la_oirs_lista_lo_mas_urgente_primero(): void
    {
        $masVieja = $this->sol('20000001', 2, '2026-09-01');
        $otra = $this->sol('20000002', 2, '2026-10-02');
        $h = $this->comoFuncionario($this->oirs())->get('/app')->getContent();
        $this->assertLessThan(strpos($h, $otra->folio), strpos($h, $masVieja->folio));
    }

    public function test_el_panel_del_funcionario_cuenta_solo_sus_solicitudes(): void
    {
        [$e, $f] = $this->expediente($this->sol('20000001', 1));
        $h = $this->comoFuncionario($f)->get('/app')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#En revisión del responsable</a>|En revisión del responsable</th><td><progress max="1" value="1"#', $h);
        auth()->forgetGuards();
        $otro = $this->usuario('funcionario', 6 > 0 ? 3 : 1); // otra unidad
        $h2 = $this->comoFuncionario($otro)->get('/app')->getContent();
        $this->assertStringNotContainsString('value="1" aria-label="En revisión del responsable: 1"', $h2);
    }

    public function test_el_comando_de_cuentas_de_prueba_crea_autoridad_y_oirs_solo_en_pruebas(): void
    {
        Usuario::query()->whereIn('rol', ['autoridad', 'oirs'])->delete();
        config(['sgd.ambiente' => 'produccion']);
        $this->artisan('pruebas:seguimiento')->assertFailed();
        $this->assertSame(0, Usuario::query()->whereIn('rol', ['autoridad', 'oirs'])->count());
        config(['sgd.ambiente' => 'pruebas']);
        $this->artisan('pruebas:seguimiento')->assertSuccessful();
        $this->assertSame(2, Usuario::query()->whereIn('rol', ['autoridad', 'oirs'])->count());
        $this->artisan('pruebas:seguimiento')->assertSuccessful(); // idempotente
        $this->assertSame(2, Usuario::query()->whereIn('rol', ['autoridad', 'oirs'])->count());
    }

    public function test_usuarios_autoridad_y_oirs_no_pueden_firmar(): void
    {
        $adm = $this->usuario('admin');
        $u = $this->autoridad();
        $this->comoFuncionario($adm)->post('/app/admin/usuarios/'.$u->id, ['run' => $u->run, 'nombres' => $u->nombres, 'apellidos' => $u->apellidos, 'correo' => $u->correo, 'rol' => 'autoridad', 'puede_firmar' => 1]);
        $this->assertFalse((bool) $u->refresh()->puede_firmar);
    }
}
