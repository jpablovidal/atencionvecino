<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Institucion;
use App\Models\ProcesoCodificador;
use App\Services\Admin\CodificacionService;
use App\Services\Interop\CodificadorApi;
use App\Services\Interop\CodificadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\Escenario;
use Tests\TestCase;

/** API del Codificador (documento «Selecciona un servicio para consultar») y municipio por defecto. */
class CodificadorApiTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private const B = 'https://codificacion.digital.gob.cl/api';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        config(['sgd.codificador.mode' => 'api', 'sgd.codificador.api_url' => null, 'sgd.codificador.version' => 'v1', 'sgd.codificador.id_codificacion' => 1, 'sgd.codificador.id_codificacion_procesos' => null, 'sgd.codificador.api_token' => null]);
    }

    private function cod(int $id, string $codigo, string $desc, int $padre = 0, string $extra = '{"tipo":"MUN"}', string $ident = ''): array
    {
        return ['id' => $id, 'codigo' => $codigo, 'descripcion' => $desc, 'fecha_incio' => '2021-12-14T11:09:58.649Z', 'fecha_fin' => '2999-12-30T21:00:00-03:00', 'idCodificacion' => 1, 'info_extra' => $extra, 'padre' => $padre, 'secuencia' => 1, 'fecha_cambio' => '0001-01-01T00:00:00Z', 'identificador' => $ident];
    }

    private function fake(array $extra = []): void
    {
        Http::swap(new Factory);
        Http::fake($extra + [self::B.'/v1/codigos/1*' => Http::response(['codigos' => [
            $this->cod(573, 'PE-SUB-00573', 'Subsecretaría de Desarrollo Regional y Administrativo', 0, '{"tipo":"SUB"}'),
            $this->cod(243, 'PE-MUN-00243', 'Municipalidad de Santo Domingo', 573, '{"tipo":"MUN","nivel":"3"}', '1.PE-MUN-00243'),
            $this->cod(900, 'PE-MUN-00900', 'Municipalidad de Otra Comuna', 573),
        ]])]);
    }

    public function test_el_municipio_por_defecto_es_santo_domingo(): void
    {
        $this->assertSame('PE-MUN-00243', (include base_path('config/sgd.php'))['institucion_codigo'] ?? null);
        config(['sgd.institucion_codigo' => 'PE-MUN-00243']);
        $m = CodificacionService::municipio();
        $this->assertSame('PE-MUN-00243', $m['codigo']);
        $this->assertSame('Municipalidad de Santo Domingo', $m['nombre']);
        $this->assertSame('predeterminado', $m['origen']);
        $this->assertSame('PE-MUN-00243', CodificacionService::institucion());
    }

    public function test_el_comando_deja_la_municipalidad_en_el_catalogo_y_es_idempotente(): void
    {
        $this->artisan('codificador:municipio')->assertSuccessful();
        $this->artisan('codificador:municipio')->assertSuccessful();
        $m = Institucion::query()->where('codigo', 'PE-MUN-00243')->first();
        $this->assertSame('Municipalidad de Santo Domingo', $m->nombre);
        $this->assertSame('PE-SUB-00573', $m->codigo_padre);
        $this->assertSame(243, (int) $m->codificador_id);
        $this->assertSame('1.PE-MUN-00243', $m->oid);
        $this->assertSame(1, Institucion::query()->where('codigo', 'PE-MUN-00243')->count());
        $this->assertSame('Municipalidad de Santo Domingo', CodificacionService::municipio()['nombre']);
    }

    public function test_listado_con_filtros_detalle_y_jerarquia_segun_el_documento(): void
    {
        $this->fake([self::B.'/v1/v1/codigos/1/PE-MUN-00243' => Http::response(['codigos' => [$this->cod(243, 'PE-MUN-00243', 'Municipalidad de Santo Domingo')]]),
            self::B.'/v1/v1/codigos/1/PE-MUN-00243/jerarquia*' => Http::response(['codigos' => [['id' => 243, 'codigo' => 'PE-MUN-00243', 'descripcion' => 'Municipalidad', 'Hijos' => []]]])]);
        $api = app(CodificadorApi::class);
        $api->codigos(['codigo' => 'PE-MUN-00243', 'padre' => 573, 'descripcion' => 'Santo', 'desde' => '2021-01-01', 'hasta' => '2999-01-01', 'otro' => 'x']);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_starts_with($r->url(), self::B.'/v1/codigos/1?') && str_contains($r->url(), 'codigo=PE-MUN-00243') && str_contains($r->url(), 'padre=573') && ! str_contains($r->url(), 'otro='));
        $this->assertSame('Municipalidad de Santo Domingo', $api->detalle('PE-MUN-00243')['descripcion']);
        $this->assertSame('2021-12-14T11:09:58.649Z', $api->detalle('PE-MUN-00243')['fecha_inicio'], 'acepta «fecha_incio» del documento');
        $this->assertSame([], $api->jerarquia('PE-MUN-00243', 'jsontree', 'desc')[0]['hijos']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/jerarquia') && str_contains($r->url(), 'tipovista=jsontree') && str_contains($r->url(), 'orden=desc'));
    }

    public function test_detalle_reintenta_sin_el_v1_repetido_si_el_servidor_responde_404(): void
    {
        $this->fake([self::B.'/v1/v1/codigos/1/PE-MUN-00243' => Http::response('not found', 404), self::B.'/v1/codigos/1/PE-MUN-00243' => Http::response(['codigos' => [$this->cod(243, 'PE-MUN-00243', 'Municipalidad de Santo Domingo')]])]);
        $this->assertSame(243, app(CodificadorApi::class)->detalle('PE-MUN-00243')['id']);
    }

    public function test_los_identificadores_se_validan_y_la_url_debe_ser_https(): void
    {
        $this->expectException(FlujoException::class);
        app(CodificadorApi::class)->detalle('../../etc/passwd');
    }

    public function test_la_url_no_https_se_rechaza(): void
    {
        config(['sgd.codificador.api_url' => 'http://codificacion.digital.gob.cl/api']);
        $this->expectException(FlujoException::class);
        app(CodificadorApi::class)->codigos();
    }

    public function test_sincronizar_guarda_instituciones_con_padre_oid_y_tipo_y_no_borra(): void
    {
        $this->fake();
        Institucion::query()->create(['codigo' => 'PE-ANTIGUA-1', 'nombre' => 'Antigua', 'origen' => 'csv', 'activo' => true]);
        $r = app(CodificadorService::class)->sincronizar();
        $this->assertSame(3, $r['instituciones']['creados']);
        $m = Institucion::query()->where('codigo', 'PE-MUN-00243')->first();
        $this->assertSame('PE-SUB-00573', $m->codigo_padre);
        $this->assertSame('MUN', $m->tipo);
        $this->assertSame('1.PE-MUN-00243', $m->oid);
        $this->assertSame(243, (int) $m->codificador_id);
        $this->assertStringContainsString('"nivel":"3"', (string) $m->info_extra);
        $this->assertNotNull(Institucion::query()->where('codigo', 'PE-ANTIGUA-1')->first(), 'no se borra lo que no llega');
        $r2 = app(CodificadorService::class)->sincronizar();
        $this->assertSame(0, $r2['instituciones']['creados']);
        $this->assertSame(3, $r2['instituciones']['actualizados']);
    }

    public function test_sincronizar_tambien_los_procedimientos_si_se_indica_su_codificacion(): void
    {
        config(['sgd.codificador.id_codificacion_procesos' => 7]);
        $this->fake([self::B.'/v1/codigos/7*' => Http::response(['codigos' => [$this->cod(1, 'PA-0001', 'Permiso de circulación')]])]);
        $r = app(CodificadorService::class)->sincronizar();
        $this->assertSame(1, $r['procesos']['creados']);
        $this->assertSame('Permiso de circulación', ProcesoCodificador::query()->where('codigo', 'PA-0001')->value('nombre'));
    }

    public function test_el_modo_manual_no_sincroniza(): void
    {
        config(['sgd.codificador.mode' => 'manual']);
        $this->expectException(FlujoException::class);
        app(CodificadorService::class)->sincronizar();
    }

    public function test_validar_confirma_que_la_municipalidad_existe_en_el_codificador(): void
    {
        $this->fake();
        config(['sgd.institucion_codigo' => 'PE-MUN-00243']);
        $filas = collect($this->comoFuncionario($this->usuario('admin'))->post('/app/admin/api/codificador/probar')->assertSessionHas('pruebas')->getSession()->get('pruebas'));
        $this->assertTrue($filas->first(fn ($f) => str_starts_with($f[0], 'La API'))[1]);
        $f = $filas->first(fn ($f) => str_starts_with($f[0], 'La institución existe'));
        $this->assertTrue($f[1]);
        $this->assertStringContainsString('OID 1.PE-MUN-00243', $f[2]);
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('x', 500)]);
        $malas = collect($this->comoFuncionario($this->usuario('admin'))->post('/app/admin/api/codificador/probar')->getSession()->get('pruebas'))->first(fn ($f) => str_starts_with($f[0], 'La API'));
        $this->assertFalse($malas[1]);
    }

    public function test_la_pantalla_trae_los_campos_nuevos_con_sus_valores_por_defecto(): void
    {
        $h = $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/api/codificador')->assertOk()->getContent();
        foreach (['API del Codificador', 'Id de la codificación', 'Versión de la API', 'PE-MUN-00243'] as $t) {
            $this->assertStringContainsString($t, $h);
        }
    }
}
