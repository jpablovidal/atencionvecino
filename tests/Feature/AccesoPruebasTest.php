<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CuentaPrueba;
use App\Support\Ambiente;
use App\Support\ProductionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Escenario;
use Tests\TestCase;

class AccesoPruebasTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private const PW = 'Clave-De-Prueba-77';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        RateLimiter::clear('x');
    }

    private function activar(?string $hasta = null): void
    {
        config(['sgd.ambiente' => 'pruebas', 'sgd.acceso_pruebas_hasta' => $hasta ?? now('America/Santiago')->addDays(7)->format('Y-m-d')]);
    }

    private function cuenta(string $run, string $tipo, string $pw = self::PW, bool $activo = true): CuentaPrueba
    {
        return CuentaPrueba::query()->create(['run' => $run, 'tipo' => $tipo, 'nombres' => 'N', 'apellidos' => 'A', 'password' => Hash::make($pw), 'activo' => $activo]);
    }

    public function test_por_defecto_el_acceso_con_contrasena_no_existe(): void
    {
        $this->assertFalse(Ambiente::accesoPruebasActivo());
        $this->get('/ingreso/pruebas')->assertNotFound();
        $this->post('/ingreso/pruebas', ['run' => '10000001-6', 'password' => 'x'])->assertNotFound();
        $this->get('/ingreso')->assertOk()->assertDontSee('Ingreso de pruebas')->assertDontSee('AMBIENTE DE PRUEBAS');
    }

    public function test_acceso_de_funcionario_y_de_ciudadano(): void
    {
        $this->activar();
        $adm = $this->usuario('admin');
        $this->cuenta($adm->run, 'funcionario');
        $this->cuenta('20000001-3', 'ciudadano');
        $this->get('/ingreso')->assertSee('Ingreso de pruebas');
        $this->get('/ingreso/pruebas')->assertOk()->assertSee('Contraseña');
        $this->post('/ingreso/pruebas', ['run' => $adm->run, 'password' => self::PW])->assertRedirect(route('espacio'));
        $this->get('/app/admin/usuarios')->assertOk();
        $this->assertDatabaseHas('audit_log', ['accion' => 'auth.login']);
        $this->post('/salir');
        $this->flushSession();
        auth()->forgetGuards();
        $this->post('/ingreso/pruebas', ['run' => '20.000.001-3', 'password' => self::PW])->assertRedirect(route('ciudadano.index'));
        $this->get('/ciudadano')->assertOk();
        $this->assertNotNull(CuentaPrueba::query()->where('run', '20000001-3')->first()->ultimo_ingreso);
    }

    public function test_un_run_de_cuenta_de_prueba_no_habilita_a_alguien_no_registrado_como_funcionario(): void
    {
        $this->activar();
        $this->cuenta('15000000-9', 'funcionario'); // sin fila en usuarios
        $this->post('/ingreso/pruebas', ['run' => '15000000-9', 'password' => self::PW])->assertRedirect(route('ingreso'));
        $this->get('/app')->assertRedirect();
    }

    public function test_credenciales_incorrectas_mismo_mensaje_y_se_audita(): void
    {
        $this->activar();
        $adm = $this->usuario('admin');
        $this->cuenta($adm->run, 'funcionario');
        $a = $this->post('/ingreso/pruebas', ['run' => $adm->run, 'password' => 'mala'])->assertRedirect();
        $this->assertSame('RUN o contraseña incorrectos.', session('error'));
        $this->post('/ingreso/pruebas', ['run' => '19999999-9', 'password' => 'mala']);
        $this->assertSame('RUN o contraseña incorrectos.', session('error')); // no revela si la cuenta existe
        $this->assertDatabaseHas('audit_log', ['accion' => 'auth.fallo']);
        $this->get('/app')->assertRedirect(); // sigue sin sesión
        $this->assertStringNotContainsString(self::PW, (string) AuditLog::query()->pluck('detalle')->implode(' '));
    }

    public function test_bloqueo_tras_cinco_intentos_fallidos(): void
    {
        $this->activar();
        $adm = $this->usuario('admin');
        $this->cuenta($adm->run, 'funcionario');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/ingreso/pruebas', ['run' => $adm->run, 'password' => 'mala'.$i]);
        }
        $this->post('/ingreso/pruebas', ['run' => $adm->run, 'password' => self::PW]);
        $this->assertStringContainsString('Demasiados intentos', (string) session('error')); // ni siquiera con la clave correcta
        $this->get('/app')->assertRedirect();
    }

    public function test_cuenta_inactiva_no_ingresa(): void
    {
        $this->activar();
        $adm = $this->usuario('admin');
        $this->cuenta($adm->run, 'funcionario', self::PW, false);
        $this->post('/ingreso/pruebas', ['run' => $adm->run, 'password' => self::PW]);
        $this->assertSame('RUN o contraseña incorrectos.', session('error'));
    }

    public function test_el_acceso_vence_solo_y_con_fecha_invalida_no_se_activa(): void
    {
        $this->activar(now('America/Santiago')->subDay()->format('Y-m-d'));
        $this->assertFalse(Ambiente::accesoPruebasActivo());
        $this->get('/ingreso/pruebas')->assertNotFound();
        foreach (['', 'mañana', '2026-13-45', '31-12-2026'] as $mal) {
            config(['sgd.acceso_pruebas_hasta' => $mal]);
            $this->assertFalse(Ambiente::accesoPruebasActivo(), $mal);
        }
        $this->activar(now('America/Santiago')->format('Y-m-d')); // hoy: vigente hasta el fin del día
        $this->assertTrue(Ambiente::accesoPruebasActivo());
    }

    public function test_banner_de_ambiente_de_pruebas(): void
    {
        $this->activar('2099-01-31');
        $this->get('/')->assertSee('AMBIENTE DE PRUEBAS')->assertSee('31-01-2099');
    }

    public function test_la_guardia_de_produccion_con_ambiente_pruebas(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://gestor.santodomingo.cl', 'sgd.claveunica.mode' => 'mock', 'sgd.firmagob.mode' => 'mock', 'sgd.pisee.mode' => 'mock',
            'sgd.docdigital.mode' => 'mock', 'mail.default' => 'log', 'sgd.llm.enabled' => false]);
        // producción real: los modos simulados siguen prohibidos
        config(['sgd.ambiente' => 'produccion']);
        $this->falla();
        // pruebas sin fecha de vencimiento: prohibido
        config(['sgd.ambiente' => 'pruebas', 'sgd.acceso_pruebas_hasta' => null]);
        $this->falla();
        // pruebas con fecha: se permiten los modos simulados
        config(['sgd.acceso_pruebas_hasta' => now()->addDays(5)->format('Y-m-d')]);
        ProductionGuard::verificar();
        // pero lo demás sigue exigido
        config(['app.debug' => true]);
        $this->falla();
        config(['app.debug' => false, 'app.url' => 'http://inseguro']);
        $this->falla();
        config(['app.url' => 'https://gestor.santodomingo.cl', 'sgd.ambiente' => 'otro']);
        $this->falla();
    }

    private function falla(): void
    {
        try {
            ProductionGuard::verificar();
            $this->fail('Debió fallar cerrado');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_comandos_de_cuentas_de_prueba(): void
    {
        $this->artisan('pruebas:credenciales', ['--rotar' => true])->assertFailed(); // deshabilitado en producción
        $this->activar();
        $adm = $this->usuario('admin');
        $f = tempnam(sys_get_temp_dir(), 'cr');
        file_put_contents($f, json_encode([$adm->run => 'Una-Clave-Larga-1', '20000001-3' => 'Otra-Clave-Larga-2', '10000002-4' => 'corta']));
        $this->artisan('pruebas:credenciales', ['--archivo' => $f, '--borrar' => true])->assertSuccessful();
        $this->assertFileDoesNotExist($f);
        $this->assertSame(2, CuentaPrueba::query()->count()); // la de 5 caracteres se omite
        $this->assertSame('funcionario', CuentaPrueba::query()->where('run', $adm->run)->value('tipo'));
        $this->assertSame('ciudadano', CuentaPrueba::query()->where('run', '20000001-3')->value('tipo'));
        $this->assertTrue(Hash::check('Una-Clave-Larga-1', CuentaPrueba::query()->where('run', $adm->run)->value('password')));
        $this->assertStringNotContainsString('Una-Clave-Larga-1', (string) CuentaPrueba::query()->where('run', $adm->run)->value('password'));
        $antes = CuentaPrueba::query()->where('run', $adm->run)->value('password');
        $this->artisan('pruebas:credenciales', ['--rotar' => true])->assertSuccessful();
        $this->assertNotSame($antes, CuentaPrueba::query()->where('run', $adm->run)->value('password'));
        $this->artisan('pruebas:credenciales', ['--demo' => true])->assertSuccessful();
        $this->assertGreaterThanOrEqual(10, CuentaPrueba::query()->count()); // usuarios demo + 2 vecinos
        $this->artisan('pruebas:credenciales', ['--desactivar' => true])->assertSuccessful();
        $this->assertSame(0, CuentaPrueba::query()->where('activo', true)->count());
    }

    public function test_crear_admin(): void
    {
        $this->artisan('sgd:crear-admin', ['run' => '12345678-0', 'nombres' => 'A', 'apellidos' => 'B', 'correo' => 'a@b.cl'])->assertFailed();
        $this->artisan('sgd:crear-admin', ['run' => '12345678-5', 'nombres' => 'Rosa', 'apellidos' => 'Díaz', 'correo' => 'rosa@santodomingo.cl'])->assertSuccessful();
        $this->assertDatabaseHas('usuarios', ['run' => '12345678-5', 'rol' => 'admin', 'activo' => 1, 'puede_firmar' => 0]);
    }
}
