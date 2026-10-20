<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Area;
use App\Models\Institucion;
use App\Models\Unidad;
use App\Services\Admin\ArchivoConfigService;
use App\Services\Admin\AreaService;
use App\Services\Admin\CodificacionService;
use App\Services\Admin\TotpService;
use App\Services\ExpedienteService;
use App\Support\UploadValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

class AdminConfiguracionTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        ArchivoConfigService::olvidar();
    }

    protected function tearDown(): void
    {
        ArchivoConfigService::olvidar();
        parent::tearDown();
    }

    private function zip(array $partes): string
    {
        $p = tempnam(sys_get_temp_dir(), 'z');
        $z = new \ZipArchive;
        $z->open($p, \ZipArchive::OVERWRITE);
        foreach ($partes as $n => $c) {
            $z->addFromString($n, $c);
        }
        $z->close();
        $b = (string) file_get_contents($p);
        @unlink($p);

        return $b;
    }

    // ---- Archivos: pesos y formatos

    public function test_formatos_y_pesos_son_configurables_y_se_auditan(): void
    {
        $admin = $this->usuario('admin');
        try {
            UploadValidator::validarBytes("a;b\n1;2");
            $this->fail('CSV viene deshabilitado por defecto');
        } catch (\InvalidArgumentException) {
        }
        app(ArchivoConfigService::class)->guardar($admin, ['formatos' => ['jpg' => 1, 'png' => 1, 'csv' => 1], 'max_mb' => ['ciudadano' => 5, 'funcionario' => 20], 'max_archivos' => 4]);
        $this->assertSame('text/csv', UploadValidator::validarBytes("a;b\n1;2")['mime']);
        $this->assertSame(20 * 1048576, ArchivoConfigService::maxBytes('funcionario'));
        $this->assertSame(5 * 1048576, ArchivoConfigService::maxBytes('ciudadano'));
        $this->assertSame(4, ArchivoConfigService::maxArchivos());
        $this->assertDatabaseHas('audit_log', ['accion' => 'archivos.config']);
    }

    public function test_formato_deshabilitado_se_rechaza_y_pdf_no_se_puede_deshabilitar(): void
    {
        $svc = app(ArchivoConfigService::class);
        $svc->guardar($this->usuario('admin'), ['formatos' => ['png' => 1], 'max_mb' => ['ciudadano' => 10, 'funcionario' => 10], 'max_archivos' => 8]);
        $this->assertSame('application/pdf', UploadValidator::validarBytes($this->pdfValido())['mime']);
        $this->assertSame('image/png', UploadValidator::validarBytes("\x89PNG\r\n\x1A\n".str_repeat("\0", 20))['mime']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no está habilitado');
        UploadValidator::validarBytes("\xFF\xD8\xFF\xE0".str_repeat("\0", 20));
    }

    public function test_documentos_ofimaticos_validan_estructura_y_rechazan_macros(): void
    {
        app(ArchivoConfigService::class)->guardar($this->usuario('admin'), ['formatos' => ['docx' => 1, 'odt' => 1], 'max_mb' => ['ciudadano' => 10, 'funcionario' => 10], 'max_archivos' => 8]);
        $docx = $this->zip(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>']);
        $this->assertSame('docx', UploadValidator::validarBytes($docx)['ext']);
        $odt = $this->zip(['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<x/>']);
        $this->assertSame('odt', UploadValidator::validarBytes($odt)['ext']);
        $macro = $this->zip(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>', 'word/vbaProject.bin' => 'x']);
        try {
            UploadValidator::validarBytes($macro);
            $this->fail('Debió rechazar macros');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('macros', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        UploadValidator::validarBytes($this->zip(['x.txt' => 'zip cualquiera']));
    }

    public function test_pesos_fuera_de_rango_se_rechazan(): void
    {
        $this->expectException(FlujoException::class);
        app(ArchivoConfigService::class)->guardar($this->usuario('admin'), ['max_mb' => ['ciudadano' => 0, 'funcionario' => 10], 'max_archivos' => 8]);
    }

    public function test_pantalla_de_archivos_solo_para_quien_tiene_el_permiso(): void
    {
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/admin/archivos')->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/archivos')->assertOk()->assertSee('Peso máximo por archivo');
        $this->comoFuncionario($this->usuario('admin'))->post('/app/admin/archivos', ['formatos' => ['txt' => 1], 'max_mb' => ['ciudadano' => 12, 'funcionario' => 15], 'max_archivos' => 6])->assertRedirect();
        $this->assertSame(12 * 1048576, ArchivoConfigService::maxBytes('ciudadano'));
    }

    // ---- Segundo factor del administrador

    public function test_totp_cumple_el_vector_de_rfc_6238(): void
    {
        $this->assertSame('287082', TotpService::codigo('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv(59, 30)));
        $this->assertSame('081804', TotpService::codigo('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv(1111111109, 30)));
        $this->assertNull(TotpService::pasoValido('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '000000', 59));
    }

    public function test_admin_debe_inscribir_y_verificar_el_segundo_factor(): void
    {
        config(['sgd.admin_2fa' => true]);
        $admin = $this->usuario('admin');
        $c = $this->comoFuncionario($admin);
        $c->get('/app')->assertRedirect('/app/2fa');
        $c->get('/app/admin/archivos')->assertRedirect('/app/2fa');
        $c->get('/app/2fa')->assertOk()->assertSee('Escanee');
        $secreto = app(TotpService::class)->secretoPendiente($admin->refresh());
        $this->assertNotNull($secreto);
        $paso = intdiv(time(), 30);
        $c->post('/app/2fa', ['codigo' => '123456'])->assertSessionHas('error');
        $r = $c->post('/app/2fa', ['codigo' => TotpService::codigo($secreto, $paso)]);
        $r->assertOk()->assertSee('códigos de respaldo');
        $c->get('/app')->assertOk();
        $this->assertSame($admin->id, session('totp_ok'));
        $this->assertNotNull($admin->refresh()->totp_confirmado_en);
        $this->assertStringNotContainsString($secreto, (string) $admin->totp_secreto, 'El secreto debe guardarse cifrado');
    }

    public function test_el_codigo_totp_no_se_puede_reutilizar_y_los_de_respaldo_sirven_una_vez(): void
    {
        $admin = $this->usuario('admin');
        $svc = app(TotpService::class);
        $s = $svc->iniciarInscripcion($admin);
        $paso = intdiv(time(), 30);
        $resp = $svc->confirmarInscripcion($admin->refresh(), TotpService::codigo($s, $paso));
        $this->assertCount(8, $resp);
        $this->assertFalse($svc->verificar($admin->refresh(), TotpService::codigo($s, $paso)), 'El mismo código no puede usarse dos veces');
        $this->assertTrue($svc->verificar($admin->refresh(), TotpService::codigo($s, $paso + 1)));
        $this->assertTrue($svc->verificar($admin->refresh(), $resp[0]));
        $this->assertFalse($svc->verificar($admin->refresh(), $resp[0]), 'Un código de respaldo sirve una sola vez');
    }

    public function test_funcionarios_no_admin_no_necesitan_segundo_factor(): void
    {
        config(['sgd.admin_2fa' => true]);
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app')->assertOk();
    }

    // ---- Áreas

    public function test_usuario_puede_pertenecer_a_varias_areas_y_solo_habilitadas(): void
    {
        $svc = app(AreaService::class);
        $admin = $this->usuario('admin');
        $svc->guardar($admin, ['salud' => ['habilitada' => 1], 'educacion' => ['habilitada' => 1]]);
        $u = $this->usuario('funcionario');
        $ids = Area::query()->whereIn('codigo', ['municipal', 'salud'])->pluck('id')->all();
        $svc->asignarUsuario($u, $ids);
        $this->assertCount(2, $u->areas()->get());
        $corp = Area::query()->where('codigo', 'corporacion')->value('id');
        $this->expectException(FlujoException::class);
        $svc->asignarUsuario($u, [$corp]);
    }

    public function test_no_se_deshabilita_un_area_en_uso_y_la_municipal_siempre_esta(): void
    {
        $svc = app(AreaService::class);
        $admin = $this->usuario('admin');
        $svc->guardar($admin, ['salud' => ['habilitada' => 1]]);
        $un = Unidad::query()->first();
        $svc->asignarUnidad($un, Area::query()->where('codigo', 'salud')->value('id'));
        try {
            $svc->guardar($admin, ['salud' => []]);
            $this->fail('Debió impedir deshabilitar un área en uso');
        } catch (FlujoException $e) {
            $this->assertStringContainsString('asignados', $e->getMessage());
        }
        $svc->guardar($admin, ['salud' => ['habilitada' => 1], 'municipal' => []]);
        $this->assertTrue((bool) Area::query()->where('codigo', 'municipal')->value('habilitada'));
    }

    // ---- Codificación

    public function test_municipio_desde_catalogo_y_codigo_por_area(): void
    {
        $admin = $this->usuario('admin');
        $svc = app(CodificacionService::class);
        Institucion::query()->create(['codigo' => 'MSD01', 'nombre' => 'I. Municipalidad de Santo Domingo', 'activo' => true, 'origen' => 'csv']);
        try {
            $svc->elegirMunicipio($admin, 'NOEXISTE');
            $this->fail('Debe exigir el catálogo');
        } catch (FlujoException) {
        }
        $svc->elegirMunicipio($admin, 'MSD01');
        $this->assertSame('MSD01', CodificacionService::institucion());
        app(AreaService::class)->guardar($admin, ['salud' => ['habilitada' => 1, 'institucion_codigo' => 'SALUD-SD']]);
        $this->assertSame('SALUD-SD', CodificacionService::institucion(Area::query()->where('codigo', 'salud')->value('id')));
    }

    public function test_patrones_validan_marcadores_y_unicidad(): void
    {
        $admin = $this->usuario('admin');
        $svc = app(CodificacionService::class);
        foreach ([['expediente' => '{institucion}', 'documento' => '{tipo}-{anio}-{numero}'], ['expediente' => '{codigo}', 'documento' => '{tipo}-{numero}'],
            ['expediente' => '{codigo}-{xx}', 'documento' => '{tipo}-{anio}-{numero}'], ['expediente' => '<b>{codigo}', 'documento' => '{tipo}-{anio}-{numero}']] as $malo) {
            try {
                $svc->guardarPatrones($admin, $malo);
                $this->fail('Debió rechazar '.json_encode($malo));
            } catch (FlujoException) {
            }
        }
        $svc->guardarPatrones($admin, ['expediente' => '{institucion}/{codigo}', 'documento' => '{institucion}-{tipo}-{anio}-{numero}-{area}']);
        $this->assertSame('{institucion}/{codigo}', CodificacionService::patrones()['expediente']);
    }

    public function test_documento_vigente_recibe_identificador_normativo(): void
    {
        $this->assertNotEmpty(CodificacionService::matrizNormas());
        $this->assertCount(5, CodificacionService::NORMAS);
        $doc = $this->documentoVigente(app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom = $this->usuario('funcionario')), $dom);
        $this->assertMatchesRegularExpression('/^PE-MUN-00243-[A-Za-z]+-\d{4}-\d{6}$/', (string) $doc->refresh()->identificador);
    }
}
