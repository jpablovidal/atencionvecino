<?php

namespace Tests\Feature;

use App\Models\ComunicacionOficial;
use App\Models\DocDigitalEntidad;
use App\Models\DocDigitalUsuario;
use App\Models\Documento;
use App\Models\Expediente;
use App\Services\ExpedienteService;
use App\Services\Interop\DocDigitalDirectorioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Directorio de DocDigital: carga automática y reanudable de entidades y personas, y su uso al despachar. */
class DocDigitalDirectorioTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
    }

    /** @return array<string,mixed> */
    private function pagina(array $items, int $pagina, int $paginas): array
    {
        return ['status' => 200, 'message' => 'OK', 'count' => count($items), 'result' => $items, 'total_count' => 3, 'total_pages' => $paginas, 'page' => $pagina];
    }

    private function fakeDirectorio(): void
    {
        $base = $this->dd;
        $usuarios = [
            ['usuario_id' => 11, 'usuario_run' => '12345678-5', 'usuario_nombre' => 'Ana Tramitadora', 'usuario_email' => 'ana@santodomingo.cl', 'usuario_cargo' => 'Secretaria', 'entidad_id' => 5001, 'entidad_nombre' => 'I. Municipalidad de Santo Domingo', 'organismo_id' => 1, 'organismo_nombre' => 'Municipalidades'],
            ['usuario_id' => 12, 'usuario_run' => '7654321-6', 'usuario_nombre' => 'Beto Gobierno Regional', 'usuario_email' => 'beto@gorev.cl', 'usuario_cargo' => 'Jefe', 'entidad_id' => 7001, 'entidad_nombre' => 'Gobierno Regional de Valparaíso', 'organismo_id' => 2, 'organismo_nombre' => 'GORE'],
        ];
        $this->ddFake([], [], [
            "$base/entidades/?*" => function (Request $r) {
                parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
                $p = (int) ($q['pageNumber'] ?? 0);

                return Http::response($this->pagina($p === 0
                    ? [['entidad_id' => 5001, 'entidad_nombre' => 'I. Municipalidad de Santo Domingo', 'entidad_padre_id' => 1, 'organismo' => ['organismo_id' => 1, 'organismo_nombre' => 'Municipalidades']], ['entidad_id' => 7001, 'entidad_nombre' => 'Gobierno Regional de Valparaíso']]
                    : [['entidad_id' => 7002, 'entidad_nombre' => 'Secretaría General de la Presidencia']], $p, 2), 200);
            },
            "$base/usuarios/?*" => function (Request $r) use ($usuarios) {
                parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
                $tipo = isset($q['tipo']) ? (int) $q['tipo'] : null;
                $lista = $usuarios;
                if (isset($q['idEntidad'])) {
                    $lista = array_values(array_filter($lista, fn ($u) => $u['entidad_id'] === (int) $q['idEntidad']));
                }
                if ($tipo !== null && ! in_array($tipo, [1, 2], true)) {
                    $lista = [];
                } elseif ($tipo === 2) {
                    $lista = array_slice($lista, 0, 1);
                }

                return Http::response($this->pagina($lista, 0, 1), 200);
            },
        ]);
    }

    public function test_carga_entidades_y_personas_sin_guardar_el_run_y_con_roles(): void
    {
        $this->ddConfig();
        $this->fakeDirectorio();
        $r = app(DocDigitalDirectorioService::class)->cargar(30);
        $this->assertTrue($r['completo']);
        $this->assertNull($r['error']);
        $this->assertSame(3, DocDigitalEntidad::query()->count());
        $this->assertSame(2, DocDigitalUsuario::query()->count());
        $ana = DocDigitalUsuario::query()->where('usuario_id', 11)->first();
        $this->assertSame([1, 2], $ana->rolesLista());
        $this->assertSame('***678-5', $ana->run_mascara);
        $this->assertStringNotContainsString('12345678', json_encode($ana->getAttributes()));
        $this->assertNull(DocDigitalUsuario::query()->where('usuario_id', 12)->value('roles'));
        $this->assertSame(64, strlen((string) $ana->run_huella));
    }

    public function test_el_run_de_un_funcionario_local_se_reconoce_por_su_huella(): void
    {
        $this->ddConfig();
        $this->fakeDirectorio();
        app(DocDigitalDirectorioService::class)->cargar(30);
        $u = $this->usuario('funcionario');
        $u->forceFill(['run' => '12345678-5'])->save();
        $f = app(DocDigitalDirectorioService::class)->de($u->refresh());
        $this->assertSame('Ana Tramitadora', $f?->nombre);
    }

    public function test_la_carga_es_reanudable_y_marca_no_vigente_lo_que_ya_no_esta(): void
    {
        $this->ddConfig();
        $this->fakeDirectorio();
        DocDigitalEntidad::query()->create(['entidad_id' => 9999, 'nombre' => 'Entidad que ya no existe', 'vigente' => true, 'sincronizado_en' => now()->subDay()]);
        $svc = app(DocDigitalDirectorioService::class);
        $r = $svc->cargar(5); // presupuesto mínimo: avanza lo que alcance
        $this->assertTrue($r['completo'] || $svc->estado()['cursor'] !== null);
        for ($i = 0; $i < 20 && ! ($r['completo'] ?? false); $i++) {
            $r = $svc->cargar(5);
        }
        $this->assertTrue($r['completo']);
        $this->assertFalse((bool) DocDigitalEntidad::query()->where('entidad_id', 9999)->value('vigente'));
        $this->assertTrue((bool) DocDigitalEntidad::query()->where('entidad_id', 7002)->value('vigente'));
        $this->assertArrayNotHasKey('cursor', $svc->estado());
        $this->assertFalse($svc->debeCargar()); // recién cargado: no vuelve a cargar hasta pasadas las horas configuradas
    }

    public function test_un_error_de_la_api_deja_el_cursor_y_el_mensaje(): void
    {
        $this->ddConfig();
        $this->ddFake([], [], ["{$this->dd}/entidades/?*" => Http::response('boom', 500)]);
        $r = app(DocDigitalDirectorioService::class)->cargar(10);
        $this->assertNotNull($r['error']);
        $this->assertFalse($r['completo']);
        $this->assertNotEmpty(app(DocDigitalDirectorioService::class)->estado()['cursor']);
    }

    public function test_sin_modo_api_no_carga_nada_y_el_comando_lo_dice(): void
    {
        config(['sgd.docdigital.mode' => 'outbox']);
        $this->artisan('docdigital:directorio')->expectsOutputToContain('no hay directorio')->assertSuccessful();
    }

    public function test_panel_del_directorio_y_busqueda_exigen_permiso(): void
    {
        $this->ddConfig();
        $this->fakeDirectorio();
        app(DocDigitalDirectorioService::class)->cargar(30);
        $this->comoFuncionario($this->usuario('funcionario'))->get('/app/docdigital/directorio')->assertForbidden();
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->get('/app/docdigital/directorio?ver=personas')->assertOk()->assertSee('Ana Tramitadora')->assertSee('Beto Gobierno Regional')->assertDontSee('12345678-5');
        $this->comoFuncionario($admin)->get('/app/docdigital/directorio?ver=entidades&q=Presidencia')->assertOk()->assertSee('Secretaría General de la Presidencia');
        $this->comoFuncionario($this->usuario('funcionario'))->getJson('/app/docdigital/directorio/buscar?tipo=entidad&q=Regional')->assertOk()->assertJsonFragment(['id' => 7001]);
        $this->comoFuncionario($this->usuario('auditor'))->getJson('/app/docdigital/directorio/buscar?tipo=entidad&q=Regional')->assertForbidden();
        $this->comoFuncionario($admin)->postJson('/app/docdigital/directorio/cargar')->assertStatus(302);
    }

    public function test_despacho_a_una_entidad_del_directorio_y_a_personas_de_esa_entidad(): void
    {
        $this->ddConfig();
        $this->fakeDirectorio();
        app(DocDigitalDirectorioService::class)->cargar(30);
        [$exp, $doc] = $this->expedienteConDocumentoFirmado();
        $f = $this->usuario('funcionario', 1);
        $url = "/app/expedientes/{$exp->id}/comunicaciones";
        $this->comoFuncionario($f)->post($url, ['documento_id' => $doc->id, 'destino' => 'docdigital', 'entidad_id' => 7001, 'usuarios' => [12], 'asunto' => 'Oficio al GORE'])->assertSessionHasNoErrors();
        $c = ComunicacionOficial::query()->latest('id')->first();
        $this->assertSame(7001, (int) $c->destino_entidad_id);
        $this->assertSame('DEMO-GORE-VAL', $c->institucion_codigo);
        $this->assertSame(12, json_decode($c->destino_usuarios, true)[0]['id']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/documentos/firmado/ingresar') && ($r['listado_id_entidades_destinatarias'] ?? null) === [7001] && ($r['listado_id_usuarios_destinatarios'] ?? null) === [12]);
        // una persona de otra entidad no es válida
        $this->comoFuncionario($f)->post($url, ['documento_id' => $doc->id, 'destino' => 'docdigital', 'entidad_id' => 7002, 'usuarios' => [12], 'asunto' => 'Oficio'])->assertSessionHas('error');
        // una entidad que no está en el directorio tampoco
        $this->comoFuncionario($f)->post($url, ['documento_id' => $doc->id, 'destino' => 'docdigital', 'entidad_id' => 424242, 'asunto' => 'Oficio'])->assertSessionHas('error');
        // no se repite el despacho del mismo documento a la misma entidad
        $this->comoFuncionario($f)->post($url, ['documento_id' => $doc->id, 'destino' => 'docdigital', 'entidad_id' => 7001, 'asunto' => 'Otra vez'])->assertSessionHas('error');
    }

    /** @return array{0:Expediente,1:Documento} */
    private function expedienteConDocumentoFirmado(): array
    {
        $f = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $f);
        $d = $this->documentoVigente($e, $f);
        $d->forceFill(['pdf_firmado_ruta' => $d->pdf_ruta, 'pdf_firmado_sha256' => $d->pdf_sha256])->save();

        return [$e, $d->refresh()];
    }
}
