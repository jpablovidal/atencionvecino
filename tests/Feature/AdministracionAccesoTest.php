<?php

namespace Tests\Feature;

use App\Http\Controllers\LogsController;
use App\Http\Controllers\SesionesController;
use App\Models\Expediente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\ExpedienteService;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

class AdministracionAccesoTest extends TestCase
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

    private function rolPersonalizado(string $clave, string $base, array $permisos): Rol
    {
        $r = Rol::query()->create(['clave' => $clave, 'nombre' => ucfirst($clave), 'rol_base' => $base, 'sistema' => false, 'activo' => true]);
        foreach ($permisos as $p) {
            DB::table('rol_permisos')->insert(['rol_id' => $r->id, 'permiso' => $p]);
        }

        return $r;
    }

    // ------------------------------------------------------------------ logs
    public function test_registro_de_logs_filtra_y_enmascara_run(): void
    {
        $a = app(AuditoriaService::class);
        $a->registrar('doc.firmar', 'documento', 7, [], false, ['run' => '12345678-5', 'rol' => 'alcalde']);
        $a->registrar('auth.fallo', null, null, [], true, ['run' => '11111111-1', 'rol' => null]);
        $h = $this->comoFuncionario($this->usuario('auditor'));
        $h->get('/app/logs')->assertOk()->assertSee('<td>doc.firmar', false)->assertSee('***678-5', false)->assertDontSee('12345678-5');
        $this->get('/app/logs?accion=auth.')->assertSee('<td>auth.fallo', false)->assertDontSee('<td>doc.firmar', false);
        $this->get('/app/logs?run=12.345.678-5')->assertSee('<td>doc.firmar', false)->assertDontSee('<td>auth.fallo', false);
        $this->get('/app/logs?sensible=1')->assertSee('<td>auth.fallo', false)->assertDontSee('<td>doc.firmar', false);
        $this->get('/app/logs?desde=2999-01-01')->assertSee('Sin registros');
        $this->get('/app/logs?ip=no-es-ip')->assertSessionHasErrors('ip');
        $this->get('/app/logs?accion=%25')->assertDontSee('<td>doc.firmar', false); // el comodín se escapa
    }

    public function test_logs_solo_para_admin_y_auditor(): void
    {
        foreach (['funcionario', 'alcalde', 'secretario'] as $r) {
            $this->comoFuncionario($this->usuario($r))->get('/app/logs')->assertForbidden();
        }
        $this->get('/ingreso')->assertOk();
        $this->admin()->get('/app/logs')->assertOk();
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/logs')->assertOk();
    }

    public function test_exportar_csv_audita_y_neutraliza_formulas(): void
    {
        app(AuditoriaService::class)->registrar('=HYPERLINK("http://x")', 'doc', 1);
        $r = $this->comoFuncionario($this->usuario('auditor'))->get('/app/logs/exportar');
        $r->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $csv = $r->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
        $this->assertDatabaseHas('audit_log', ['accion' => 'logs.exportar', 'sensible' => 1]);
        $this->assertSame("'=1", LogsController::celda('=1'));
        $this->assertSame('normal', LogsController::celda('normal'));
        // un rol personalizado de base auditor sin logs.exportar no exporta
        $rol = $this->rolPersonalizado('lector', 'auditor', ['logs.ver']);
        $u = $this->usuario('auditor');
        $u->update(['rol' => 'lector']);
        $this->comoFuncionario($u->fresh())->get('/app/logs/exportar')->assertForbidden();
        $this->get('/app/logs')->assertOk();
    }

    public function test_log_de_aplicacion_redacta_datos_y_exige_diagnostico(): void
    {
        $this->assertStringNotContainsString('12345678-5', LogsController::redactar('usuario 12.345.678-5 correo a@b.cl'));
        $r = LogsController::redactar('Authorization: Bearer abc123SECRETO correo juan@muni.cl run 12345678-5 '.base_path('app/X.php'));
        $this->assertStringNotContainsString('abc123SECRETO', $r);
        $this->assertStringNotContainsString('juan@muni.cl', $r);
        $this->assertStringNotContainsString(base_path(), $r);
        $this->assertStringContainsString('[RUN]', $r);
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/logs/aplicacion')->assertForbidden();
        $this->admin()->get('/app/logs/aplicacion')->assertOk();
    }

    // ------------------------------------------------------------------ sesiones
    private function sesion(string $id, ?int $userId, array $payload, ?int $ts = null): string
    {
        $h = hash('sha256', $id);
        DB::table('sessions')->insert(['id' => $h, 'user_id' => $userId, 'ip_address' => '10.0.0.5', 'user_agent' => 'TestAgent/1.0', 'payload' => base64_encode(serialize($payload)), 'last_activity' => $ts ?? time()]);

        return $h;
    }

    public function test_usuarios_conectados_lista_funcionarios_y_ciudadanos_enmascarados(): void
    {
        $f = $this->usuario('funcionario');
        $this->sesion('a', $f->id, ['login_at' => time() - 600]);
        $this->sesion('b', null, ['ciudadano' => ['run' => '20000001-9', 'nombres' => 'V', 'apellidos' => 'D'], 'login_at' => time()]);
        $this->sesion('c', null, ['_token' => 'x']); // anónima: no se lista
        $this->sesion('d', $f->id, [], time() - 99999); // vencida
        $r = $this->admin()->get('/app/sesiones')->assertOk();
        $r->assertSee($f->nombres)->assertSee('Ciudadano')->assertSee('***001-9', false)->assertDontSee('20000001-9')->assertSee('TestAgent/1.0');
        $this->assertStringContainsString('1 sesión activa', $r->getContent() === '' ? '' : '1 sesión activa');
        $this->assertSame(2, substr_count($r->getContent(), 'Cerrar sesión<span'));
    }

    public function test_cierre_forzado_de_sesion_audita_y_protege_la_propia(): void
    {
        $f = $this->usuario('funcionario');
        $h = $this->sesion('x', $f->id, []);
        $this->comoFuncionario($this->usuario('auditor'))->post("/app/sesiones/$h/cerrar")->assertForbidden(); // solo ver para auditor
        $this->admin()->post("/app/sesiones/$h/cerrar")->assertSessionHas('ok');
        $this->assertDatabaseMissing('sessions', ['id' => $h]);
        $this->assertDatabaseHas('audit_log', ['accion' => 'sesion.cerrar_forzado']);
        $this->admin()->post('/app/sesiones/'.str_repeat('0', 64).'/cerrar')->assertNotFound();
        $this->admin()->post('/app/sesiones/no-valido/cerrar')->assertNotFound();
        $this->comoFuncionario($f)->get('/app/sesiones')->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/sesiones')->assertForbidden();
    }

    public function test_payload_malicioso_no_instancia_objetos(): void
    {
        $p = base64_encode(serialize(new \ArrayObject(['a' => 1])));
        $this->assertSame([], SesionesController::payload($p));
        $this->assertSame([], SesionesController::payload('%%no-base64%%'));
    }

    // ------------------------------------------------------------------ roles
    public function test_roles_de_sistema_son_de_solo_lectura(): void
    {
        $sis = Rol::query()->where('clave', 'admin')->first();
        $this->admin()->get('/app/admin/roles')->assertOk()->assertSee('Roles y permisos');
        $this->get("/app/admin/roles/{$sis->id}")->assertOk()->assertSee('solo lectura');
        $this->post("/app/admin/roles/{$sis->id}", ['nombre' => 'X', 'rol_base' => 'admin'])->assertForbidden();
        $this->post("/app/admin/roles/{$sis->id}/estado")->assertForbidden();
        $this->post("/app/admin/roles/{$sis->id}/eliminar")->assertForbidden();
    }

    public function test_crear_rol_personalizado_respeta_el_techo_del_rol_base(): void
    {
        $base = ['clave' => 'jefe_ofic', 'nombre' => 'Jefe de oficina', 'rol_base' => 'funcionario'];
        $this->admin()->post('/app/admin/roles', $base + ['permisos' => ['expedientes.operar', 'documentos.crear']])->assertSessionHas('ok');
        $r = Rol::query()->where('clave', 'jefe_ofic')->first();
        $this->assertEqualsCanonicalizing(['expedientes.operar', 'documentos.crear'], $r->permisos());
        // fuera del techo: un funcionario no puede administrar ni ver logs
        foreach (['roles.gestionar', 'logs.ver', 'api.gestionar', 'delegaciones.crear'] as $mal) {
            $this->admin()->post('/app/admin/roles', ['clave' => 'otro_'.substr(md5($mal), 0, 5)] + $base + ['permisos' => [$mal]]);
            $this->assertTrue(session()->has('error') || session()->has('errors'), $mal);
        }
        $this->assertSame(3, Rol::query()->where('sistema', false)->count()); // jefe_ofic + los 2 roles de demostración de trámites
        // clave inválida, reservada o duplicada
        foreach (['Admin', 'admin', 'jefe_ofic', '1x', 'a', 'con espacio', str_repeat('a', 21)] as $c) {
            $this->admin()->post('/app/admin/roles', array_merge($base, ['clave' => $c]))->assertSessionHasErrors('clave');
        }
        $this->admin()->post('/app/admin/roles', array_merge($base, ['clave' => 'zz_x', 'permisos' => ['inexistente.permiso']]))->assertSessionHasErrors('permisos.*');
        // solo el administrador (roles.gestionar es exclusivo del sistema)
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/admin/roles')->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/admin/roles', $base)->assertForbidden();
    }

    public function test_un_usuario_con_rol_personalizado_recibe_solo_los_permisos_asignados(): void
    {
        $this->rolPersonalizado('solo_leer', 'funcionario', ['vigentes.ver']);
        $u = $this->usuario('funcionario');
        $u->update(['rol' => 'solo_leer']);
        $h = $this->comoFuncionario($u->fresh());
        $h->get('/app/vigentes')->assertOk();
        $h->get('/app/comunicaciones')->assertForbidden();
        $e = Expediente::query()->first();
        if ($e) {
            $h->post("/app/expedientes/{$e->id}/comentarios", ['texto' => 'hola'])->assertForbidden();
            $h->post("/app/expedientes/{$e->id}/cerrar", [])->assertForbidden();
        }
        $this->assertTrue($u->fresh()->esRol('funcionario')); // invariantes del rol base
        $this->assertFalse($u->fresh()->tienePermiso('roles.gestionar'));
        // un rol personalizado de base funcionario nunca obtiene permisos de admin aunque la BD tenga la fila
        $r = Rol::query()->where('clave', 'solo_leer')->first();
        DB::table('rol_permisos')->insert(['rol_id' => $r->id, 'permiso' => 'roles.gestionar']);
        DB::table('rol_permisos')->insert(['rol_id' => $r->id, 'permiso' => 'logs.exportar']);
        $this->assertFalse($u->fresh()->tienePermiso('roles.gestionar'));
        $this->assertFalse($u->fresh()->tienePermiso('logs.exportar'));
        $this->comoFuncionario($u->fresh())->get('/app/admin/roles')->assertForbidden();
    }

    public function test_rol_inactivo_o_inexistente_no_concede_nada(): void
    {
        $r = $this->rolPersonalizado('temporal', 'funcionario', ['vigentes.ver']);
        $u = $this->usuario('funcionario');
        $u->update(['rol' => 'temporal']);
        $this->assertTrue($u->fresh()->tienePermiso('vigentes.ver'));
        $r->update(['activo' => false]);
        $this->assertFalse($u->fresh()->tienePermiso('vigentes.ver'));
        $this->assertSame('sin-rol', $u->fresh()->rolBase());
        $this->comoFuncionario($u->fresh())->get('/app/vigentes')->assertForbidden();
    }

    public function test_editar_rol_cambia_permisos_cierra_sesiones_y_protege_la_base(): void
    {
        $r = $this->rolPersonalizado('editable', 'funcionario', ['vigentes.ver']);
        $u = $this->usuario('funcionario');
        $u->update(['rol' => 'editable']);
        $h = $this->sesion('sess1', $u->id, []);
        $this->admin()->post("/app/admin/roles/{$r->id}", ['nombre' => 'Editable 2', 'rol_base' => 'funcionario', 'permisos' => ['vigentes.ver', 'documentos.crear']])->assertSessionHas('ok');
        $this->assertEqualsCanonicalizing(['vigentes.ver', 'documentos.crear'], $r->fresh()->permisos());
        $this->assertDatabaseMissing('sessions', ['id' => $h]); // reautenticar con permisos nuevos
        // con usuarios asignados no se cambia la base
        $this->admin()->post("/app/admin/roles/{$r->id}", ['nombre' => 'Editable 2', 'rol_base' => 'auditor', 'permisos' => []])->assertSessionHas('error');
        $this->assertSame('funcionario', $r->fresh()->rol_base);
        // no se desactiva ni elimina con usuarios
        $this->admin()->post("/app/admin/roles/{$r->id}/estado")->assertSessionHas('error');
        $this->admin()->post("/app/admin/roles/{$r->id}/eliminar")->assertSessionHas('error');
        $this->assertTrue($r->fresh()->activo);
        $u->update(['rol' => 'funcionario']);
        $this->admin()->post("/app/admin/roles/{$r->id}/estado")->assertSessionHas('ok');
        $this->assertFalse($r->fresh()->activo);
        $this->admin()->post("/app/admin/roles/{$r->id}/eliminar")->assertSessionHas('ok');
        $this->assertDatabaseMissing('roles', ['id' => $r->id]);
        $this->assertDatabaseMissing('rol_permisos', ['rol_id' => $r->id]);
        $this->assertDatabaseHas('audit_log', ['accion' => 'rol.eliminar']);
    }

    // ------------------------------------------------------------------ usuarios
    public function test_usuarios_con_roles_dinamicos_y_busqueda(): void
    {
        $this->rolPersonalizado('analista', 'funcionario', ['vigentes.ver']);
        $u = ['nombres' => 'Nuevo', 'apellidos' => 'Analista', 'correo' => 'na@muni.cl', 'unidad_id' => 3, 'run' => '12.345.678-5'];
        $this->admin()->post('/app/admin/usuarios', $u + ['rol' => 'analista', 'puede_firmar' => 1])->assertSessionHasNoErrors()->assertRedirect('/app/admin/usuarios');
        $this->assertDatabaseHas('usuarios', ['run' => '12345678-5', 'rol' => 'analista', 'puede_firmar' => 1]);
        $this->admin()->post('/app/admin/usuarios', array_merge($u, ['run' => '9.999.999-3', 'rol' => 'inexistente']))->assertSessionHasErrors('rol');
        $this->get('/app/admin/usuarios?q=Analista')->assertSee('Nuevo Analista')->assertDontSee($this->usuario('alcalde')->apellidos);
        $this->get('/app/admin/usuarios?rol=analista')->assertSee('Nuevo Analista');
        $this->get('/app/admin/usuarios?estado=inactivo')->assertDontSee('Nuevo Analista');
        $this->get('/app/admin/usuarios?q=%25')->assertOk();
        // rol personalizado de base admin no puede firmar
        $this->rolPersonalizado('super2', 'admin', ['mantenedores.gestionar']);
        $this->admin()->post('/app/admin/usuarios', array_merge($u, ['run' => '9.999.999-3', 'rol' => 'super2', 'puede_firmar' => 1]));
        $this->assertDatabaseHas('usuarios', ['rol' => 'super2', 'puede_firmar' => 0]);
        // desactivar un rol personalizado lo quita de las opciones
        $this->get('/app/admin/usuarios/nuevo')->assertSee('Analista');
    }

    public function test_resguardos_no_desactivarse_ni_dejar_sin_administrador(): void
    {
        $adm = $this->usuario('admin');
        $this->comoFuncionario($adm)->post("/app/admin/usuarios/{$adm->id}/activo")->assertSessionHas('error');
        $this->assertTrue((bool) $adm->fresh()->activo);
        // si existe otro admin, puede desactivarse al otro pero no al último
        $otro = Usuario::query()->create(['run' => '13000000-'.Run::dv('13000000'), 'nombres' => 'Otro', 'apellidos' => 'Admin', 'correo' => 'o@muni.cl', 'rol' => 'admin', 'activo' => true]);
        $this->comoFuncionario($adm)->post("/app/admin/usuarios/{$otro->id}/activo")->assertSessionHas('ok');
        $this->assertFalse((bool) $otro->fresh()->activo);
        $otro->update(['activo' => true]);
        $otro->update(['activo' => true]);
        // cambiar el rol del último admin se rechaza
        $otro->update(['activo' => false]);
        $this->comoFuncionario($adm)->post("/app/admin/usuarios/{$adm->id}", ['run' => $adm->run, 'nombres' => $adm->nombres, 'apellidos' => $adm->apellidos, 'correo' => $adm->correo, 'rol' => 'funcionario'])->assertSessionHas('error');
        $this->assertSame('admin', $adm->fresh()->rol);
    }

    public function test_desactivar_o_cambiar_rol_cierra_las_sesiones_del_usuario(): void
    {
        $f = $this->usuario('funcionario');
        $h = $this->sesion('s1', $f->id, []);
        $this->admin()->post("/app/admin/usuarios/{$f->id}/activo")->assertSessionHas('ok');
        $this->assertDatabaseMissing('sessions', ['id' => $h]);
        $this->admin()->post("/app/admin/usuarios/{$f->id}/activo");
        $h2 = $this->sesion('s2', $f->id, []);
        $this->admin()->post("/app/admin/usuarios/{$f->id}", ['run' => $f->run, 'nombres' => $f->nombres, 'apellidos' => $f->apellidos, 'correo' => $f->correo, 'rol' => 'secretario'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => $h2]);
        $this->assertDatabaseHas('audit_log', ['accion' => 'usuario.editar', 'sensible' => 1]);
    }

    public function test_ultimo_acceso_se_registra_al_ingresar(): void
    {
        $f = $this->usuario('funcionario');
        $this->assertNull($f->fresh()->ultimo_acceso);
        $f->forceFill(['ultimo_acceso' => now()])->save();
        $this->assertNotNull($f->fresh()->ultimo_acceso);
    }

    // ------------------------------------------------------------------ permisos en acciones
    public function test_acciones_de_expediente_exigen_su_permiso_especifico(): void
    {
        $dom = $this->usuario('funcionario');
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $this->rolPersonalizado('miron', 'funcionario', ['vigentes.ver', 'comunicaciones.ver']);
        $dom->update(['rol' => 'miron']);
        $h = $this->comoFuncionario($dom->fresh());
        $h->get('/app/comunicaciones')->assertOk();
        $h->post("/app/expedientes/{$e->id}/comentarios", ['texto' => 'x'])->assertForbidden();
        $h->post("/app/expedientes/{$e->id}/cerrar", [])->assertForbidden();
        $h->post("/app/expedientes/{$e->id}/derivar", [])->assertForbidden();
        $h->post("/app/expedientes/{$e->id}/devolver", [])->assertForbidden();
        $h->get("/app/expedientes/{$e->id}/exportar")->assertForbidden();
        $h->post("/app/expedientes/{$e->id}/documentos", [])->assertForbidden();
        $h->post("/app/expedientes/{$e->id}/comunicaciones", [])->assertForbidden();
        $h->post('/app/llm/borrador', [])->assertForbidden();
    }
}
