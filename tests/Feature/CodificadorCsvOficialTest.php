<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Institucion;
use App\Services\Admin\CodificacionService;
use App\Services\ExpedienteService;
use App\Services\Interop\CodificadorApi;
use App\Services\Interop\CodificadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Codificador: «Listado de códigos de Instituciones» oficial (CSV separado por «;»), vigencia, datos territoriales y rutas de la API. */
class CodificadorCsvOficialTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Carbon::setTestNow('2026-10-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function muestra(): string
    {
        return (string) file_get_contents(base_path('tests/fixtures/codificador_instituciones_muestra.csv'));
    }

    public function test_importa_el_listado_oficial_con_padre_oid_tipo_y_territorio(): void
    {
        $r = app(CodificadorService::class)->importarCsv('instituciones', $this->muestra());
        $this->assertSame([], $r['errores']);
        $this->assertGreaterThanOrEqual(20, $r['creados']);
        $m = Institucion::query()->where('codigo', 'PE-MUN-00243')->firstOrFail();
        $this->assertSame('Municipalidad de Santo Domingo', $m->nombre);
        $this->assertSame(243, (int) $m->codificador_id);
        $this->assertSame('1.PE-MUN-00243', $m->oid);
        $this->assertSame('MUN', $m->tipo);
        $this->assertSame('PE-SUB-00573', $m->codigo_padre);
        $this->assertTrue((bool) $m->activo);
        $x = json_decode((string) $m->info_extra, true);
        $this->assertSame('05', $x['region']);
        $this->assertSame('056', $x['provincia']);
        $this->assertSame('05606', $x['comuna']); // el listado trae 5606: se completa a 5 dígitos
        $this->assertSame('B', $x['grupo']);
        $this->assertSame(3, $x['nivel']);
        $this->assertSame('PE', $x['poder']);
        $this->assertSame('OA', $x['dr']);
        $this->assertTrue($x['afecta_ley_21180']);
        $this->assertSame('PE-MIN-00526', Institucion::query()->where('codigo', 'PE-SUB-00573')->value('codigo_padre'));
        $this->assertNull(Institucion::query()->where('codigo', 'PE-PRE-00000')->value('codigo_padre')); // padre 0 = sin padre
    }

    public function test_lo_que_ya_no_esta_vigente_queda_inactivo(): void
    {
        app(CodificadorService::class)->importarCsv('instituciones', $this->muestra());
        $this->assertFalse((bool) Institucion::query()->where('codigo', 'PE-HOS-01531')->value('activo')); // vigente=false, fin 2024
        $this->assertFalse((bool) Institucion::query()->where('codigo', 'PE-FUN-00697')->value('activo')); // fin 07-10-2026 (ya pasó)
        $this->assertTrue((bool) Institucion::query()->where('codigo', 'PE-UNI-00001')->value('activo'));
        $this->assertNull(app(CodificadorService::class)->institucionActiva('PE-HOS-01531'));
        $this->assertNotNull(app(CodificadorService::class)->institucionActiva('PE-MUN-00243'));
    }

    public function test_acepta_codigos_con_formatos_distintos_al_habitual(): void
    {
        app(CodificadorService::class)->importarCsv('instituciones', $this->muestra());
        $this->assertSame('SER', Institucion::query()->where('codigo', 'PE-SER-1533')->value('tipo'));
        $this->assertNotNull(Institucion::query()->where('codigo', 'PE-FFAA-00171')->first());
    }

    public function test_reimportar_actualiza_sin_duplicar_y_conserva_los_nombres_de_ubicacion(): void
    {
        app(CodificadorService::class)->asegurarMunicipioPorDefecto();
        $this->assertSame('Valparaíso', json_decode((string) Institucion::query()->where('codigo', 'PE-MUN-00243')->value('info_extra'), true)['region_nombre']);
        $a = app(CodificadorService::class)->importarCsv('instituciones', $this->muestra());
        $b = app(CodificadorService::class)->importarCsv('instituciones', $this->muestra());
        $this->assertSame(0, $b['creados']);
        $this->assertSame($a['creados'] + $a['actualizados'], $b['actualizados']);
        $x = json_decode((string) Institucion::query()->where('codigo', 'PE-MUN-00243')->value('info_extra'), true);
        $this->assertSame('Valparaíso', $x['region_nombre']);
        $this->assertSame('05606', $x['comuna']);
        $this->assertSame(1, Institucion::query()->where('codigo', 'PE-MUN-00243')->count());
    }

    public function test_el_formato_sencillo_y_el_separado_por_punto_y_coma_siguen_funcionando(): void
    {
        $svc = app(CodificadorService::class);
        $this->assertSame(2, $svc->importarCsv('instituciones', "codigo,nombre,tipo,codigo_padre\nPE-X-1,Uno,MUN,\nPE-X-2,Dos,SUB,PE-X-1\n")['creados']);
        $this->assertSame(1, $svc->importarCsv('instituciones', "codigo;nombre\nPE-X-3;Tres\n")['creados']);
        $this->assertSame(1, $svc->importarCsv('procesos', "codigo;descripcion\nPROC-1;Un proceso\n")['creados']);
        $this->expectException(FlujoException::class);
        $svc->importarCsv('instituciones', "foo,bar\n1,2\n");
    }

    public function test_la_pantalla_importa_el_archivo_oficial(): void
    {
        $admin = $this->usuario('admin');
        $f = UploadedFile::fake()->createWithContent('Listado_de_códigos_de_Instituciones.csv', $this->muestra());
        $this->comoFuncionario($admin)->post('/app/admin/codificador/importar', ['entidad' => 'instituciones', 'archivo' => $f])->assertSessionHas('ok');
        $this->assertSame('1.PE-MUN-00243', Institucion::query()->where('codigo', 'PE-MUN-00243')->value('oid'));
        $this->comoFuncionario($admin)->get('/app/admin/codificador')->assertOk()->assertSee('Listado de códigos de Instituciones');
    }

    public function test_la_sincronizacion_por_api_respeta_la_vigencia_el_tipo_y_el_oid(): void
    {
        config(['sgd.codificador.mode' => 'api', 'sgd.codificador.api_url' => null, 'sgd.codificador.version' => 'v1', 'sgd.codificador.id_codificacion' => 1]);
        $c = fn (int $id, string $cod, string $d, int $padre, string $fin, string $extra) => ['id' => $id, 'codigo' => $cod, 'descripcion' => $d, 'fecha_inicio' => '2021-12-14T11:39:58Z', 'fecha_fin' => $fin, 'IdCodificacion' => 1, 'info_extra' => $extra, 'padre' => $padre, 'secuencia' => $id, 'identificador' => ''];
        Http::swap(new Factory);
        Http::fake(['https://codificacion.digital.gob.cl/api/v1/codigos/1*' => Http::response(['codigos' => [
            $c(1, 'PE-UNI-00001', 'Universidad X', 0, '2999-12-30T21:00:00-03:00', '{"sigla":"UMCE","dr":"DS","nivel":"2","tipo":"Universidad"}'),
            $c(2, 'PE-HOS-00002', 'Hospital cerrado', 1, '2024-05-08T13:56:00-04:00', '{}'),
        ]])]);
        app(CodificadorService::class)->sincronizar();
        $u = Institucion::query()->where('codigo', 'PE-UNI-00001')->firstOrFail();
        $this->assertSame('UNI', $u->tipo); // el código manda sobre el texto de info_extra («Universidad»)
        $this->assertSame('1.PE-UNI-00001', $u->oid); // el listado trae el identificador vacío: se arma idCodificacion.código
        $this->assertTrue((bool) $u->activo);
        $h = Institucion::query()->where('codigo', 'PE-HOS-00002')->firstOrFail();
        $this->assertFalse((bool) $h->activo);
        $this->assertSame('PE-UNI-00001', $h->codigo_padre);
    }

    public function test_el_servidor_de_la_guia_postman_usa_rutas_sin_api_ni_version(): void
    {
        config(['sgd.codificador.api_url' => 'https://codificador.digital.gob.cl', 'sgd.codificador.id_codificacion' => 1]);
        Http::swap(new Factory);
        Http::fake(['https://codificador.digital.gob.cl/*' => Http::response(['codigos' => [['id' => 1, 'codigo' => 'PE-UNI-00001', 'descripcion' => 'U', 'fecha_inicio' => '2021-12-14T11:39:58.649Z', 'fecha_fin' => '2999-12-30T21:00:00-03:00', 'IdCodificacion' => 0, 'info_extra' => '{"sigla":"UMCE","dr":"DS","nivel":"2","tipo":"Universidad"}', 'padre' => 0]]])]);
        $api = app(CodificadorApi::class);
        $r = $api->codigos(['descripcion' => 'universidad', 'codigo' => 'uni', 'padre' => 0]);
        $this->assertSame('2021-12-14T11:39:58.649Z', $r[0]['fecha_inicio']);
        $this->assertSame('Universidad', $r[0]['info_extra']['tipo']);
        Http::assertSent(fn (Request $q) => str_starts_with($q->url(), 'https://codificador.digital.gob.cl/codigos/1?') && $q['descripcion'] === 'universidad' && $q['padre'] === 0);
        $api->detalle('PE-UNI-00001');
        Http::assertSent(fn (Request $q) => str_starts_with($q->url(), 'https://codificador.digital.gob.cl/codigos/1/PE-UNI-00001'));
    }

    public function test_el_municipio_por_defecto_coincide_con_el_codificador_y_alimenta_la_ubicacion_de_los_expedientes(): void
    {
        $svc = app(CodificadorService::class);
        $svc->asegurarMunicipioPorDefecto();
        $m = Institucion::query()->where('codigo', 'PE-MUN-00243')->firstOrFail();
        $this->assertSame(['243', '1.PE-MUN-00243', 'PE-SUB-00573', 'MUN'], [(string) $m->codificador_id, $m->oid, $m->codigo_padre, $m->tipo]);
        $this->assertSame('PE-MIN-00526', Institucion::query()->where('codigo', 'PE-SUB-00573')->value('codigo_padre'));
        $svc->asegurarMunicipioPorDefecto(); // idempotente
        $this->assertSame(1, Institucion::query()->where('codigo', 'PE-MUN-00243')->count());
        $u = CodificacionService::ubicacion();
        $this->assertSame(['region' => '05', 'provincia' => '056', 'comuna' => '05606'], $u['codigo_gestor_codigos']);
        $this->assertSame('Santo Domingo', $u['comuna']);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
        $this->assertSame('05606', $e->metadatos['ubicacion']['codigo_gestor_codigos']['comuna']);
    }

    public function test_el_destinatario_se_busca_en_el_catalogo_sin_cargar_miles_de_opciones(): void
    {
        app(CodificadorService::class)->importarCsv('instituciones', $this->muestra());
        $f = $this->usuario('funcionario', 1);
        $this->comoFuncionario($f)->getJson('/app/instituciones/buscar?q=Santo Domingo')->assertOk()->assertJsonFragment(['id' => 'PE-MUN-00243', 'texto' => 'Municipalidad de Santo Domingo']);
        $this->comoFuncionario($f)->getJson('/app/instituciones/buscar?q=PE-MUN-0023')->assertOk()->assertJsonFragment(['id' => 'PE-MUN-00238']);
        $this->comoFuncionario($f)->getJson('/app/instituciones/buscar?q=Hospital')->assertOk()->assertJsonMissing(['id' => 'PE-HOS-01531']); // inactivo: no se ofrece
        $this->comoFuncionario($f)->getJson('/app/instituciones/buscar?q=x')->assertStatus(422);
        $this->comoFuncionario($this->usuario('auditor'))->getJson('/app/instituciones/buscar?q=Santo')->assertForbidden();
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $f);
        $html = $this->comoFuncionario($f)->get("/app/expedientes/{$e->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('<option value="PE-MUN-00243"', $html);
        $this->assertStringContainsString('data-buscar-dd="institucion"', $html);
    }

    public function test_el_listado_completo_oficial_si_se_dispone_de_el(): void
    {
        $ruta = (string) env('CODIFICADOR_CSV_COMPLETO');
        if ($ruta === '' || ! is_file($ruta)) {
            $this->markTestSkipped('Defina CODIFICADOR_CSV_COMPLETO con la ruta del «Listado de códigos de Instituciones» para verificar el archivo completo.');
        }
        $r = app(CodificadorService::class)->importarCsv('instituciones', (string) file_get_contents($ruta));
        // el listado trae 2 filas incompletas («PE-undefined-…», sin descripción): se omiten y se informan
        $this->assertSame(['Fila 4868: código o nombre inválido', 'Fila 5336: código o nombre inválido'], $r['errores']);
        $this->assertSame(6450, $r['creados']);
        $this->assertSame(6450, Institucion::query()->where('origen', 'csv')->count());
        $this->assertSame(6337, Institucion::query()->where('origen', 'csv')->where('activo', true)->count());
        $this->assertSame(113, Institucion::query()->where('origen', 'csv')->where('activo', false)->count());
        $this->assertSame(0, Institucion::query()->whereNotNull('codigo_padre')->whereNotIn('codigo_padre', Institucion::query()->select('codigo'))->count(), 'todo código padre existe');
        $this->assertSame(411, Institucion::query()->where('tipo', 'MUN')->where('activo', true)->count());
        $this->assertSame('1.PE-MUN-00243', Institucion::query()->where('codigo', 'PE-MUN-00243')->value('oid'));
    }
}
