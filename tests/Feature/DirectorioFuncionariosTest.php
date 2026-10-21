<?php

namespace Tests\Feature;

use App\Models\ApiConfig;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\ClaveUnicaService;
use App\Services\Directorio\DirectorioFuncionarios;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Directorio institucional (base «sistemas_usuarios»): quien entra con ClaveÚnica y figura en él es funcionario(a). */
class DirectorioFuncionariosTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        config(['database.connections.sgd_directorio' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge(DirectorioFuncionarios::CONEXION);
        config(['sgd.directorio.enabled' => true, 'sgd.directorio.host' => 'x', 'sgd.directorio.database' => 'sistemas_usuarios', 'sgd.directorio.username' => 'ro', 'sgd.claveunica.mode' => 'real',
            'sgd.claveunica.client_id' => 'cid', 'sgd.claveunica.client_secret' => 'sec', 'sgd.claveunica.redirect_uri' => 'https://x/cb']);
        Schema::connection(DirectorioFuncionarios::CONEXION)->create('sistemas_users', function ($t) {
            $t->increments('id');
            foreach (['habilitado', 'credencial', 'dv', 'nombre', 'apellido', 'cargo', 'direccion', 'departamento', 'unidad', 'mail', 'password', 'pass'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('rut');
            $t->date('fecha_termino')->nullable();
        });
    }

    private function fila(array $o = []): void
    {
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->insert(array_merge(['habilitado' => 'si', 'credencial' => 'Vigente', 'rut' => 19142479, 'dv' => '4', 'nombre' => 'Rocío', 'apellido' => 'Abarca',
            'cargo' => 'Profesional', 'direccion' => 'Dirección de Recursos Humanos', 'departamento' => 'Dirección de Recursos Humanos', 'unidad' => '', 'mail' => 'rabarca@santodomingo.cl', 'password' => 'TEXTO-PLANO-NO-LEER', 'pass' => '$2y$10$hashNoLeer'], $o));
    }

    private function entrar(string $numero, string $dv, string $via = 'auto')
    {
        $this->app->forgetInstance(ClaveUnicaService::class);
        Http::swap(new Factory); // cada ingreso parte sin simulaciones previas
        Http::fake(['https://accounts.claveunica.gob.cl/openid/token/' => Http::response(['access_token' => 't']), 'https://accounts.claveunica.gob.cl/openid/userinfo/' => Http::response(['RolUnico' => ['numero' => (int) $numero, 'DV' => $dv], 'name' => ['nombres' => ['Rocío'], 'apellidos' => ['Abarca']]])]);

        $this->flushSession();
        $r = $this->withSession(['cu' => ['state' => hash('sha256', 'e'), 'via' => $via, 'exp' => time() + 60]])->get('/claveunica/callback?code=c&state=e');
        $this->app['auth']->forgetGuards(); // cada ingreso se evalúa como una petición nueva

        return $r;
    }

    public function test_el_funcionario_del_directorio_entra_como_tal_y_se_crea_su_usuario(): void
    {
        $dv = Run::dv('19142479');
        $this->fila(['dv' => $dv]);
        $r = $this->entrar('19142479', $dv);
        $u = Usuario::query()->where('run', '19142479-'.$dv)->first();
        $this->assertNotNull($u);
        $this->assertTrue($u->directorio);
        $this->assertSame('funcionario', $u->rol);
        $this->assertFalse((bool) $u->puede_firmar);
        $this->assertSame('Dirección de Recursos Humanos', Unidad::query()->find($u->unidad_id)->nombre);
        $r->assertRedirect(route('espacio')); // funcionario(a) que además es vecino(a): elige su espacio
        $this->assertDatabaseHas('audit_log', ['accion' => 'usuario.directorio_alta']);
    }

    public function test_quien_no_figura_entra_como_vecino_y_no_se_crea_nada(): void
    {
        $this->fila();
        $dv = Run::dv('11111111');
        $r = $this->entrar('11111111', $dv);
        $this->assertNull(Usuario::query()->where('run', '11111111-'.$dv)->first());
        $r->assertRedirect(route('ciudadano.index'));
    }

    public function test_no_cuentan_deshabilitados_credencial_vencida_ni_fecha_de_termino(): void
    {
        $dir = app(DirectorioFuncionarios::class);
        $dv = Run::dv('19142479');
        foreach ([['habilitado' => 'no'], ['credencial' => 'Vencida'], ['fecha_termino' => '2020-01-01'], ['dv' => $dv === '0' ? '1' : '0']] as $o) {
            DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->delete();
            $this->fila(array_merge(['dv' => $dv], $o));
            $this->assertNull($dir->buscar('19142479-'.$dv), json_encode($o));
        }
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->delete();
        $this->fila(['dv' => $dv, 'fecha_termino' => now()->addYear()->format('Y-m-d')]);
        $this->assertSame('Rocío', $dir->buscar('19142479-'.$dv)['nombres']);
    }

    public function test_nunca_se_leen_las_columnas_de_contrasena(): void
    {
        $dv = Run::dv('19142479');
        $this->fila(['dv' => $dv]);
        $sql = [];
        DB::connection(DirectorioFuncionarios::CONEXION)->listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });
        $f = app(DirectorioFuncionarios::class)->buscar('19142479-'.$dv);
        $this->assertNotEmpty($sql);
        foreach ($sql as $q) {
            $this->assertDoesNotMatchRegularExpression('/\b(password|pass)\b|\*/', $q);
        }
        $this->assertStringNotContainsString('TEXTO-PLANO', json_encode($f));
    }

    public function test_quien_deja_de_figurar_pierde_el_acceso_pero_las_cuentas_manuales_no(): void
    {
        $dv = Run::dv('19142479');
        $this->fila(['dv' => $dv]);
        $this->entrar('19142479', $dv)->assertRedirect(route('espacio'));
        $u = Usuario::query()->where('run', '19142479-'.$dv)->firstOrFail();
        $this->assertTrue((bool) $u->activo);
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->update(['habilitado' => 'no']);
        $this->entrar('19142479', $dv)->assertRedirect(route('ciudadano.index')); // sigue siendo vecino(a), ya no funcionario(a)
        $this->assertFalse((bool) $u->refresh()->activo);
        $this->assertDatabaseHas('audit_log', ['accion' => 'usuario.directorio_baja']);
        // administración creada a mano: no depende del directorio
        $admin = $this->usuario('admin');
        [$n, $d] = explode('-', $admin->run);
        $this->entrar($n, $d)->assertRedirect(route('espacio'));
        $this->assertTrue((bool) $admin->refresh()->activo);
    }

    public function test_directorio_caido_no_bloquea_a_los_existentes_ni_crea_nuevos(): void
    {
        config(['database.connections.sgd_directorio' => ['driver' => 'sqlite', 'database' => '/ruta/que/no/existe/x.sqlite', 'prefix' => '']]);
        DB::purge(DirectorioFuncionarios::CONEXION);
        $admin = $this->usuario('admin');
        [$n, $d] = explode('-', $admin->run);
        $this->entrar($n, $d)->assertRedirect(route('espacio'));
        $dv = Run::dv('19142479');
        $this->entrar('19142479', $dv)->assertRedirect(route('ciudadano.index'));
        $this->assertNull(Usuario::query()->where('run', '19142479-'.$dv)->first());
    }

    public function test_una_cuenta_desactivada_a_mano_no_se_recrea_ni_se_reactiva(): void
    {
        $dv = Run::dv('19142479');
        $this->fila(['dv' => $dv]);
        $this->entrar('19142479', $dv)->assertRedirect(route('espacio'));
        $u = Usuario::query()->where('run', '19142479-'.$dv)->firstOrFail();
        $u->forceFill(['activo' => false])->save();
        $this->entrar('19142479', $dv)->assertRedirect(route('ciudadano.index')); // sigue en el directorio, pero administración la desactivó
        $this->assertFalse((bool) $u->refresh()->activo);
        $this->assertSame(1, Usuario::query()->where('run', '19142479-'.$dv)->count());
    }

    public function test_sin_el_directorio_activo_todo_sigue_igual(): void
    {
        config(['sgd.directorio.enabled' => false]);
        $dv = Run::dv('19142479');
        $this->fila(['dv' => $dv]);
        $this->entrar('19142479', $dv)->assertRedirect(route('ciudadano.index'));
        $this->assertNull(Usuario::query()->where('run', '19142479-'.$dv)->first());
    }

    public function test_la_pantalla_de_configuracion_valida_y_guarda_la_contrasena_cifrada(): void
    {
        $this->fila();
        $a = $this->comoFuncionario($this->usuario('admin'));
        $h = $a->get('/app/admin/api/directorio')->assertOk()->getContent();
        $this->assertStringContainsString('Conexión a la base sistemas_usuarios', $h);
        $a->post('/app/admin/api/directorio', ['activo' => 1, 'enabled' => 1, 'host' => 'localhost', 'port' => '3306', 'database' => 'sistemas_usuarios', 'username' => 'ro', 'password' => 'CLAVE-SECRETA-DB', 'tabla' => 'sistemas_users', 'rol_defecto' => 'funcionario', 'crear_unidades' => 1])->assertSessionHas('ok');
        $c = ApiConfig::query()->where('servicio', 'directorio')->first();
        $this->assertStringNotContainsString('CLAVE-SECRETA-DB', (string) $c->secretos.(string) $c->valores);
        $a->post('/app/admin/api/directorio', ['activo' => 1, 'host' => 'x;drop', 'rol_defecto' => 'funcionario'])->assertSessionHas('error');
        $a->post('/app/admin/api/directorio', ['activo' => 1, 'rol_defecto' => 'admin'])->assertSessionHas('error');
        config(['sgd.directorio.enabled' => true, 'sgd.directorio.host' => 'x', 'sgd.directorio.database' => 'sistemas_usuarios', 'sgd.directorio.username' => 'ro']);
        $filas = collect($a->post('/app/admin/api/directorio/probar')->getSession()->get('pruebas'));
        $this->assertTrue($filas->first(fn ($f) => str_starts_with($f[0], 'Conexión'))[1]);
        $this->assertTrue($filas->first(fn ($f) => str_starts_with($f[0], 'Tabla'))[1]);
        $this->assertFalse($filas->first(fn ($f) => str_starts_with($f[0], 'Contraseñas'))[1], 'avisa de las columnas de contraseña');
    }

    public function test_una_columna_opcional_ausente_no_impide_reconocer_al_funcionario(): void
    {
        Schema::connection(DirectorioFuncionarios::CONEXION)->drop('sistemas_users');
        Schema::connection(DirectorioFuncionarios::CONEXION)->create('sistemas_users', function ($t) {
            $t->increments('id');
            foreach (['habilitado', 'credencial', 'dv', 'nombre', 'apellido', 'cargo', 'pass'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('rut');
        });
        $dv = Run::dv('19142479');
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->insert(['habilitado' => 'si', 'credencial' => 'Vigente', 'rut' => 19142479, 'dv' => $dv, 'nombre' => 'Rocío', 'apellido' => 'Abarca', 'cargo' => 'Profesional']);
        $this->assertNotNull(app(DirectorioFuncionarios::class)->buscar('19142479-'.$dv));
        $this->entrar('19142479', $dv)->assertRedirect(route('espacio'));
    }

    public function test_el_diagnostico_por_run_explica_cada_caso_sin_datos_personales(): void
    {
        $dv = Run::dv('19142479');
        $this->fila(['dv' => $dv]);
        $d = app(DirectorioFuncionarios::class);
        $ok = $d->diagnosticarRun('19142479-'.$dv);
        $this->assertTrue(collect($ok)->every(fn ($f) => $f[1]), json_encode($ok));
        $this->assertStringNotContainsString('Rocío', json_encode($ok, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('solo como vecino', json_encode($d->diagnosticarRun('11111111-1'), JSON_UNESCAPED_UNICODE));
        $mal = $dv === '0' ? '1' : '0';
        $this->assertStringContainsString('No coincide', json_encode($d->diagnosticarRun('19142479-'.$mal), JSON_UNESCAPED_UNICODE));
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->update(['credencial' => 'Caducada']);
        $this->assertStringContainsString('credencial', json_encode($d->diagnosticarRun('19142479-'.$dv), JSON_UNESCAPED_UNICODE));
        config(['sgd.directorio.enabled' => false]);
        $this->assertStringContainsString('active el directorio', json_encode($d->diagnosticarRun('19142479-'.$dv), JSON_UNESCAPED_UNICODE));
    }

    public function test_el_panel_y_el_comando_consultan_un_run(): void
    {
        $dv = Run::dv('19142479');
        $this->fila(['dv' => $dv]);
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->get('/app/admin/api/directorio')->assertOk()->assertSee('Probar con un RUN');
        $this->comoFuncionario($admin)->post('/app/admin/api-directorio-prueba', ['run' => '19142479-'.$dv])->assertRedirect();
        $this->artisan('directorio:consultar', ['run' => '19142479-'.$dv])->assertSuccessful();
        $this->artisan('directorio:consultar', ['run' => '11111111-1'])->assertFailed();
    }
}
