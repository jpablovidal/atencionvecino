<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\ExpedienteService;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Superadministrador: administrador con acceso a todas las vistas del sistema. */
class SuperadminTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    private function otroAdmin(): Usuario
    {
        $n = '22222222';

        return Usuario::query()->firstOrCreate(['run' => $n.'-'.Run::dv($n)], ['nombres' => 'Otro', 'apellidos' => 'Admin', 'correo' => 'otro@santodomingo.invalid', 'rol' => 'admin', 'activo' => true]);
    }

    private function superadmin(): Usuario
    {
        $u = $this->usuario('admin');
        $u->forceFill(['superadmin' => true])->save();

        return $u->refresh();
    }

    public function test_tiene_todos_los_permisos_y_el_admin_comun_no(): void
    {
        $s = $this->superadmin();
        $this->assertTrue($s->esSuperadmin());
        foreach (['expedientes.operar', 'decretopago.ver', 'comunicaciones.ver', 'transparencia.ver', 'archivo.ver', 'docdigital.gestionar', 'roles.gestionar'] as $p) {
            $this->assertTrue($s->tienePermiso($p), $p);
        }
        $this->assertFalse($s->tienePermiso('permiso.inexistente'));
        $comun = $this->otroAdmin();
        $this->assertFalse($comun->esSuperadmin());
        $this->assertFalse($comun->tienePermiso('expedientes.operar'));
    }

    public function test_solo_vale_con_rol_de_administracion_y_cuenta_activa_y_no_se_asigna_en_masa(): void
    {
        $f = $this->usuario('funcionario');
        $f->forceFill(['superadmin' => true])->save();
        $this->assertFalse($f->refresh()->esSuperadmin(), 'un funcionario no puede ser superadministrador');
        $s = $this->superadmin();
        $s->forceFill(['activo' => false])->save();
        $this->assertFalse($s->refresh()->esSuperadmin());
        $n = Usuario::query()->create(['run' => '33333333-3', 'nombres' => 'X', 'apellidos' => 'Y', 'correo' => 'x@y.cl', 'rol' => 'admin', 'superadmin' => true, 'activo' => true]);
        $this->assertFalse((bool) $n->refresh()->superadmin, 'asignación masiva ignorada');
    }

    public function test_ve_el_contenido_de_todas_las_unidades_y_el_admin_comun_no(): void
    {
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
        $s = $this->superadmin();
        $this->comoFuncionario($s)->get("/app/expedientes/{$e->id}")->assertOk();
        $this->comoFuncionario($this->otroAdmin())->get("/app/expedientes/{$e->id}")->assertForbidden();
        $this->comoFuncionario($s)->get('/app/docdigital')->assertOk()->assertDontSee('no ve el contenido de las comunicaciones');
        $this->comoFuncionario($this->otroAdmin())->get('/app/docdigital')->assertOk()->assertSee('no ve el contenido de las comunicaciones');
        $this->comoFuncionario($s)->get('/app/vigentes')->assertOk();
    }

    public function test_todas_las_vistas_de_la_aplicacion_se_abren(): void
    {
        $s = $this->superadmin();
        $rutas = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), 'app/') && ! str_contains($r->uri(), '{'))
            ->map(fn ($r) => '/'.$r->uri())->unique()->values();
        $this->assertGreaterThan(40, $rutas->count());
        $malas = [];
        foreach ($rutas as $u) {
            $st = $this->comoFuncionario($s)->get($u)->getStatusCode();
            if ($st === 403 || $st >= 500) {
                $malas[] = "$u => $st";
            }
        }
        $this->assertSame([], $malas, 'vistas que no abren para el superadministrador: '.implode('; ', $malas));
    }

    public function test_el_comando_exige_rol_de_administracion(): void
    {
        $f = $this->usuario('funcionario');
        $this->artisan('sgd:superadmin', ['run' => $f->run])->assertFailed();
        $a = $this->usuario('admin');
        $this->artisan('sgd:superadmin', ['run' => $a->run])->assertSuccessful();
        $this->assertTrue($a->refresh()->esSuperadmin());
        $this->assertDatabaseHas('audit_log', ['accion' => 'usuario.superadmin']);
        $this->artisan('sgd:superadmin', ['run' => $a->run, '--quitar' => true])->assertSuccessful();
        $this->assertFalse($a->refresh()->esSuperadmin());
        $this->artisan('sgd:superadmin', ['run' => '99999999-9'])->assertFailed();
    }

    public function test_solo_un_superadministrador_otorga_la_condicion_desde_usuarios(): void
    {
        $objetivo = $this->otroAdmin();
        $datos = ['run' => $objetivo->run, 'nombres' => $objetivo->nombres, 'apellidos' => $objetivo->apellidos, 'correo' => $objetivo->correo, 'rol' => $objetivo->rol, 'superadmin_form' => 1, 'superadmin' => 1];
        $this->comoFuncionario($this->usuario('admin', 1))->post('/app/admin/usuarios/'.$objetivo->id, $datos);
        $this->assertFalse($objetivo->refresh()->esSuperadmin(), 'un administrador común no puede otorgarla');
        $s = $this->superadmin();
        $this->comoFuncionario($s)->get('/app/admin/usuarios/'.$objetivo->id)->assertOk()->assertSee('name="superadmin"', false);
        $this->comoFuncionario($s)->post('/app/admin/usuarios/'.$objetivo->id, $datos)->assertRedirect();
        $this->assertTrue($objetivo->refresh()->esSuperadmin());
    }
}
