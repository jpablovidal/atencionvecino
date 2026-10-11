<?php

namespace Tests\Feature;

use App\Models\Archivo;
use App\Models\DocumentoVersion;
use App\Models\RepositorioReubicacion;
use App\Models\Solicitud;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use App\Services\Repositorio\DriveBlobStore;
use App\Services\Repositorio\DriveClient;
use App\Services\Repositorio\DriveException;
use App\Services\Repositorio\RepositorioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Escenario;
use Tests\Support\FakeDrive;
use Tests\TestCase;

class RepositorioDriveTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private FakeDrive $drive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->drive = new FakeDrive;
    }

    private function modoDrive(): void
    {
        $this->drive->configurar('drive');
        $this->drive->activar();
    }

    public function test_en_modo_drive_todo_se_guarda_cifrado_en_la_unidad_compartida(): void
    {
        $this->modoDrive();
        $s = $this->solicitudDOM();
        $anexo = Archivo::query()->where('ambito', 'anexo')->first();
        $this->assertStringStartsWith('gd:', $anexo->ruta);
        $this->assertSame('drive', $anexo->almacen);
        $id = substr($anexo->ruta, 3);
        $blob = $this->drive->files[$id]['bytes'];
        $this->assertStringStartsWith('SGD1', $blob);
        $this->assertStringNotContainsString('%PDF', $blob); // Google solo ve texto cifrado
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $this->drive->files[$id]['name']); // nombre aleatorio
        $this->assertSame(now('America/Santiago')->format('Y/m'), $this->drive->ruta($this->drive->carpetaPadre($id)) ?: ''); // carpeta año/mes bajo la raíz
        $this->assertSame($this->drive->ruta($id), now('America/Santiago')->format('Y/m').'/'.$this->drive->files[$id]['name']);
        // lectura (descifra y verifica integridad) por la ruta real del ciudadano
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}/comprobante")->assertOk();
        $this->assertSame($this->pdfValido(), app(FileVault::class)->get($anexo->ruta, $anexo->sha256));
        // las carpetas se crean una sola vez
        $creaciones = count(array_filter($this->drive->log, fn ($l) => $l === 'POST /drive/v3/files'));
        $this->solicitudDOM();
        $this->assertSame($creaciones, count(array_filter($this->drive->log, fn ($l) => $l === 'POST /drive/v3/files')));
    }

    public function test_autenticacion_con_cuenta_de_servicio_sin_delegacion_y_alcance_drive(): void
    {
        $this->modoDrive();
        $this->solicitudDOM();
        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'oauth2.googleapis.com')) {
                return true;
            }
            [, $p] = explode('.', $req['assertion']);
            $c = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
            $this->assertSame('https://www.googleapis.com/auth/drive', $c['scope']);
            $this->assertArrayNotHasKey('sub', $c); // actúa como ella misma (miembro de la unidad compartida), sin suplantar usuarios

            return true;
        });
    }

    public function test_el_pdf_firmado_y_las_versiones_tambien_van_a_drive(): void
    {
        $this->modoDrive();
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $d = $this->documentoVigente($e, $dom);
        $this->assertStringStartsWith('gd:', $d->pdf_ruta);
        $this->assertStringStartsWith('gd:', (string) DocumentoVersion::query()->first()->pdf_ruta);
        $this->get('/validar/'.$d->codigo_validacion)->assertOk();
        $d->forceFill(['nivel_acceso' => 'publico'])->save();
        $r = $this->get('/validar/'.$d->codigo_validacion.'/copia')->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    public function test_alteracion_en_drive_se_detecta(): void
    {
        $this->modoDrive();
        $this->solicitudDOM();
        $a = Archivo::query()->where('ambito', 'anexo')->first();
        $id = substr($a->ruta, 3);
        $b = $this->drive->files[$id]['bytes'];
        $b[strlen($b) - 2] = $b[strlen($b) - 2] ^ "\x01";
        $this->drive->files[$id]['bytes'] = $b;
        $this->expectException(\RuntimeException::class);
        app(FileVault::class)->get($a->ruta, $a->sha256);
    }

    public function test_falla_cerrada_si_drive_no_responde_y_no_queda_nada_a_medias(): void
    {
        $this->modoDrive();
        $this->drive->fallarSubida = 503;
        $antes = Archivo::query()->count();
        try {
            $this->solicitudDOM();
            $this->fail('debe fallar');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(DriveException::class, $e);
        }
        $this->assertSame($antes, Archivo::query()->count());
        $this->assertSame(0, Solicitud::query()->count());
        $this->assertCount(0, $this->drive->archivosSubidos());
    }

    public function test_reintenta_ante_errores_transitorios(): void
    {
        $this->drive->configurar('drive');
        $n = 0;
        $fake = $this->drive;
        Http::fake(function ($r) use (&$n, $fake) {
            if (str_starts_with((string) parse_url($r->url(), PHP_URL_PATH), '/upload/') && ++$n === 1) {
                return Http::response('', 503);
            }
            $m = new \ReflectionMethod($fake, 'responder');

            return $m->invoke($fake, $r);
        });
        $this->assertStringStartsWith('gd:', app(FileVault::class)->put('contenido')['ruta']);
        $this->assertSame(2, $n);
    }

    public function test_md5_distinto_descarta_la_subida(): void
    {
        $this->modoDrive();
        $this->drive->md5Incorrecto = true;
        try {
            app(FileVault::class)->put('contenido');
            $this->fail('debe fallar');
        } catch (DriveException $e) {
            $this->assertStringContainsString('suma de verificación', $e->getMessage());
        }
        $this->assertCount(0, $this->drive->archivosSubidos()); // se eliminó el archivo subido
    }

    public function test_modo_drive_sin_configuracion_no_escribe_en_disco(): void
    {
        config(['sgd.repositorio' => ['modo' => 'drive', 'drive_sa_json' => null, 'drive_shared_drive_id' => null, 'drive_root_folder_id' => null]]);
        app()->forgetInstance(FileVault::class);
        $this->expectException(\RuntimeException::class);
        app(FileVault::class)->put('x');
    }

    public function test_rutas_de_drive_se_validan(): void
    {
        foreach (['gd:../../etc/passwd', 'gd:', 'gd:abc', 'gd:'.str_repeat('a', 200), 'gd:id con espacio 123', 'gd:ab/cd/efghijklmn'] as $mala) {
            $this->assertFalse(DriveBlobStore::esRutaDrive($mala), $mala);
        }
        $this->assertTrue(DriveBlobStore::esRutaDrive('gd:1AbCdEf_GhIjKl-Mn123'));
        $this->assertFalse(DriveClient::idValido('x'));
        $this->modoDrive();
        $this->expectException(\RuntimeException::class);
        app(FileVault::class)->get('gd:../../x', str_repeat('0', 64));
    }

    public function test_prueba_de_conexion_extremo_a_extremo(): void
    {
        $this->modoDrive();
        $pasos = app(RepositorioService::class)->probar();
        $this->assertTrue(collect($pasos)->every(fn ($p) => $p[1]), json_encode($pasos));
        $this->assertCount(6, $pasos);
        $this->assertCount(0, $this->drive->archivosSubidos()); // el archivo de prueba se borra
        $this->assertTrue(app(RepositorioService::class)->ultimaPrueba()['ok']);
        $this->assertDatabaseHas('audit_log', ['accion' => 'repositorio.probar']);
    }

    public function test_prueba_detecta_raiz_de_otra_unidad_o_sin_permiso_de_escritura(): void
    {
        $this->modoDrive();
        $this->drive->driveIdDeLaRaiz = '0AOtraUnidad1234567';
        $p = app(RepositorioService::class)->probar();
        $this->assertFalse(collect($p)->every(fn ($x) => $x[1]));
        $this->assertStringContainsString('no pertenece a la unidad compartida', json_encode($p, JSON_UNESCAPED_UNICODE));
        $this->drive->driveIdDeLaRaiz = FakeDrive::DRIVE_ID;
        $this->drive->canAddChildren = false;
        $this->assertStringContainsString('Gestor de contenido', json_encode(app(RepositorioService::class)->probar(), JSON_UNESCAPED_UNICODE));
        config(['sgd.repositorio.drive_sa_json' => '/no/existe.json']);
        $this->assertFalse(app(RepositorioService::class)->probar()[0][1]);
        $this->assertFalse(app(RepositorioService::class)->ultimaPrueba()['ok']);
    }

    public function test_migracion_de_archivos_locales_a_drive(): void
    {
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom); // modo local
        $d = $this->documentoVigente($e, $dom);
        $rutaVersion = DocumentoVersion::query()->first()->pdf_ruta;
        $this->assertFalse(str_starts_with($rutaVersion, 'gd:'));
        $anexo = Archivo::query()->where('ambito', 'anexo')->first();
        $plano = app(FileVault::class)->get($anexo->ruta, $anexo->sha256);

        $this->modoDrive();
        $svc = app(RepositorioService::class);
        $antes = $svc->estadisticas();
        $this->assertGreaterThan(0, $antes['pendientes']);
        $this->assertSame(0, $antes['reubicadas']);
        $rutaLocal = config('sgd.storage_path').'/'.$anexo->ruta;
        $this->assertFileExists($rutaLocal);

        do {
            $r = $svc->migrarLote(3, 30);
            $this->assertSame(0, $r['errores']);
        } while ($r['pendientes'] > 0 && $r['migradas'] > 0);

        $this->assertSame(0, $svc->estadisticas()['pendientes']);
        $this->assertFileDoesNotExist($rutaLocal); // la copia local se borra tras registrar la reubicación
        $this->assertSame('drive', $anexo->refresh()->almacen);
        $this->assertSame($anexo->ruta, Archivo::query()->find($anexo->id)->ruta); // la referencia no cambia
        $this->assertSame($rutaVersion, DocumentoVersion::query()->first()->pdf_ruta); // las versiones inmutables no se tocan
        // se sigue leyendo (ahora desde Drive) por las mismas rutas originales
        app()->forgetInstance(FileVault::class);
        $this->assertSame($plano, app(FileVault::class)->get($anexo->ruta, $anexo->sha256));
        $this->assertSame('drive', app(FileVault::class)->almacenDe($rutaVersion));
        $this->assertNotEmpty(app(FileVault::class)->get($d->pdf_ruta, $d->pdf_sha256));
        $this->assertGreaterThan(0, RepositorioReubicacion::query()->count());
        // idempotente
        $this->assertSame(['migradas' => 0, 'errores' => 0, 'pendientes' => 0], $svc->migrarLote(10, 10));
        // la verificación de integridad por muestra pasa
        $v = $svc->verificarMuestra(10);
        $this->assertSame($v['revisados'], $v['ok']);
    }

    public function test_si_falla_la_subida_durante_la_migracion_se_conserva_el_archivo_local(): void
    {
        $this->solicitudDOM();
        $anexo = Archivo::query()->where('ambito', 'anexo')->first();
        $this->modoDrive();
        $this->drive->fallarSubida = 500;
        $r = app(RepositorioService::class)->migrarLote(10, 10);
        $this->assertSame(0, $r['migradas']);
        $this->assertGreaterThan(0, $r['errores']);
        $this->assertFileExists(config('sgd.storage_path').'/'.$anexo->ruta);
        $this->assertSame(0, RepositorioReubicacion::query()->count());
        $this->assertSame('local', $anexo->refresh()->almacen);
    }

    public function test_la_verificacion_detecta_archivos_alterados(): void
    {
        $this->modoDrive();
        $this->solicitudDOM();
        foreach ($this->drive->archivosSubidos() as $id => $f) {
            $this->drive->files[$id]['bytes'] = 'basura';
        }
        $v = app(RepositorioService::class)->verificarMuestra(5);
        $this->assertGreaterThan(0, count($v['fallos']));
        $this->assertSame(0, $v['ok']);
    }

    public function test_volver_a_modo_local_sigue_leyendo_lo_que_ya_esta_en_drive(): void
    {
        $this->modoDrive();
        $this->solicitudDOM();
        $a = Archivo::query()->where('ambito', 'anexo')->first();
        config(['sgd.repositorio.modo' => 'local']);
        app()->forgetInstance(FileVault::class);
        $this->assertSame($this->pdfValido(), app(FileVault::class)->get($a->ruta, $a->sha256));
        $nuevo = app(FileVault::class)->put('x'); // y lo nuevo vuelve a disco
        $this->assertFalse(str_starts_with($nuevo['ruta'], 'gd:'));
    }

    public function test_comandos_de_consola(): void
    {
        $this->modoDrive();
        $this->artisan('repositorio:operar probar')->assertSuccessful();
        $this->artisan('repositorio:operar estado')->assertSuccessful();
        $this->artisan('repositorio:operar migrar')->assertSuccessful();
        $this->artisan('repositorio:operar verificar --n=3')->assertSuccessful();
    }
}
