<?php

namespace Tests\Feature;

use App\Models\ApiConfig;
use App\Services\Repositorio\CredencialDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Carga de la clave JSON de Drive desde el panel y URL de Drive pegada como ID. */
class RepositorioCredencialTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->dir = sys_get_temp_dir().'/sgd-sec-'.bin2hex(random_bytes(4));
        config(['sgd.secrets_path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function claveJson(array $extra = []): UploadedFile
    {
        $k = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($k, $pem);
        $c = json_encode(array_merge(['type' => 'service_account', 'project_id' => 'proyecto-sgd', 'private_key' => $pem, 'client_email' => 'sgd-drive@proyecto-sgd.iam.gserviceaccount.com', 'client_id' => '1'], $extra));

        return UploadedFile::fake()->createWithContent('clave.json', $c);
    }

    /** @return array<string,array{0:string,1:string}> pantalla (URL) y nombre del campo de archivo */
    public static function pantallas(): array
    {
        return ['pantalla Repositorio' => ['/app/admin/repositorio', 'drive_sa_archivo'], 'editor genérico de APIs' => ['/app/admin/api/repositorio', 'archivo_drive_sa_json']];
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['modo' => 'local', 'activo' => '1', 'drive_shared_drive_id' => '', 'drive_root_folder_id' => ''], $extra);
    }

    #[DataProvider('pantallas')]
    public function test_se_carga_valida_y_guarda_la_clave_con_permisos_600_sin_mostrarla(string $url, string $campo): void
    {
        $a = $this->usuario('admin');
        $this->comoFuncionario($a)->post($url, $this->datos([$campo => $this->claveJson()]))->assertSessionHas('ok');
        $ruta = $this->dir.'/drive-sa.json';
        $this->assertFileExists($ruta);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($ruta)), -4));
        $r = $this->comoFuncionario($a)->get($url)->assertOk();
        $r->assertSee('sgd-drive@proyecto-sgd.iam.gserviceaccount.com')->assertSee('proyecto-sgd')->assertSee($campo, false);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $r->getContent());
        $this->assertStringContainsString($this->dir.'/drive-sa.json', $r->getContent()); // la ruta queda completada
        $this->assertDatabaseHas('audit_log', ['accion' => $url === '/app/admin/repositorio' ? 'repositorio.credencial_drive' : 'api.credencial_subida']);
    }

    #[DataProvider('pantallas')]
    public function test_rechaza_archivos_que_no_son_una_cuenta_de_servicio(string $url, string $campo): void
    {
        $a = $this->usuario('admin');
        foreach ([
            UploadedFile::fake()->createWithContent('x.json', str_repeat('no es json ', 30)),
            UploadedFile::fake()->createWithContent('x.json', json_encode(['type' => 'authorized_user', 'client_email' => 'a@b.c', 'private_key' => 'x', 'pad' => str_repeat('x', 200)])),
            $this->claveJson(['client_email' => 'persona@gmail.com']),
            $this->claveJson(['private_key' => "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----\n"]),
            UploadedFile::fake()->createWithContent('x.json', str_repeat('a', 30000)),
        ] as $f) {
            $this->comoFuncionario($a)->post($url, $this->datos([$campo => $f]));
            $this->assertFileDoesNotExist($this->dir.'/drive-sa.json');
        }
    }

    #[DataProvider('pantallas')]
    public function test_solo_quien_gestiona_puede_subirla(string $url, string $campo): void
    {
        $this->comoFuncionario($this->usuario('funcionario'))->post($url, $this->datos([$campo => $this->claveJson()]))->assertForbidden();
        $this->assertFileDoesNotExist($this->dir.'/drive-sa.json');
    }

    public function test_la_cuenta_del_correo_se_guarda_con_su_propio_nombre(): void
    {
        $this->comoFuncionario($this->usuario('admin'))->post('/app/admin/api/google_mail', ['activo' => '1', 'archivo_service_account_json' => $this->claveJson()])->assertSessionHas('ok');
        $this->assertFileExists($this->dir.'/gmail-sa.json');
        $this->assertFileDoesNotExist($this->dir.'/drive-sa.json');
    }

    #[DataProvider('pantallas')]
    public function test_acepta_la_url_de_drive_y_extrae_el_id(string $url, string $campo): void
    {
        $id = '0ALsOFxNiTuf_Uk9PVA';
        $this->assertSame($id, CredencialDriveService::extraerId("https://drive.google.com/drive/u/0/folders/$id"));
        $this->assertSame($id, CredencialDriveService::extraerId("https://drive.google.com/open?id=$id"));
        $this->assertSame($id, CredencialDriveService::extraerId(" $id "));
        $this->assertSame('https://evil.example/folders/'.$id, CredencialDriveService::extraerId('https://evil.example/folders/'.$id));
        $this->comoFuncionario($this->usuario('admin'))->post($url, $this->datos(['drive_shared_drive_id' => "https://drive.google.com/drive/u/0/folders/$id", 'drive_root_folder_id' => "https://drive.google.com/drive/folders/$id?usp=sharing"]))->assertSessionHas('ok');
        $v = json_decode((string) ApiConfig::query()->where('servicio', 'repositorio')->first()->valores, true) ?: [];
        $this->assertSame($id, $v['drive_shared_drive_id'] ?? null);
        $this->assertSame($id, $v['drive_root_folder_id'] ?? null);
    }
}
