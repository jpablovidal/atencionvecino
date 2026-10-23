<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Unidad;
use App\Models\UnidadInterna;
use App\Models\Usuario;
use App\Services\Admin\AsignacionMasivaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Asignación masiva de dirección, roles, permisos y estado con las reglas de seguridad por persona. */
class AsignacionMasivaTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function svc(): AsignacionMasivaService
    {
        return app(AsignacionMasivaService::class);
    }

    public function test_la_pantalla_lista_a_todas_las_personas_y_filtra(): void
    {
        $admin = $this->usuario('admin');
        $r = $this->comoFuncionario($admin)->get(route('app.admin.asignacion'))->assertOk();
        foreach (Usuario::query()->limit(5)->get() as $u) {
            $r->assertSee($u->run);
        }
        $this->comoFuncionario($admin)->get(route('app.admin.asignacion', ['estado' => 'inactivos', 'q' => 'zzzz-no-existe']))->assertOk()->assertSee('No hay personas');
        $this->comoFuncionario($this->usuario('funcionario'))->get(route('app.admin.asignacion'))->assertForbidden();
    }

    public function test_asigna_direccion_a_varias_personas_y_valida_el_departamento(): void
    {
        $admin = $this->usuario('admin');
        $un = Unidad::query()->firstOrFail();
        $f1 = $this->usuario('funcionario', 1);
        $f2 = $this->usuario('funcionario', 2);
        $res = $this->svc()->aplicar('unidad', [$f1->id, $f2->id], ['unidad_id' => $un->id], $admin);
        $this->assertSame(2, $res['aplicados']);
        $this->assertSame($un->id, $f1->fresh()->unidad_id);
        $this->assertSame($un->id, $f2->fresh()->unidad_id);
        $otra = Unidad::query()->where('id', '!=', $un->id)->first();
        $dep = $otra ? UnidadInterna::query()->where('tipo', 'departamento')->where('unidad_id', $otra->id)->first() : null;
        if ($dep) {
            $this->expectException(FlujoException::class);
            $this->svc()->aplicar('unidad', [$f1->id], ['unidad_id' => $un->id, 'departamento_id' => $dep->id], $admin);
        }
    }

    public function test_agrega_y_quita_roles_adicionales_y_respeta_la_separacion_de_funciones(): void
    {
        $admin = $this->usuario('admin');
        $f = $this->usuario('funcionario', 1);
        $res = $this->svc()->aplicar('roles_agregar', [$f->id], ['roles' => ['transparencia']], $admin);
        $this->assertSame(1, $res['aplicados']);
        $this->assertContains('transparencia', $f->fresh()->rolesAdicionales());
        $this->svc()->aplicar('roles_quitar', [$f->id], ['roles' => ['transparencia']], $admin);
        $this->assertNotContains('transparencia', $f->fresh()->rolesAdicionales());
        $res = $this->svc()->aplicar('roles_agregar', [$admin->id], ['roles' => ['transparencia']], $this->usuario('admin'));
        $this->assertSame(0, $res['aplicados']);
        $this->assertCount(1, $res['omitidos']);
        $this->expectException(FlujoException::class);
        $this->svc()->aplicar('roles_agregar', [$f->id], ['roles' => ['no_existe']], $admin);
    }

    public function test_concede_deniega_y_quita_ajustes_de_permisos(): void
    {
        $admin = $this->usuario('admin');
        $f = $this->usuario('funcionario', 1);
        $this->svc()->aplicar('roles_agregar', [$f->id], ['roles' => ['transparencia']], $admin);
        $this->svc()->aplicar('permisos', [$f->id], ['perm' => ['expedientes.crear' => 'denegar', 'transparencia.ver' => 'conceder']], $admin);
        $ind = $f->fresh()->permisosIndividuales();
        $this->assertSame('denegar', $ind['expedientes.crear']);
        $this->assertSame('conceder', $ind['transparencia.ver']);
        $this->svc()->aplicar('permisos', [$f->id], ['perm' => ['expedientes.crear' => 'limpiar']], $admin);
        $this->assertArrayNotHasKey('expedientes.crear', $f->fresh()->permisosIndividuales());
        $this->expectException(FlujoException::class);
        $this->svc()->aplicar('permisos', [$f->id], ['perm' => ['inexistente.x' => 'conceder']], $admin);
    }

    public function test_cambia_el_rol_principal_y_protege_a_quien_actua_y_al_ultimo_admin(): void
    {
        $admin = $this->usuario('admin');
        $f = $this->usuario('funcionario', 1);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $f->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);
        $res = $this->svc()->aplicar('rol', [$f->id, $admin->id], ['rol' => 'secretario'], $admin);
        $this->assertSame(1, $res['aplicados']);
        $this->assertSame('secretario', $f->fresh()->rol);
        $this->assertSame('admin', $admin->fresh()->rol, 'no puede cambiarse el propio rol');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $f->id)->count());
        $otro = $this->usuario('secretario', 1);
        $res = $this->svc()->aplicar('rol', [$admin->id], ['rol' => 'funcionario'], $otro);
        $this->assertSame(1, count($res['omitidos']) + $res['aplicados']);
    }

    public function test_desactiva_personas_pero_no_al_actor_ni_al_ultimo_administrador(): void
    {
        $admin = $this->usuario('admin');
        $f = $this->usuario('funcionario', 1);
        $res = $this->svc()->aplicar('estado', [$f->id, $admin->id], ['activo' => '0'], $admin);
        $this->assertSame(1, $res['aplicados']);
        $this->assertFalse($f->fresh()->activo);
        $this->assertTrue($admin->fresh()->activo);
        $this->svc()->aplicar('estado', [$f->id], ['activo' => '1'], $admin);
        $this->assertTrue($f->fresh()->activo);
    }

    public function test_el_formulario_aplica_con_alcance_por_filtro_y_registra_la_bitacora(): void
    {
        $admin = $this->usuario('admin');
        $un = Unidad::query()->firstOrFail();
        $this->comoFuncionario($admin)->post(route('app.admin.asignacion.aplicar'), ['accion' => 'unidad', 'alcance' => 'filtro', 'rol' => 'funcionario', 'unidad_id' => $un->id])->assertRedirect();
        $this->assertSame(0, Usuario::query()->where('rol', 'funcionario')->where(fn ($q) => $q->whereNull('unidad_id')->orWhere('unidad_id', '!=', $un->id))->count());
        $this->assertDatabaseHas('audit_log', ['accion' => 'usuario.masivo']);
        $this->comoFuncionario($admin)->post(route('app.admin.asignacion.aplicar'), ['accion' => 'unidad', 'alcance' => 'marcados'])->assertSessionHas('error');
    }
}
