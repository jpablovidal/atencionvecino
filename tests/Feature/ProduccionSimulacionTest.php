<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\AuditLog;
use App\Models\Expediente;
use App\Models\Solicitud;
use App\Models\Usuario;
use App\Services\Admin\ProduccionService;
use App\Services\Admin\SimulacionService;
use App\Services\Admin\TotpService;
use App\Services\FileVault;
use App\Support\Ambiente;
use App\Support\AuditTriggers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Escenario;
use Tests\TestCase;

class ProduccionSimulacionTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        config(['sgd.ambiente' => 'pruebas', 'sgd.acceso_pruebas_hasta' => now()->addDays(10)->format('Y-m-d')]);
    }

    private function cargar(): array
    {
        return app(SimulacionService::class)->cargar();
    }

    public function test_la_simulacion_crea_procesos_con_los_servicios_reales(): void
    {
        $log = $this->cargar();
        $this->assertSame([], array_values(array_filter($log, fn ($l) => str_starts_with($l, '✖'))), implode("\n", $log));
        $this->assertGreaterThanOrEqual(6, Solicitud::query()->count());
        $this->assertGreaterThanOrEqual(5, Expediente::query()->count());
        $this->assertTrue(Archivo::query()->where('version', 3)->exists(), 'hay un archivo con 3 versiones');
        $this->assertTrue(DB::table('egresos')->exists());
        $this->assertTrue(DB::table('respuestas_finales')->exists());
        $this->assertTrue(DB::table('documento_version_eventos')->where('evento', 'firmado')->exists());
        $this->assertTrue(SimulacionService::yaCargada());
        $this->expectException(FlujoException::class);
        $this->cargar();
    }

    public function test_la_simulacion_solo_existe_en_pruebas(): void
    {
        config(['sgd.ambiente' => 'produccion']);
        $this->expectException(FlujoException::class);
        $this->cargar();
    }

    public function test_el_comando_simular(): void
    {
        $this->artisan('sgd:simular')->assertSuccessful();
        $this->artisan('sgd:simular')->assertFailed();
    }

    public function test_todas_las_tablas_estan_clasificadas_para_el_paso_a_produccion(): void
    {
        $todas = collect(Schema::getTableListing())->map(fn ($t) => preg_replace('/^.*\./', '', $t))->all();
        $clasificadas = array_merge(ProduccionService::BORRAR, ProduccionService::CONSERVAR);
        $this->assertSame([], array_values(array_diff($todas, $clasificadas)), 'Tabla nueva sin clasificar en ProduccionService::BORRAR o CONSERVAR');
        $this->assertSame([], array_values(array_intersect(ProduccionService::BORRAR, ProduccionService::CONSERVAR)));
    }

    /** Administrador real (no demo) con segundo factor, listo para pasar. */
    private function adminListo(): Usuario
    {
        $admin = Usuario::query()->create(['run' => '12345678-5', 'nombres' => 'Real', 'apellidos' => 'Admin', 'correo' => 'admin@santodomingo.cl', 'rol' => 'admin', 'cargo' => 'Administrador', 'puede_firmar' => false, 'activo' => true]);
        $t = app(TotpService::class);
        $s = $t->iniciarInscripcion($admin);
        $t->confirmarInscripcion($admin->refresh(), TotpService::codigo($s, intdiv(time(), 30) - 1));
        config(['sgd.admin_2fa' => true, 'app.debug' => false, 'app.url' => 'https://gestor.santodomingo.cl', 'sgd.claveunica.mode' => 'real', 'sgd.firmagob.mode' => 'prod',
            'sgd.docdigital.mode' => 'outbox', 'sgd.pisee.mode' => 'off', 'mail.default' => 'log', 'sgd.llm.enabled' => false]);

        return $admin->refresh();
    }

    private function codigoNuevo(Usuario $a): string
    {
        $s = Crypt::decryptString($a->refresh()->totp_secreto);

        return TotpService::codigo($s, intdiv(time(), 30));
    }

    public function test_requisitos_bloquean_el_paso_si_falta_algo(): void
    {
        $svc = app(ProduccionService::class);
        $admin = $this->usuario('admin'); // demo, sin 2FA
        $this->assertFalse($svc->puedePasar($admin, false));
        config(['sgd.claveunica.mode' => 'mock']);
        $ok = $this->adminListo();
        config(['sgd.claveunica.mode' => 'mock']);
        $this->assertFalse($svc->puedePasar($ok, true), 'ClaveÚnica simulada impide pasar: dejaría el sitio inaccesible');
        $falla = collect($svc->requisitos($ok, true))->firstWhere('ok', false);
        $this->assertStringContainsString('CLAVEUNICA_MODE=mock', $falla['etiqueta']);
        $this->expectException(FlujoException::class);
        $svc->pasarAProduccion($ok, ProduccionService::FRASE, '000000', true, true);
    }

    public function test_pasar_a_produccion_borra_procesos_conserva_configuracion_y_la_auditoria(): void
    {
        $this->cargar();
        $blobs = Archivo::query()->pluck('ruta')->all();
        $this->assertNotEmpty($blobs);
        $vault = app(FileVault::class);
        $auditAntes = AuditLog::query()->count();
        $tramites = DB::table('tramites')->count();
        $admin = $this->adminListo();
        $usuariosReales = Usuario::query()->count();
        $svc = app(ProduccionService::class);
        $this->assertTrue($svc->puedePasar($admin, true), json_encode($svc->requisitos($admin, true)));

        foreach ([['mala frase', true], [ProduccionService::FRASE, false]] as [$frase, $resp]) {
            try {
                $svc->pasarAProduccion($admin, $frase, $this->codigoNuevo($admin), true, $resp);
                $this->fail('Debió exigir la frase y el respaldo');
            } catch (FlujoException) {
            }
        }
        $this->assertGreaterThan(0, Solicitud::query()->count(), 'nada se borró con confirmaciones inválidas');

        // el código TOTP de la inscripción ya se usó: se espera el siguiente paso de 30 s
        $s = Crypt::decryptString($admin->refresh()->totp_secreto);
        $siguiente = TotpService::codigo($s, intdiv(time(), 30) + 1);
        $this->travel(31)->seconds();
        $res = $svc->pasarAProduccion($admin->refresh(), ProduccionService::FRASE, $siguiente, true, true);

        foreach (['solicitudes', 'expedientes', 'documentos', 'archivos', 'archivo_versiones', 'decretos_pago', 'egresos', 'respuestas_finales', 'documento_version_eventos', 'jobs'] as $t) {
            $this->assertSame(0, DB::table($t)->count(), "$t debe quedar vacía");
        }
        foreach ($blobs as $b) {
            try {
                $vault->get($b, str_repeat('0', 64));
                $this->fail('el archivo cifrado debía borrarse');
            } catch (\Throwable) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame($tramites, DB::table('tramites')->count(), 'los trámites (configuración) se conservan');
        $this->assertSame($usuariosReales, Usuario::query()->count());
        $this->assertTrue((bool) $admin->refresh()->activo, 'el administrador que ejecuta sigue activo');
        $this->assertSame(0, Usuario::query()->where('activo', true)->where('correo', 'like', '%.invalid')->count(), 'los usuarios de demostración quedan inactivos');
        $this->assertGreaterThan($auditAntes, AuditLog::query()->count(), 'la auditoría se conserva y registra el paso');
        $this->assertTrue(AuditLog::query()->where('accion', 'produccion.completado')->exists());
        $this->assertTrue(ProduccionService::enProduccion());
        Ambiente::aplicarOverride();
        $this->assertFalse(Ambiente::esPruebas());
        $this->assertGreaterThan(0, $res['archivos_borrados']);
        $this->assertSame(0, DB::table('correlativos')->count());
        $this->assertTrue(AuditTriggers::existeBloqueoDelete('documento_versiones'), 'el bloqueo de borrado de la evidencia se restaura');
        $this->assertTrue(AuditTriggers::existeBloqueoDelete('audit_log'));
        // una vez en producción no se vuelve a pasar ni a simular
        $this->expectException(FlujoException::class);
        $svc->pasarAProduccion($admin->refresh(), ProduccionService::FRASE, '123456', true, true);
    }

    public function test_pantalla_solo_para_el_administrador(): void
    {
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/admin/produccion')->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/produccion')->assertOk()->assertSee('Requisitos para pasar a producción')->assertSee('Cargar datos simulados');
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/admin/produccion/simular')->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->post('/app/admin/produccion/simular')->assertSessionHas('ok');
        $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/produccion')->assertSee('Ya cargados');
    }
}
