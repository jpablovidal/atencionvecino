<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarComunicacionDocDigital;
use App\Models\Archivo;
use App\Models\ComunicacionOficial;
use App\Models\Expediente;
use App\Models\Institucion;
use App\Models\PiseeServicio;
use App\Models\ProcesoCodificador;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use App\Services\Interop\CodificadorService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\PiseeService;
use App\Support\ProductionGuard;
use App\Support\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

class InteroperabilidadTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    private Expediente $exp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->exp = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
    }

    // ------------------------------------------------------------------ Codificador
    public function test_codificador_importa_csv_valida_y_actualiza(): void
    {
        $csv = "codigo,nombre,tipo,codigo_padre\nINS-1,Servicio Uno,Servicio,\nINS-2,Servicio Dos,,INS-1\nmal codigo!,Inválido,,\nINS-3,,,\n";
        $r = app(CodificadorService::class)->importarCsv('instituciones', $csv);
        $this->assertSame(2, $r['creados']);
        $this->assertCount(2, $r['errores']);
        $r = app(CodificadorService::class)->importarCsv('instituciones', "codigo,nombre\nINS-1,Servicio Uno Renombrado\n");
        $this->assertSame(1, $r['actualizados']);
        $this->assertSame('Servicio Uno Renombrado', Institucion::query()->where('codigo', 'INS-1')->value('nombre'));
        $this->assertDatabaseHas('audit_log', ['accion' => 'codificador.carga']);
        $this->expectException(FlujoException::class);
        app(CodificadorService::class)->importarCsv('procesos', "foo,bar\n1,2\n"); // sin cabecera válida
    }

    public function test_codificador_rechaza_binarios_y_entidades_invalidas(): void
    {
        $this->expectException(FlujoException::class);
        app(CodificadorService::class)->importarCsv('instituciones', "codigo,nombre\n\xFF\xFE,x");
    }

    public function test_codificador_subida_por_http_solo_admin(): void
    {
        $csv = $this->archivo('i.csv', "codigo,nombre\nHTTP-1,Desde HTTP\n");
        $this->comoFuncionario($this->usuario('funcionario'))->post('/app/admin/codificador/importar', ['entidad' => 'instituciones', 'archivo' => $csv])->assertForbidden();
        $this->comoFuncionario($this->usuario('admin'))->post('/app/admin/codificador/importar', ['entidad' => 'instituciones', 'archivo' => $this->archivo('i.csv', "codigo,nombre\nHTTP-1,Desde HTTP\n")])->assertSessionHas('ok');
        $this->assertDatabaseHas('instituciones', ['codigo' => 'HTTP-1', 'origen' => 'csv']);
        $this->comoFuncionario($this->usuario('admin'))->get('/app/admin/codificador')->assertOk()->assertSee('Codificador');
    }

    public function test_codificador_sincroniza_por_api_con_url_fija_https(): void
    {
        config(['sgd.codificador.mode' => 'api', 'sgd.codificador.api_url' => 'https://codificador.ejemplo.gob.cl/catalogo']);
        Http::fake(['codificador.ejemplo.gob.cl/*' => Http::response(['instituciones' => [['codigo' => 'API-1', 'nombre' => 'Desde API']], 'procesos' => [['codigo' => 'P-1', 'nombre' => 'Proceso API', 'institucion_codigo' => 'API-1']]], 200)]);
        $r = app(CodificadorService::class)->sincronizar();
        $this->assertSame(1, $r['instituciones']['creados']);
        $this->assertDatabaseHas('procesos_codificador', ['codigo' => 'P-1', 'origen' => 'api']);
        config(['sgd.codificador.api_url' => 'http://inseguro.cl']);
        $this->expectException(FlujoException::class);
        app(CodificadorService::class)->sincronizar();
    }

    public function test_tramite_referencia_proceso_existente_y_metadatos_llevan_codigos(): void
    {
        config(['sgd.institucion_codigo' => 'DEMO-MUN-SD']);
        $base = ['codigo_interno' => 'Z-1', 'nombre' => 'Trámite Z', 'plazo_dias' => 5, 'unidad_propietaria_id' => 3, 'unidades' => [3]];
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->post('/app/tramites', $base + ['proceso_codigo' => 'NO-EXISTE'])->assertSessionHasErrors('proceso_codigo');
        $this->comoFuncionario($admin)->post('/app/tramites', $base + ['proceso_codigo' => 'DEMO-PROC-CERT'])->assertSessionHasNoErrors();
        $this->assertSame('DEMO-PROC-CERT', Tramite::query()->where('codigo_interno', 'Z-1')->value('proceso_codigo'));
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
        $this->assertSame('DEMO-MUN-SD', $e->metadatos['identificacion']['institucion_codigo']);
        $this->assertSame('DEMO-PROC-EDIF', $e->metadatos['procedimiento']['codigo_proceso']);
        $this->assertSame(2, ProcesoCodificador::query()->count());
    }

    // ------------------------------------------------------------------ PISEE
    private function servicio(): PiseeServicio
    {
        return PiseeServicio::query()->where('codigo', 'DEMO-CONSULTA')->firstOrFail();
    }

    public function test_pisee_deshabilitado_por_defecto(): void
    {
        $this->assertSame('disabled', config('sgd.pisee.mode'));
        Http::fake();
        $this->expectException(FlujoException::class);
        app(PiseeService::class)->consultar($this->exp, $this->servicio(), $this->usuario('funcionario', 1), 'Verificar antecedentes del solicitante');
        Http::assertNothingSent();
    }

    public function test_pisee_api_consulta_solo_al_solicitante_con_mtls_cabecera_y_sin_redirecciones(): void
    {
        config(['sgd.pisee.mode' => 'api', 'sgd.pisee.base_url' => 'https://nodo.municipio.local:8443', 'sgd.pisee.header_cliente' => 'X-Road-Client', 'sgd.pisee.valor_cliente' => 'CL/GOB/MUN/SGD',
            'sgd.pisee.cert' => '/etc/cert.pem', 'sgd.pisee.key' => '/etc/key.pem']);
        Http::fake(['nodo.municipio.local:8443/*' => Http::response(['persona' => ['vigente' => true], 'dato' => 'secreto-pisee-123'], 200)]);
        $c = app(PiseeService::class)->consultar($this->exp, $this->servicio(), $this->usuario('funcionario', 1), 'Verificar antecedentes del solicitante');
        $this->assertSame('ok', $c->estado);
        $this->assertSame($this->exp->solicitud->run, $c->run_consultado);
        Http::assertSent(function ($req) {
            $this->assertStringStartsWith('https://nodo.municipio.local:8443/demo/v1/consulta', $req->url());
            $this->assertSame('CL/GOB/MUN/SGD', $req->header('X-Road-Client')[0]);
            $this->assertStringContainsString('run='.Run::sinDv($this->exp->solicitud->run), $req->url()); // RUN sin DV

            return true;
        });
        $o = app(PiseeService::class)->opcionesTls();
        $this->assertSame(['verify' => true, 'cert' => '/etc/cert.pem', 'ssl_key' => '/etc/key.pem'], $o); // mTLS, verificación del servidor activa
        // resultado cifrado en reposo y jamás en la bitácora
        $a = Archivo::query()->findOrFail($c->archivo_id);
        $this->assertTrue($a->sensible);
        $this->assertStringNotContainsString('secreto-pisee', (string) file_get_contents(config('sgd.storage_path').'/'.$a->ruta));
        $this->assertStringContainsString('secreto-pisee-123', app(FileVault::class)->get($a->ruta, $a->sha256));
        $this->assertStringNotContainsString('secreto-pisee', json_encode(DB::table('audit_log')->get()));
        $this->assertDatabaseHas('audit_log', ['accion' => 'pisee.consulta', 'sensible' => 1]);
    }

    public function test_pisee_exige_finalidad_url_https_y_expediente_abierto(): void
    {
        config(['sgd.pisee.mode' => 'mock']);
        $svc = app(PiseeService::class);
        $u = $this->usuario('funcionario', 1);
        foreach (['', 'corta', "dos\nlineas de finalidad larga"] as $f) {
            try {
                $svc->consultar($this->exp, $this->servicio(), $u, $f);
                $this->fail('debió exigir finalidad');
            } catch (FlujoException) {
                $this->addToAssertionCount(1);
            }
        }
        config(['sgd.pisee.mode' => 'api', 'sgd.pisee.base_url' => 'http://inseguro.local']);
        Http::fake();
        $c = $svc->consultar($this->exp, $this->servicio(), $u, 'Finalidad suficientemente larga');
        $this->assertSame('error', $c->estado); // nodo no https => no se llama
        Http::assertNothingSent();
        app(ExpedienteService::class)->cerrar($this->exp);
        $this->expectException(FlujoException::class);
        $svc->consultar($this->exp->refresh(), $this->servicio(), $u, 'Finalidad suficientemente larga');
    }

    public function test_pisee_error_del_nodo_queda_registrado_sin_resultado(): void
    {
        config(['sgd.pisee.mode' => 'api', 'sgd.pisee.base_url' => 'https://nodo.local']);
        Http::fake(['nodo.local/*' => Http::response(['error' => 'x'], 503)]);
        $c = app(PiseeService::class)->consultar($this->exp, $this->servicio(), $this->usuario('funcionario', 1), 'Finalidad suficientemente larga');
        $this->assertSame(['error', 503, null], [$c->estado, $c->http_status, $c->archivo_id]);
    }

    public function test_pisee_lista_blanca_de_rutas_y_permisos(): void
    {
        $svc = app(PiseeService::class);
        foreach (['/ok/v1/x', '/a/b-c_d.e/{id}'] as $ok) {
            $this->assertTrue($svc->rutaValida($ok), $ok);
        }
        foreach (['http://evil.cl/x', '//evil.cl/x', '/a/../b', 'sin-barra', '/a b', "/a\r\nHost: x", '/a?x=1', '/'] as $mal) {
            $this->assertFalse($svc->rutaValida($mal), $mal);
        }
        $admin = $this->usuario('admin');
        $this->comoFuncionario($admin)->post('/app/admin/pisee', ['codigo' => 'X1', 'nombre' => 'X', 'ruta' => 'http://evil.cl', 'metodo' => 'GET'])->assertSessionHasErrors('ruta');
        $this->comoFuncionario($admin)->post('/app/admin/pisee', ['codigo' => 'X1', 'nombre' => 'X', 'ruta' => '/svc/v1/x', 'metodo' => 'DELETE'])->assertSessionHasErrors('metodo');
        $this->comoFuncionario($admin)->post('/app/admin/pisee', ['codigo' => 'X1', 'nombre' => 'X', 'ruta' => '/svc/v1/x', 'metodo' => 'GET', 'param_run' => 'run'])->assertSessionHasNoErrors();
        // otra unidad / admin no pueden consultar sobre este expediente
        config(['sgd.pisee.mode' => 'mock']);
        $body = ['servicio_id' => $this->servicio()->id, 'finalidad' => 'Finalidad suficientemente larga'];
        $this->comoFuncionario($this->usuario('funcionario', 3))->post("/app/expedientes/{$this->exp->id}/pisee", $body)->assertForbidden();
        $this->comoFuncionario($admin)->post("/app/expedientes/{$this->exp->id}/pisee", $body)->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->post("/app/expedientes/{$this->exp->id}/pisee", $body)->assertForbidden();
        $this->comoFuncionario($this->usuario('funcionario', 1))->post("/app/expedientes/{$this->exp->id}/pisee", $body)->assertSessionHas('ok');
        $this->comoFuncionario($this->usuario('funcionario', 1))->get("/app/expedientes/{$this->exp->id}")->assertSee('SIMULADO');
    }

    // ------------------------------------------------------------------ DocDigital
    private function docVigente(bool $firmado = false)
    {
        $d = $this->documentoVigente($this->exp, $this->usuario('funcionario', 1));
        if ($firmado) {
            $d->forceFill(['pdf_firmado_ruta' => $d->pdf_ruta, 'pdf_firmado_sha256' => $d->pdf_sha256])->save();
        }

        return $d->refresh();
    }

    private function modo(string $m, bool $url = true): void
    {
        $this->ddConfig($m, $url ? [] : ['sgd.docdigital.url_demo' => null]);
    }

    public function test_docdigital_outbox_queda_pendiente_hasta_registrar_evidencia(): void
    {
        $d = $this->docVigente();
        Http::fake();
        Queue::fake();
        $svc = app(ComunicacionService::class);
        $c = $svc->crearSaliente($this->exp, $d, 'DEMO-SEGPRES', 'Remite decreto', $this->usuario('funcionario', 1));
        $this->assertSame('pendiente', $c->estado);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->expectFlujo(fn () => $svc->crearSaliente($this->exp, $d, 'DEMO-SEGPRES', 'Otra vez', $this->usuario('funcionario', 1))); // duplicado
        $this->expectFlujo(fn () => $svc->crearSaliente($this->exp, $d, 'NO-EXISTE', 'Asunto', $this->usuario('funcionario', 1)));
        $this->expectFlujo(fn () => $svc->crearSaliente($this->exp, $d, 'DEMO-GORE-VAL', "Asunto\r\nBcc: x", $this->usuario('funcionario', 1)));
        $this->comoFuncionario($this->usuario('funcionario', 1))->post("/app/comunicaciones/{$c->id}/evidencia", ['folio' => 'DD-2026-000123'])->assertSessionHas('ok');
        $c->refresh();
        $this->assertSame(['enviada', 'docdigital_manual', 'DD-2026-000123'], [$c->estado, $c->evidencia_tipo, $c->evidencia]);
    }

    public function test_docdigital_api_envia_con_pdf_firmado_y_es_idempotente(): void
    {
        $this->modo('demo');
        Queue::fake();
        $d = $this->docVigente(true);
        $c = app(ComunicacionService::class)->crearSaliente($this->exp, $d, 'DEMO-SEGPRES', 'Remite decreto', $this->usuario('funcionario', 1));
        Queue::assertPushed(EnviarComunicacionDocDigital::class);
        $this->ddFake();
        (new EnviarComunicacionDocDigital($c->id))->handle(app(ComunicacionService::class));
        (new EnviarComunicacionDocDigital($c->id))->handle(app(ComunicacionService::class)); // idempotente
        $c->refresh();
        $this->assertSame(['enviada', 'docdigital_id', '987654'], [$c->estado, $c->evidencia_tipo, $c->evidencia]);
        Http::assertSentCount(1); // token fijo: solo el despacho
        Http::assertSent(function ($req) {
            $this->assertSame('POST', $req->method());
            $this->assertStringEndsWith('/documentos/firmado/ingresar', $req->url());
            $this->assertSame('Bearer tok-dd', $req->header('Authorization')[0]);
            $this->assertSame([7002], $req['listado_id_entidades_destinatarias']);
            $this->assertSame(5001, $req['id_entidad_creadora']);
            $this->assertSame(3, $req['tipo_id']);
            $this->assertSame('Remite decreto', $req['materia']);
            $this->assertFalse($req['es_reservado']);
            $this->assertNotSame('', (string) $req['folio']);
            $this->assertStringStartsWith('%PDF', base64_decode($req['documento']));
            $this->assertStringEndsWith('.pdf', $req['nombre']);

            return true;
        });
    }

    public function test_docdigital_api_exige_firma_real_y_falla_cerrado(): void
    {
        $this->modo('demo');
        Queue::fake();
        $sinFirma = $this->docVigente(false); // firma simulada: sin PDF firmado por FirmaGob
        $this->expectFlujo(fn () => app(ComunicacionService::class)->crearSaliente($this->exp, $sinFirma, 'DEMO-SEGPRES', 'Remite', $this->usuario('funcionario', 1)));
        $this->docVigente(true);
        // documentos de otro estado no se comunican
        $borrador = app(DocumentoService::class)->crear($this->exp, TipoDocumento::query()->first(), null, 'Borrador', $this->usuario('funcionario', 1));
        $this->expectFlujo(fn () => app(ComunicacionService::class)->crearSaliente($this->exp, $borrador, 'DEMO-SEGPRES', 'Remite', $this->usuario('funcionario', 1)));
    }

    public function test_docdigital_fallo_queda_fallida_y_se_reintenta(): void
    {
        $this->modo('demo');
        Queue::fake();
        $d = $this->docVigente(true);
        $svc = app(ComunicacionService::class);
        $c = $svc->crearSaliente($this->exp, $d, 'DEMO-SEGPRES', 'Remite', $this->usuario('funcionario', 1));
        $this->ddFake([], [], [$this->dd.'/documentos/firmado/ingresar' => Http::sequence()->push(['status' => 500, 'message' => 'Error', 'error' => 'falla interna'], 500)->push($this->ddOk(['id_documento' => 9, 'id_solicitud' => 1]), 200)]);
        try {
            $svc->enviar($c);
            $this->fail('debe propagar');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $c->refresh();
        $this->assertSame(['fallida', 1, null], [$c->estado, $c->intentos, $c->evidencia]);
        $svc->enviar($c);
        $this->assertSame('enviada', $c->refresh()->estado);
        // modo API: la evidencia manual está prohibida
        $this->expectFlujo(fn () => $svc->registrarEvidencia($c, 'x'));
    }

    public function test_docdigital_sin_url_o_ruta_configurada_falla_cerrado(): void
    {
        $this->modo('demo', false);
        $d = $this->docVigente(true);
        Queue::fake();
        $svc = app(ComunicacionService::class);
        $c = $svc->crearSaliente($this->exp, $d, 'DEMO-SEGPRES', 'Remite', $this->usuario('funcionario', 1));
        Http::fake();
        try {
            $svc->enviar($c);
        } catch (\Throwable) {
        }
        $this->assertSame('fallida', $c->refresh()->estado);
        Http::assertNothingSent();
    }

    public function test_docdigital_mock_marca_evidencia_como_simulada(): void
    {
        $this->modo('mock');
        Queue::fake();
        $d = $this->docVigente(false);
        $svc = app(ComunicacionService::class);
        $c = $svc->crearSaliente($this->exp, $d, 'DEMO-SEGPRES', 'Remite', $this->usuario('funcionario', 1));
        $svc->enviar($c);
        $this->assertSame('simulado', $c->refresh()->evidencia_tipo);
        $this->comoFuncionario($this->usuario('funcionario', 1))->get("/app/expedientes/{$this->exp->id}")->assertSee('SIMULADO – no enviado realmente');
    }

    public function test_docdigital_recibidos_se_guardan_cifrados_sin_duplicar_y_validan_pdf(): void
    {
        $this->modo('demo');
        $pdf = $this->pdfValido();
        $this->ddFake([1 => 'Oficio entrante', 2 => 'Malicioso', 3 => 'No PDF'], [1 => $pdf, 2 => "%PDF-1.4\n<</JS(x)>>\n%%EOF", 3 => "MZ\x90\x00 exe"]);
        $svc = app(ComunicacionService::class);
        $this->assertSame(['nuevas' => 1, 'omitidas' => 2], $svc->sincronizarRecibidos());
        $this->assertSame(['nuevas' => 0, 'omitidas' => 2], $svc->sincronizarRecibidos()); // sin duplicar
        $c = ComunicacionOficial::query()->where('direccion', 'entrante')->firstOrFail();
        $this->assertSame('recibida', $c->estado);
        $a = Archivo::query()->findOrFail($c->archivo_id);
        $this->assertStringNotContainsString('%PDF', (string) file_get_contents(config('sgd.storage_path').'/'.$a->ruta));
        // visible solo para la unidad receptora (SECMUN) y el auditor
        $this->comoFuncionario($this->usuario('secretario'))->get('/app/comunicaciones')->assertOk()->assertSee('Oficio entrante');
        $this->comoFuncionario($this->usuario('secretario'))->get("/app/comunicaciones/{$c->id}/archivo")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->comoFuncionario($this->usuario('funcionario', 1))->get('/app/comunicaciones')->assertDontSee('Oficio entrante');
        $this->comoFuncionario($this->usuario('funcionario', 1))->get("/app/comunicaciones/{$c->id}/archivo")->assertNotFound();
        $this->comoFuncionario($this->usuario('admin'))->get('/app/comunicaciones')->assertForbidden();
        $this->comoFuncionario($this->usuario('auditor'))->get("/app/comunicaciones/{$c->id}/archivo")->assertOk();
        $this->artisan('docdigital:sincronizar')->assertSuccessful();
    }

    public function test_comando_outbox_no_hace_nada(): void
    {
        Http::fake();
        $this->artisan('docdigital:sincronizar')->expectsOutputToContain('outbox')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_guard_de_produccion_con_interoperabilidad(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://sgd.santodomingo.cl', 'sgd.claveunica.mode' => 'real', 'sgd.firmagob.mode' => 'prod', 'mail.default' => 'log', 'sgd.llm.enabled' => false, 'sgd.admin_2fa' => true]);
        ProductionGuard::verificar();
        foreach ([['sgd.docdigital.mode', 'mock'], ['sgd.docdigital.mode', 'demo'], ['sgd.pisee.mode', 'mock']] as [$k, $v]) {
            config([$k => $v]);
            $this->assertThrowsRuntime();
            config([$k => $k === 'sgd.pisee.mode' ? 'disabled' : 'outbox']);
        }
        config(['sgd.pisee.mode' => 'api', 'sgd.pisee.base_url' => 'http://x']);
        $this->assertThrowsRuntime();
        config(['sgd.pisee.mode' => 'disabled', 'sgd.docdigital.mode' => 'prod', 'sgd.docdigital.url_prod' => null]);
        $this->assertThrowsRuntime();
        config(['sgd.docdigital.mode' => 'outbox']);
        ProductionGuard::verificar();
    }

    private function assertThrowsRuntime(): void
    {
        try {
            ProductionGuard::verificar();
            $this->fail('Debió fallar cerrado');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    private function expectFlujo(callable $f): void
    {
        try {
            $f();
            $this->fail('Se esperaba FlujoException');
        } catch (FlujoException) {
            $this->addToAssertionCount(1);
        }
    }
}
