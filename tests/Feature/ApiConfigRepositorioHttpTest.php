<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Jobs\MigrarRepositorioADrive;
use App\Models\ApiConfig;
use App\Services\Admin\ApiConfigService;
use App\Services\FileVault;
use App\Services\Repositorio\RepositorioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Escenario;
use Tests\Support\FakeDrive;
use Tests\TestCase;

class ApiConfigRepositorioHttpTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Cache::flush();
    }

    private function admin()
    {
        return $this->comoFuncionario($this->usuario('admin'));
    }

    public function test_permisos_del_mantenedor_de_apis_y_repositorio(): void
    {
        foreach (['/app/admin/api', '/app/admin/api/docdigital', '/app/admin/repositorio'] as $u) {
            $this->comoFuncionario($this->usuario('funcionario'))->get($u)->assertForbidden();
            $this->comoFuncionario($this->usuario('auditor'))->get($u)->assertForbidden();
            $this->comoFuncionario($this->usuario('alcalde'))->get($u)->assertForbidden();
            $this->admin()->get($u)->assertOk();
        }
        $this->admin()->get('/app/admin/api/inexistente')->assertNotFound();
    }

    public function test_la_configuracion_de_bd_prevalece_sobre_env_y_se_puede_volver_a_env(): void
    {
        config(['sgd.docdigital.mode' => 'outbox', 'sgd.docdigital.unidad_recepcion' => 'SECMUN']);
        $this->admin()->post('/app/admin/api/docdigital', ['activo' => 1, 'mode' => 'prod', 'url_prod' => 'https://docdigital.ejemplo.gob.cl', 'unidad_recepcion' => 'ALC', 'entidad_id' => '5001'])->assertSessionHas('ok');
        ApiConfigService::aplicar();
        $this->assertSame(['prod', 'ALC', 'https://docdigital.ejemplo.gob.cl'], [config('sgd.docdigital.mode'), config('sgd.docdigital.unidad_recepcion'), config('sgd.docdigital.url_prod')]);
        $this->assertDatabaseHas('audit_log', ['accion' => 'api.configurar']);
        // inactiva: no se aplica
        config(['sgd.docdigital.mode' => 'outbox']);
        $this->admin()->post('/app/admin/api/docdigital/env')->assertSessionHas('ok');
        ApiConfigService::aplicar();
        $this->assertSame('outbox', config('sgd.docdigital.mode'));
        $this->assertFalse(ApiConfig::query()->where('servicio', 'docdigital')->first()->activo);
    }

    public function test_validaciones_de_campos(): void
    {
        $casos = [
            ['docdigital', ['mode' => 'inventado']], ['docdigital', ['url_prod' => 'http://sin-tls.cl']], ['docdigital', ['url_prod' => 'https://user:pass@x.cl']],
            ['docdigital', ['url_prod' => 'https://127.0.0.1/x']], ['docdigital', ['url_prod' => 'https://10.0.0.5/x']], ['docdigital', ['url_prod' => 'https://localhost/x']],
            ['google_mail', ['service_account_json' => 'relativa/sa.json']], ['google_mail', ['service_account_json' => '/home/x/../etc/passwd']], ['google_mail', ['service_account_json' => public_path('sa.json')]],
            ['google_mail', ['rate_per_minute' => 'diez']], ['docdigital', ['entidad_id' => "5\r\nHost: evil"]],
        ];
        foreach ($casos as [$s, $d]) {
            $this->admin()->post("/app/admin/api/$s", ['activo' => 1] + $d)->assertSessionHas('error');
        }
        // el nodo PISEE sí puede estar en una IP privada de la red municipal
        $this->admin()->post('/app/admin/api/pisee', ['activo' => 1, 'mode' => 'api', 'base_url' => 'https://10.20.30.40:8443'])->assertSessionHas('ok');
        $this->admin()->post('/app/admin/api/google_mail', ['activo' => 1, 'service_account_json' => '/home/usuario/sgd_secrets/gmail-sa.json', 'rate_per_minute' => '15', 'sender' => 'no-responder@santodomingo.cl'])->assertSessionHas('ok');
    }

    public function test_modos_simulados_prohibidos_en_produccion(): void
    {
        $this->app['env'] = 'production';
        $svc = app(ApiConfigService::class);
        $u = $this->usuario('admin');
        foreach ([['claveunica', ['mode' => 'mock']], ['firmagob', ['mode' => 'mock']], ['docdigital', ['mode' => 'demo']], ['pisee', ['mode' => 'mock']]] as [$s, $d]) {
            try {
                $svc->guardar($s, $d, true, [], $u);
                $this->fail("debió prohibir $s ".json_encode($d));
            } catch (FlujoException $e) {
                $this->assertStringContainsString('producción', $e->getMessage());
            }
        }
        $svc->guardar('firmagob', ['mode' => 'test'], true, [], $u);
        $this->addToAssertionCount(1);
    }

    public function test_los_secretos_solo_en_bd_si_se_habilita_nunca_se_muestran_y_van_cifrados(): void
    {
        // el almacenamiento de secretos en BD está encendido por defecto; con SGD_API_SECRETOS_EN_BD=0 se rechaza
        $this->assertTrue(ApiConfigService::secretosEnBd());
        config(['sgd.api_secretos_en_bd' => false]);
        $this->admin()->post('/app/admin/api/firmagob', ['activo' => 1, 'mode' => 'test', 'secret' => 'super-secreto-hs256'])->assertSessionHas('error');
        $this->assertSame(0, ApiConfig::query()->count());

        config(['sgd.api_secretos_en_bd' => true]);
        $this->admin()->post('/app/admin/api/firmagob', ['activo' => 1, 'mode' => 'test', 'secret' => 'super-secreto-hs256', 'api_token_key' => 'tok-abc-123'])->assertSessionHas('ok');
        $c = ApiConfig::query()->where('servicio', 'firmagob')->first();
        $this->assertNotNull($c->secretos);
        $this->assertStringNotContainsString('super-secreto', (string) $c->secretos); // cifrado con APP_KEY
        $this->assertStringNotContainsString('super-secreto', (string) $c->valores);
        config(['sgd.firmagob.secret' => null]);
        ApiConfigService::aplicar();
        $this->assertSame('super-secreto-hs256', config('sgd.firmagob.secret'));

        $html = $this->admin()->get('/app/admin/api/firmagob')->getContent();
        $this->assertStringNotContainsString('super-secreto', $html);
        $this->assertStringNotContainsString('tok-abc-123', $html);
        $this->assertStringContainsString('guardado, cifrado', $html);
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringNotContainsString('super-secreto', json_encode(DB::table('audit_log')->get()));

        // vacío = no cambia; borrar = elimina
        $this->admin()->post('/app/admin/api/firmagob', ['activo' => 1, 'mode' => 'test', 'secret' => ''])->assertSessionHas('ok');
        $this->assertSame(['secret', 'api_token_key'], array_values(array_intersect(['secret', 'api_token_key'], $this->claves('firmagob'))));
        $this->admin()->post('/app/admin/api/firmagob', ['activo' => 1, 'mode' => 'test', 'borrar' => ['secret']])->assertSessionHas('ok');
        $this->assertSame(['api_token_key'], $this->claves('firmagob'));
    }

    private function claves(string $s): array
    {
        return $this->admin()->get('/app/admin/api/'.$s) && true ? app(ApiConfigService::class)->estado($s)['secretos_bd'] : [];
    }

    public function test_el_resultado_de_validar_no_filtra_secretos(): void
    {
        config(['sgd.firmagob.secret' => 'valor-secreto-env']);
        $r = $this->admin()->post('/app/admin/api/firmagob/probar')->assertRedirect();
        $html = $this->admin()->withSession(['pruebas' => app(ApiConfigService::class)->probar('firmagob')])->get('/app/admin/api/firmagob')->getContent();
        $this->assertStringNotContainsString('valor-secreto-env', $html);
        $this->assertStringContainsString('definido', $html);
    }

    public function test_el_json_de_la_cuenta_de_servicio_se_valida_y_se_exigen_permisos_restrictivos(): void
    {
        $f = new FakeDrive;
        chmod($f->saPath, 0644);
        config(['sgd.google.service_account_json' => $f->saPath, 'sgd.google.sender' => 'x@santodomingo.cl']);
        $r = app(ApiConfigService::class)->probar('google_mail');
        $this->assertStringContainsString('permisos', json_encode($r, JSON_UNESCAPED_UNICODE));
        chmod($f->saPath, 0600);
        $r = collect(app(ApiConfigService::class)->probar('google_mail'));
        $this->assertTrue($r->last()[1]); // estructura de cuenta de servicio válida
    }

    // ------------------------------------------------------------------ Repositorio (UI)
    public function test_repositorio_guardar_probar_migrar_y_verificar(): void
    {
        $drive = new FakeDrive;
        $this->admin()->post('/app/admin/repositorio', ['modo' => 'drive', 'drive_sa_json' => $drive->saPath, 'drive_shared_drive_id' => 'corto', 'drive_root_folder_id' => FakeDrive::ROOT_ID])->assertSessionHas('error');
        $this->admin()->post('/app/admin/repositorio', ['modo' => 'drive'])->assertSessionHas('error'); // sin credenciales
        $this->admin()->post('/app/admin/repositorio', ['modo' => 'drive', 'drive_sa_json' => $drive->saPath, 'drive_shared_drive_id' => FakeDrive::DRIVE_ID, 'drive_root_folder_id' => FakeDrive::ROOT_ID])
            ->assertStatus(302)->assertSessionMissing('error');
        // la ruta no puede estar en public/ (el JSON no se guarda con la configuración inválida)
        $this->admin()->post('/app/admin/repositorio', ['modo' => 'local', 'drive_sa_json' => public_path('sa.json')])->assertSessionHas('error');

        ApiConfigService::aplicar();
        $this->assertSame('drive', config('sgd.repositorio.modo'));
        app()->forgetInstance(FileVault::class);
        app()->forgetInstance(RepositorioService::class);
        $drive->activar();

        Queue::fake();
        $this->admin()->post('/app/admin/repositorio/migrar')->assertSessionHas('error'); // sin prueba exitosa previa
        $this->admin()->post('/app/admin/repositorio/probar')->assertSessionHas('pasos');
        $this->admin()->post('/app/admin/repositorio/migrar')->assertSessionHas('ok');
        Queue::assertPushed(MigrarRepositorioADrive::class);

        $this->solicitudDOM();
        $this->admin()->post('/app/admin/repositorio/verificar')->assertSessionHas('verif');
        $html = $this->admin()->get('/app/admin/repositorio')->getContent();
        foreach (['Repositorio de documentos', 'Google Drive', 'Gestor de contenido', 'exitosa'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
        $this->admin()->withSession(['pasos' => app(RepositorioService::class)->probar()])->get('/app/admin/repositorio')->assertSee('Resultado de la prueba');
    }

    public function test_migrar_exige_modo_drive(): void
    {
        $this->admin()->post('/app/admin/repositorio/migrar')->assertSessionHas('error');
    }
}
