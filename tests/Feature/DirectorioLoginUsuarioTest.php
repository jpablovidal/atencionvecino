<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Directorio\DirectorioFuncionarios;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Ingreso de funcionarios con «username» y contraseña de la tabla sistemas_users (hash `pass`), sin ClaveÚnica. */
class DirectorioLoginUsuarioTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private string $dv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        RateLimiter::clear('x');
        config(['database.connections.sgd_directorio' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'sgd.directorio.enabled' => true, 'sgd.directorio.host' => 'x',
            'sgd.directorio.database' => 'sistemas_usuarios', 'sgd.directorio.username' => 'ro', 'sgd.directorio.login_usuario' => true]);
        DB::purge(DirectorioFuncionarios::CONEXION);
        Schema::connection(DirectorioFuncionarios::CONEXION)->create('sistemas_users', function ($t) {
            $t->increments('id');
            foreach (['habilitado', 'credencial', 'dv', 'nombre', 'apellido', 'cargo', 'direccion', 'departamento', 'unidad', 'mail', 'username', 'password', 'pass'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('rut');
            $t->date('fecha_termino')->nullable();
        });
        $this->dv = Run::dv('19142479');
        $this->fila();
    }

    private function fila(array $o = []): void
    {
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->insert(array_merge(['habilitado' => 'si', 'credencial' => 'Vigente', 'rut' => 19142479, 'dv' => $this->dv, 'nombre' => 'Rocío', 'apellido' => 'Abarca',
            'cargo' => 'Profesional', 'direccion' => 'Dirección de Recursos Humanos', 'departamento' => 'Dirección de Recursos Humanos', 'unidad' => '', 'mail' => 'rabarca@santodomingo.cl', 'username' => 'rabarca',
            'password' => 'TEXTO-PLANO-NO-USAR', 'pass' => Hash::make('Clave-Segura-2026')], $o));
    }

    private function entrar(string $u = 'rabarca', string $p = 'Clave-Segura-2026')
    {
        return $this->post('/login/usuario', ['username' => $u, 'password' => $p]);
    }

    public function test_ingresa_con_username_y_contrasena_y_se_crea_el_funcionario(): void
    {
        $this->entrar()->assertRedirect(route('app.bandeja'));
        $u = Usuario::query()->where('run', '19142479-'.$this->dv)->first();
        $this->assertNotNull($u);
        $this->assertTrue($u->directorio);
        $this->assertSame('funcionario', $u->rol);
        $this->assertAuthenticatedAs($u);
        $this->assertFalse(session()->has('ciudadano'), 'sin ClaveÚnica no hay identidad de vecino(a) acreditada');
        $this->assertFalse((bool) session('via_cu'));
        $this->assertDatabaseHas('audit_log', ['accion' => 'auth.login']);
    }

    public function test_el_nombre_de_usuario_no_distingue_mayusculas(): void
    {
        $this->entrar('RABARCA')->assertRedirect(route('app.bandeja'));
    }

    public function test_contrasena_incorrecta_usuario_inexistente_y_mensaje_unico(): void
    {
        $a = $this->entrar('rabarca', 'otra')->assertRedirect()->assertSessionHas('error', 'Usuario o contraseña incorrectos.');
        $this->entrar('noexiste', 'Clave-Segura-2026')->assertSessionHas('error', 'Usuario o contraseña incorrectos.');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_log', ['accion' => 'auth.fallo']);
    }

    public function test_nunca_se_usa_la_columna_password_en_texto_plano(): void
    {
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->update(['pass' => null]);
        $this->entrar('rabarca', 'TEXTO-PLANO-NO-USAR')->assertSessionHas('error');
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->update(['pass' => 'no-es-un-hash']);
        $this->entrar('rabarca', 'no-es-un-hash')->assertSessionHas('error');
        $this->assertGuest();
        $sql = [];
        DB::connection(DirectorioFuncionarios::CONEXION)->listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });
        $this->entrar();
        $propios = array_values(array_filter($sql, fn ($q) => str_contains($q, 'sistemas_users')));
        $this->assertNotEmpty($propios);
        foreach ($propios as $q) {
            $this->assertDoesNotMatchRegularExpression('/\bpassword\b|\*/', $q);
        }
    }

    public function test_no_entra_quien_no_esta_habilitado_vigente_o_con_termino_vencido(): void
    {
        foreach ([['habilitado' => 'no'], ['credencial' => 'Vencida'], ['fecha_termino' => '2020-01-01']] as $o) {
            DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->delete();
            $this->fila($o);
            $this->entrar()->assertSessionHas('error', 'Usuario o contraseña incorrectos.');
            RateLimiter::clear('usuario:'.hash('sha256', 'rabarca|127.0.0.1'));
            RateLimiter::clear('usuario-nombre:'.hash('sha256', 'rabarca'));
        }
        $this->assertGuest();
    }

    public function test_la_administracion_no_puede_ingresar_por_aqui(): void
    {
        $admin = $this->usuario('admin');
        [$n, $d] = explode('-', $admin->run);
        DB::connection(DirectorioFuncionarios::CONEXION)->table('sistemas_users')->update(['rut' => (int) $n, 'dv' => $d]);
        $this->entrar()->assertSessionHas('error', 'Usuario o contraseña incorrectos.');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_log', ['accion' => 'auth.fallo']);
    }

    public function test_quien_fue_dado_de_baja_localmente_no_ingresa(): void
    {
        $this->entrar()->assertRedirect(route('app.bandeja'));
        Usuario::query()->where('run', '19142479-'.$this->dv)->update(['activo' => false]);
        $this->flushSession();
        $this->entrar()->assertSessionHas('error');
        $this->app['auth']->forgetGuards(); // cada ingreso se evalúa como una petición nueva
        $this->assertGuest();
    }

    public function test_bloqueo_tras_varios_intentos_fallidos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->entrar('rabarca', 'mala'.$i);
        }
        $this->entrar()->assertSessionHas('error', 'Demasiados intentos. Espere 15 minutos.');
        $this->assertGuest();
    }

    public function test_solo_existe_con_el_directorio_activo_y_la_opcion_encendida(): void
    {
        $this->get('/login/usuario')->assertOk()->assertSee('Ingreso con usuario y contraseña');
        $this->get('/ingreso')->assertSee('Ingresar con usuario y contraseña')->assertSee(route('login.usuario'), false);
        config(['sgd.directorio.login_usuario' => false]);
        $this->get('/login/usuario')->assertNotFound();
        $this->entrar()->assertNotFound();
        $this->get('/ingreso')->assertDontSee(route('login.usuario'), false);
        config(['sgd.directorio.login_usuario' => true, 'sgd.directorio.enabled' => false]);
        $this->get('/login/usuario')->assertNotFound();
    }

    public function test_valida_el_formato_del_usuario(): void
    {
        $this->post('/login/usuario', ['username' => "a' or '1'='1", 'password' => 'x'])->assertSessionHasErrors('username');
        $this->post('/login/usuario', ['username' => str_repeat('a', 31), 'password' => 'x'])->assertSessionHasErrors('username');
    }

    public function test_directorio_caido_muestra_un_mensaje_y_no_bloquea_clave_unica(): void
    {
        config(['database.connections.sgd_directorio' => ['driver' => 'sqlite', 'database' => '/ruta/que/no/existe/x.sqlite', 'prefix' => '']]);
        DB::purge(DirectorioFuncionarios::CONEXION);
        $this->entrar()->assertSessionHas('error');
        $this->assertGuest();
        $this->get('/login')->assertRedirect(); // ClaveÚnica sigue disponible
    }
}
