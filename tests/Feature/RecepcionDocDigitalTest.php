<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarComunicacionDocDigital;
use App\Models\Archivo;
use App\Models\ComunicacionOficial;
use App\Models\Expediente;
use App\Models\Plantilla;
use App\Models\TipoDocumento;
use App\Models\Unidad;
use App\Services\AuditoriaService;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalClient;
use App\Services\Interop\DocDigital\DocDigitalException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Ciclo completo: recibir por DocDigital → expediente → acuse → gestión → respuesta (decreto/oficio/…) → despacho. */
class RecepcionDocDigitalTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        config(['sgd.institucion_codigo' => 'DEMO-MUN-SD']);
    }

    private function modo(string $m): void
    {
        config(['sgd.docdigital.mode' => $m, 'sgd.docdigital.url_demo' => 'https://demodoc.ejemplo.gob.cl', 'sgd.docdigital.api_token' => 'tok', 'sgd.docdigital.path_enviar' => '/api/v2/enviar',
            'sgd.docdigital.path_recibidos' => '/api/v2/recibidos', 'sgd.docdigital.path_acuse' => '/api/v2/acuse']);
        $this->app->forgetInstance(DocDigitalClient::class);
        $this->app->forgetInstance(ComunicacionService::class);
    }

    private function recibir(array $ids = ['IN-1']): void
    {
        $docs = array_map(fn ($id) => ['id' => $id, 'asunto' => "Oficio {$id} del GORE", 'origen' => ['codigo' => 'DEMO-GORE-VAL', 'nombre' => 'Gobierno Regional de Valparaíso'], 'archivo' => ['content' => base64_encode($this->pdfValido("<<$id>>"))]], $ids);
        Http::fake(['demodoc.ejemplo.gob.cl/api/v2/recibidos' => Http::response(['documentos' => $docs], 200)]);
        app(ComunicacionService::class)->sincronizarRecibidos();
    }

    public function test_cada_comunicacion_recibida_crea_un_expediente_con_identificador_unico(): void
    {
        $this->modo('demo');
        $this->recibir(['IN-1', 'IN-2', 'IN-3']);
        $exps = Expediente::query()->where('origen', 'docdigital')->orderBy('id')->get();
        $this->assertCount(3, $exps);
        $this->assertCount(3, $exps->pluck('codigo')->unique());
        $e = $exps[0];
        $this->assertMatchesRegularExpression('/^EXP-\d{4}-\d{6}$/', $e->codigo);
        $this->assertSame('DEMO-MUN-SD-'.$e->codigo, $e->metadatos['identificacion']['identificador_unico']);
        $this->assertNull($e->solicitud_id);
        $this->assertNull($e->responsable_id);
        $this->assertSame('abierto', $e->estado);
        foreach (['identificacion', 'descripcion', 'temporalidad', 'caracterizacion', 'relaciones', 'poderes', 'seguridad', 'procedimiento', 'version'] as $k) {
            $this->assertArrayHasKey($k, $e->metadatos, "MGDEE $k");
        }
        $this->assertSame('DEMO-GORE-VAL', $e->metadatos['relaciones']['institucion_origen_codigo']);
        $c = ComunicacionOficial::query()->findOrFail($e->comunicacion_id);
        $this->assertSame([$e->id, 'recibida', 'pendiente'], [$c->expediente_id, $c->estado, $c->acuse_estado]);
        // el PDF original queda en el índice del expediente y cifrado
        $a = Archivo::query()->where('expediente_id', $e->id)->firstOrFail();
        $this->assertSame('docdigital', $a->ambito);
        $this->assertStringNotContainsString('%PDF', (string) file_get_contents(config('sgd.storage_path').'/'.$a->ruta));
        // idempotente: no duplica expedientes
        $this->recibir(['IN-1', 'IN-2', 'IN-3']);
        $this->assertSame(3, Expediente::query()->where('origen', 'docdigital')->count());
        // convive con los expedientes de solicitudes sin colisión de códigos
        $s = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
        $this->assertNotContains($s->codigo, $exps->pluck('codigo')->all());
    }

    public function test_falla_cerrado_sin_unidad_de_recepcion_y_deja_todo_o_nada(): void
    {
        $this->modo('demo');
        config(['sgd.docdigital.unidad_recepcion' => 'NO-EXISTE']);
        Http::fake(['demodoc.ejemplo.gob.cl/*' => Http::response(['documentos' => []], 200)]);
        $this->expectException(DocDigitalException::class);
        app(ComunicacionService::class)->sincronizarRecibidos();
    }

    public function test_si_falla_la_creacion_del_expediente_no_queda_nada_a_medias(): void
    {
        $this->modo('demo');
        $mock = $this->partialMock(ExpedienteService::class, fn ($m) => $m->shouldReceive('crearDesdeComunicacion')->andThrow(new \RuntimeException('boom')));
        $this->app->instance(ExpedienteService::class, $mock);
        $this->app->forgetInstance(ComunicacionService::class);
        Http::fake(['demodoc.ejemplo.gob.cl/*' => Http::response(['documentos' => [['id' => 'X1', 'asunto' => 'A', 'archivo' => ['content' => base64_encode($this->pdfValido())]]]], 200)]);
        try {
            app(ComunicacionService::class)->sincronizarRecibidos();
            $this->fail('debe propagar');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, ComunicacionOficial::query()->count());
        $this->assertSame(0, Archivo::query()->where('ambito', 'docdigital')->count());
    }

    public function test_sin_acuse_no_se_puede_gestionar_y_con_acuse_si(): void
    {
        $this->modo('demo');
        $this->recibir();
        $e = Expediente::query()->where('origen', 'docdigital')->firstOrFail();
        $sec = $this->usuario('secretario'); // unidad SECMUN = unidad receptora
        $tipo = TipoDocumento::query()->where('codigo', 'OFICIO')->first();
        $ds = app(DocumentoService::class);
        try {
            $ds->crear($e, $tipo, null, 'Respuesta', $sec);
            $this->fail('debe exigir acuse');
        } catch (FlujoException $ex) {
            $this->assertStringContainsString('acusar recibo', $ex->getMessage());
        }
        Http::fake(['demodoc.ejemplo.gob.cl/api/v2/acuse' => Http::response(['id' => 'ACUSE-55'], 200)]);
        $c = ComunicacionOficial::query()->findOrFail($e->comunicacion_id);
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/acuse")->assertSessionHas('ok');
        $c->refresh();
        $this->assertSame(['registrado', 'api', 'ACUSE-55', $sec->id], [$c->acuse_estado, $c->acuse_tipo, $c->acuse_evidencia, $c->acuse_por_id]);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v2/acuse') && $r['id'] === 'IN-1' && $r->header('Authorization')[0] === 'Bearer tok');
        $this->assertSame('borrador', $ds->crear($e->refresh(), $tipo, null, 'Respuesta', $sec)->estado);
        // un segundo acuse no es posible
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/acuse")->assertSessionHas('error');
    }

    public function test_acuse_falla_cerrado_y_permisos(): void
    {
        $this->modo('demo');
        $this->recibir();
        $e = Expediente::query()->where('origen', 'docdigital')->firstOrFail();
        $c = ComunicacionOficial::query()->findOrFail($e->comunicacion_id);
        Http::fake(['demodoc.ejemplo.gob.cl/api/v2/acuse' => Http::response([], 500)]);
        $this->comoFuncionario($this->usuario('secretario'))->post("/app/comunicaciones/{$c->id}/acuse")->assertSessionHas('error');
        $this->assertSame('pendiente', $c->refresh()->acuse_estado);
        foreach (['funcionario', 'admin', 'auditor'] as $rol) { // otra unidad, admin y auditor no acusan
            $this->comoFuncionario($this->usuario($rol, 1))->post("/app/comunicaciones/{$c->id}/acuse")->assertForbidden();
        }
        $this->comoFuncionario($this->usuario('funcionario', 1))->get("/app/expedientes/{$e->id}")->assertForbidden();
    }

    public function test_acuse_manual_en_outbox_exige_referencia(): void
    {
        $this->modo('demo');
        $this->recibir();
        $this->modo('outbox');
        $e = Expediente::query()->where('origen', 'docdigital')->firstOrFail();
        $c = ComunicacionOficial::query()->findOrFail($e->comunicacion_id);
        $sec = $this->usuario('secretario');
        $this->comoFuncionario($sec)->get("/app/expedientes/{$e->id}")->assertOk()->assertSee('acuse de recibo pendiente')->assertSee('Referencia del acuse emitido en DocDigital');
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/acuse", ['evidencia' => ''])->assertSessionHasErrors('evidencia');
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$c->id}/acuse", ['evidencia' => 'Acuse DD-2026-9981'])->assertSessionHas('ok');
        $this->assertSame(['registrado', 'manual', 'Acuse DD-2026-9981'], [$c->refresh()->acuse_estado, $c->acuse_tipo, $c->acuse_evidencia]);
    }

    public function test_acuse_simulado_en_mock_se_rotula(): void
    {
        $this->modo('mock');
        $e = $this->conEntranteMock();
        $c = ComunicacionOficial::query()->findOrFail($e->comunicacion_id);
        $this->comoFuncionario($this->usuario('secretario'))->post("/app/comunicaciones/{$c->id}/acuse")->assertSessionHas('ok');
        $this->assertSame('simulado', $c->refresh()->acuse_tipo);
        $this->comoFuncionario($this->usuario('secretario'))->get("/app/expedientes/{$e->id}")->assertSee('SIMULADO – no enviado realmente');
    }

    private function conEntranteMock(): Expediente
    {
        $unidad = Unidad::query()->where('codigo', 'SECMUN')->first();
        $c = ComunicacionOficial::query()->create(['direccion' => 'entrante', 'institucion_codigo' => 'DEMO-GORE-VAL', 'institucion_nombre' => 'GORE', 'asunto' => 'Entrante mock', 'estado' => 'recibida',
            'evidencia_tipo' => 'docdigital_id', 'evidencia' => 'M-1', 'evidencia_en' => now(), 'unidad_id' => $unidad->id, 'acuse_estado' => 'pendiente']);
        $e = app(ExpedienteService::class)->crearDesdeComunicacion($c, $unidad);
        $c->forceFill(['expediente_id' => $e->id])->save();

        return $e;
    }

    public function test_ciclo_completo_recibir_acusar_gestionar_responder_y_despachar(): void
    {
        $this->modo('demo');
        $this->recibir();
        $e = Expediente::query()->where('origen', 'docdigital')->firstOrFail();
        $sec = $this->usuario('secretario');
        $alc = $this->usuario('alcalde');
        $entrante = ComunicacionOficial::query()->findOrFail($e->comunicacion_id);

        // bandeja de la unidad receptora y asignación
        $this->comoFuncionario($sec)->get('/app')->assertSee($e->codigo)->assertSee('Comunicaciones oficiales recibidas por gestionar');
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/asignarme")->assertSessionHas('ok');
        $this->assertSame($sec->id, $e->refresh()->responsable_id);
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/asignarme")->assertSessionHas('error'); // ya tiene responsable
        $this->comoFuncionario($this->usuario('funcionario', 1))->post("/app/expedientes/{$e->id}/asignarme")->assertForbidden();

        // acuse
        Http::fake(['demodoc.ejemplo.gob.cl/api/v2/acuse' => Http::response(['id' => 'ACUSE-1'], 200)]);
        $this->comoFuncionario($sec)->post("/app/comunicaciones/{$entrante->id}/acuse")->assertSessionHas('ok');

        // gestión interna: derivar a otro funcionario y comentar
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/derivar", ['a_usuario_id' => $this->usuario('funcionario', 3)->id, 'motivo' => 'Solicito opinión técnica'])->assertSessionHas('ok');
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/comentarios", ['texto' => 'Se responderá con decreto', 'tipo' => 'comentario'])->assertSessionHas('ok');

        // respuesta con cualquier tipo documental (decreto alcaldicio con firma del alcalde)
        $ds = app(DocumentoService::class);
        $doc = $ds->crear($e->refresh(), TipoDocumento::query()->where('codigo', 'DEC_ALC')->first(), Plantilla::query()->first(), 'Decreto en respuesta al GORE', $sec);
        $this->assertStringContainsString($e->codigo, $doc->html);
        $ds->definirFlujo($doc, 'agil', [], [$alc->id]);
        $doc = $ds->firmar($doc->refresh(), $alc, '123456');
        $this->assertSame('vigente', $doc->estado);
        $doc->forceFill(['pdf_firmado_ruta' => $doc->pdf_ruta, 'pdf_firmado_sha256' => $doc->pdf_sha256])->save(); // como lo dejaría FirmaGob real

        // despacho: por defecto sugiere la institución de origen; el usuario puede elegir cualquier destinatario
        $html = $this->comoFuncionario($sec)->get("/app/expedientes/{$e->id}")->getContent();
        $this->assertMatchesRegularExpression('/<option value="DEMO-GORE-VAL"\s+selected/', $html);
        Queue::fake();
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/comunicaciones", ['documento_id' => $doc->id, 'institucion_codigo' => 'DEMO-GORE-VAL', 'asunto' => 'Responde Oficio IN-1'])->assertSessionHas('ok');
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/comunicaciones", ['documento_id' => $doc->id, 'institucion_codigo' => 'DEMO-SEGPRES', 'asunto' => 'Con copia a SEGPRES'])->assertSessionHas('ok');
        Queue::assertPushed(EnviarComunicacionDocDigital::class, 2);
        $salientes = ComunicacionOficial::query()->where('direccion', 'saliente')->orderBy('id')->get();
        $this->assertSame([$entrante->id, $entrante->id], $salientes->pluck('responde_a_id')->all());
        $this->assertSame(['DEMO-GORE-VAL', 'DEMO-SEGPRES'], $salientes->pluck('institucion_codigo')->all());

        Http::fake(['demodoc.ejemplo.gob.cl/api/v2/enviar' => Http::sequence()->push(['id' => 'OUT-1'], 200)->push(['id' => 'OUT-2'], 200)]);
        foreach ($salientes as $s) {
            (new EnviarComunicacionDocDigital($s->id))->handle(app(ComunicacionService::class));
        }
        $this->assertSame(['enviada', 'enviada'], ComunicacionOficial::query()->where('direccion', 'saliente')->pluck('estado')->all());
        Http::assertSent(fn ($r) => str_contains($r->url(), '/enviar') && $r['referencia'] === 'IN-1' && in_array($r['destinatarios'][0]['codigo_institucion'], ['DEMO-GORE-VAL', 'DEMO-SEGPRES'], true));

        // cierre y página sin solicitante sigue funcionando; no se notifica a un ciudadano inexistente
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/cerrar")->assertSessionHas('ok');
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/notificar", ['documentos' => [$doc->id], 'canal' => 'correo'])->assertSessionHas('error');
        $this->comoFuncionario($sec)->get("/app/expedientes/{$e->id}")->assertOk()->assertSee('acuse de recibo registrado')->assertSee('en respuesta a la comunicación recibida');
        $this->comoFuncionario($sec)->post("/app/expedientes/{$e->id}/devolver", ['observacion' => 'xxxxx', 'plazo_dias' => 5])->assertSessionHas('error');
        $json = json_decode($this->comoFuncionario($sec)->get("/app/expedientes/{$e->id}/exportar")->getContent(), true);
        $this->assertSame('DEMO-MUN-SD-'.$e->codigo, $json['identificacion']['identificador_unico']);
        $this->assertTrue(AuditoriaService::class === AuditoriaService::class && app(AuditoriaService::class)->verificar()['ok']);
    }

    public function test_el_expediente_de_solicitud_tambien_lleva_identificador_unico(): void
    {
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
        $this->assertSame('DEMO-MUN-SD-'.$e->codigo, $e->metadatos['identificacion']['identificador_unico']);
        $this->assertSame('solicitud', $e->origen);
    }
}
