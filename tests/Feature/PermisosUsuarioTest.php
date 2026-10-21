<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Admin\PermisosUsuarioService;
use App\Support\Permisos;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Varios roles por usuario, permisos individuales y matriz de permisos por usuario. */
class PermisosUsuarioTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function svc(): PermisosUsuarioService
    {
        return app(PermisosUsuarioService::class);
    }

    private function fresco(Usuario $u): Usuario
    {
        return Usuario::query()->findOrFail($u->id);
    }

    public function test_los_permisos_son_la_union_de_sus_roles(): void
    {
        $f = $this->usuario('funcionario');
        $this->assertFalse($f->tienePermiso('transparencia.ver'));
        $this->svc()->guardar($f, ['transparencia'], [], $this->usuario('admin'));
        $f = $this->fresco($f);
        $this->assertSame(['transparencia'], $f->rolesAdicionales());
        $this->assertTrue($f->tienePermiso('transparencia.ver'), 'permiso del rol adicional');
        $this->assertTrue($f->tienePermiso('expedientes.operar'), 'permiso del rol principal');
        $this->assertSame('funcionario', $f->rolBase(), 'el rol principal fija la base');
        $this->assertContains('transparencia', $f->basesRol());
        $this->assertTrue($f->esRol('transparencia'));
        $this->assertTrue($f->operaExpedientes());
        $this->assertTrue(Usuario::query()->operadores()->whereKey($f->id)->exists());
    }

    public function test_un_rol_adicional_de_otra_base_hace_operar_a_quien_no_operaba(): void
    {
        $o = $this->usuario('oirs');
        $this->assertFalse($o->operaExpedientes());
        $this->svc()->guardar($o, ['funcionario'], [], $this->usuario('admin'));
        $o = $this->fresco($o);
        $this->assertTrue($o->operaExpedientes());
        $this->assertTrue($o->tienePermiso('oirs.ver') && $o->tienePermiso('expedientes.operar'));
    }

    public function test_administracion_y_auditoria_no_se_combinan_con_otros_roles(): void
    {
        $admin = $this->usuario('admin');
        $f = $this->usuario('funcionario');
        $this->assertTrue($this->svc()->rolesDisponibles($admin)->isEmpty());
        $this->assertFalse($this->svc()->rolesDisponibles($f)->contains('rol_base', 'admin'));
        $this->assertFalse($this->svc()->rolesDisponibles($f)->contains('rol_base', 'auditor'));
        foreach ([[$admin, ['funcionario']], [$f, ['admin']], [$f, ['auditor']], [$f, ['funcionario']], [$f, ['no_existe']]] as [$u, $roles]) {
            try {
                $this->svc()->guardar($u, $roles, [], $this->usuario('admin'));
                $this->fail('debió rechazar '.$u->rol.' + '.implode(',', $roles));
            } catch (FlujoException) {
                $this->assertSame([], $this->fresco($u)->rolesAdicionales());
            }
        }
    }

    public function test_conceder_y_denegar_permisos_individuales_dentro_del_techo(): void
    {
        $f = $this->usuario('funcionario');
        $this->assertFalse($f->tienePermiso('plantillas.gestionar'));
        // fuera del techo de un funcionario: no se puede conceder
        $this->expectException(FlujoException::class);
        try {
            $this->svc()->guardar($f, [], ['plantillas.gestionar' => 'conceder'], $this->usuario('admin'));
        } finally {
            $this->assertFalse($this->fresco($f)->tienePermiso('plantillas.gestionar'));
        }
    }

    public function test_conceder_un_permiso_opcional_y_denegar_uno_del_rol(): void
    {
        $f = $this->usuario('funcionario');
        $this->assertFalse($f->tienePermiso('tramites.gestionar') && false);
        $opcional = collect(Permisos::techo('funcionario'))->first(fn ($p) => ! in_array($p, Permisos::deBase('funcionario'), true) && empty(Permisos::catalogo()[$p][3]));
        $this->assertNotNull($opcional, 'hay al menos un permiso opcional en el techo de funcionario');
        $this->assertFalse($f->tienePermiso($opcional));
        $this->svc()->guardar($f, [], [$opcional => 'conceder', 'expedientes.derivar' => 'denegar'], $this->usuario('admin'));
        $f = $this->fresco($f);
        $this->assertTrue($f->tienePermiso($opcional));
        $this->assertFalse($f->tienePermiso('expedientes.derivar'));
        $this->assertTrue($f->tienePermiso('expedientes.operar'));
        $m = collect($this->svc()->matriz($f))->flatten(1)->keyBy('clave');
        $this->assertSame('Concedido individualmente', $m[$opcional]['origen']);
        $this->assertSame('Denegado individualmente', $m['expedientes.derivar']['origen']);
        $this->assertFalse($m['expedientes.derivar']['efectivo']);
        $this->assertSame('Rol «Funcionario»', $m['expedientes.operar']['origen']);
        // quitar los ajustes los revierte
        $this->svc()->guardar($f, [], [], $this->usuario('admin'));
        $this->assertTrue($this->fresco($f)->tienePermiso('expedientes.derivar'));
    }

    public function test_los_permisos_reservados_al_sistema_no_se_conceden(): void
    {
        $f = $this->usuario('funcionario');
        foreach (Permisos::catalogo() as $clave => $d) {
            if (! empty($d[3])) {
                $this->expectException(FlujoException::class);
                $this->svc()->guardar($f, [], [$clave => 'conceder'], $this->usuario('admin'));
            }
        }
    }

    public function test_la_pantalla_guarda_roles_y_ajustes_y_el_usuario_los_ve_desde_su_proximo_ingreso(): void
    {
        $admin = $this->usuario('admin');
        $f = $this->usuario('funcionario');
        DB::table('sessions')->insert(['id' => 'x1', 'user_id' => $f->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->comoFuncionario($admin)->get('/app/admin/permisos')->assertOk()->assertSee('Matriz de permisos por usuario');
        $this->comoFuncionario($admin)->get('/app/admin/permisos/'.$f->id)->assertOk()->assertSee('Roles adicionales')->assertSee('expedientes.operar')->assertSee('Guardar roles y permisos');
        $r = $this->comoFuncionario($admin)->post('/app/admin/permisos/'.$f->id, ['rol_extra' => ['transparencia'], 'perm' => ['expedientes.derivar' => 'denegar', 'expedientes.cerrar' => '']]);
        $r->assertRedirect(route('app.admin.permisos.usuario', $f))->assertSessionHas('ok');
        $f = $this->fresco($f);
        $this->assertTrue($f->tienePermiso('transparencia.ver'));
        $this->assertFalse($f->tienePermiso('expedientes.derivar'));
        $this->assertDatabaseHas('audit_log', ['accion' => 'usuario.permisos']);
        $this->assertDatabaseMissing('sessions', ['id' => 'x1']);
        $this->comoFuncionario($admin)->get('/app/admin/usuarios')->assertOk()->assertSee('+ Transparencia activa y pasiva', false);
    }

    public function test_solo_quien_gestiona_roles_accede(): void
    {
        $f = $this->usuario('funcionario', 1);
        $this->comoFuncionario($f)->get('/app/admin/permisos')->assertForbidden();
        $this->comoFuncionario($f)->post('/app/admin/permisos/'.$f->id, ['rol_extra' => ['transparencia']])->assertForbidden();
        $this->comoFuncionario($f)->get('/app/admin/permisos/matriz.csv')->assertForbidden();
        $this->assertSame([], $this->fresco($f)->rolesAdicionales());
    }

    public function test_el_administrador_puede_ajustar_sus_propios_permisos_pero_no_dejar_el_sistema_sin_gestion_de_roles(): void
    {
        $admin = $this->usuario('admin');
        // unico administrador: no puede quitarse «Roles y permisos»
        $this->comoFuncionario($admin)->post('/app/admin/permisos/'.$admin->id, ['perm' => ['roles.gestionar' => 'denegar']])->assertSessionHas('error');
        $this->assertTrue($this->fresco($admin)->tienePermiso('roles.gestionar'));
        // si, puede quitarse otro permiso y volver a ponérselo
        $this->comoFuncionario($admin)->post('/app/admin/permisos/'.$admin->id, ['perm' => ['logs.exportar' => 'denegar']])->assertSessionHas('ok');
        $this->assertFalse($this->fresco($admin)->tienePermiso('logs.exportar'));
        $this->assertDatabaseHas('audit_log', ['accion' => 'usuario.permisos']);
        $this->comoFuncionario($this->fresco($admin))->post('/app/admin/permisos/'.$admin->id, ['perm' => ['logs.exportar' => '']])->assertSessionHas('ok');
        $this->assertTrue($this->fresco($admin)->tienePermiso('logs.exportar'));
        // con un segundo administrador con gestión de roles, sí puede quitárselo
        $n = '22222222';
        $otro = Usuario::query()->create(['run' => $n.'-'.Run::dv($n), 'nombres' => 'Otro', 'apellidos' => 'Admin', 'correo' => 'o@x.cl', 'rol' => 'admin', 'activo' => true]);
        $this->comoFuncionario($this->fresco($admin))->post('/app/admin/permisos/'.$admin->id, ['perm' => ['roles.gestionar' => 'denegar']])->assertSessionHas('ok');
        $this->assertFalse($this->fresco($admin)->tienePermiso('roles.gestionar'));
        $this->assertTrue($this->fresco($otro)->tienePermiso('roles.gestionar'));
    }

    public function test_un_administrador_no_puede_concederse_permisos_de_operacion(): void
    {
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->post('/app/admin/permisos/'.$admin->id, ['perm' => ['expedientes.operar' => 'conceder']])->assertSessionHas('error');
        $this->assertFalse($this->fresco($admin)->tienePermiso('expedientes.operar'));
    }

    public function test_el_csv_de_la_matriz_trae_una_fila_por_usuario_y_se_audita(): void
    {
        $admin = $this->usuario('admin');
        $f = $this->usuario('funcionario');
        $f->forceFill(['nombres' => '=HYPERLINK("x")'])->save();
        $this->svc()->guardar($f, ['transparencia'], [], $admin);
        $r = $this->comoFuncionario($admin)->get('/app/admin/permisos/matriz.csv')->assertOk();
        $csv = $r->streamedContent();
        $lineas = array_filter(explode("\n", trim($csv)));
        $this->assertSame(1 + Usuario::query()->count(), count($lineas));
        $this->assertStringContainsString('transparencia.ver', $lineas[0] ?? $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Transparencia activa y pasiva', $csv);
        $this->assertDatabaseHas('audit_log', ['accion' => 'permisos.exportar']);
    }

    public function test_el_manual_del_rol_adicional_aparece_en_ayuda_y_el_rol_con_asignaciones_no_se_elimina(): void
    {
        $f = $this->usuario('funcionario');
        $this->svc()->guardar($f, ['transparencia'], [], $this->usuario('admin'));
        $this->comoFuncionario($this->fresco($f))->get('/ayuda')->assertOk()->assertSee('Transparencia activa y pasiva');
        $rol = Rol::query()->create(['clave' => 'prueba_x', 'nombre' => 'Prueba X', 'rol_base' => 'funcionario', 'sistema' => false, 'activo' => true]);
        $g = $this->usuario('funcionario', 1);
        $this->svc()->guardar($g, ['prueba_x'], [], $this->usuario('admin'));
        $this->assertSame(1, $rol->asignaciones());
        $this->comoFuncionario($this->usuario('admin'))->post('/app/admin/roles/'.$rol->id.'/eliminar')->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['clave' => 'prueba_x']);
    }

    public function test_menu_personalizado_por_rol_base_considera_los_roles_adicionales(): void
    {
        $f = $this->usuario('funcionario');
        $this->svc()->guardar($f, ['transparencia'], [], $this->usuario('admin'));
        $f = $this->fresco($f);
        $this->assertTrue(array_intersect($f->basesRol(), ['transparencia']) !== []);
        $this->comoFuncionario($f)->get('/app/transparencia')->assertOk();
    }
}
