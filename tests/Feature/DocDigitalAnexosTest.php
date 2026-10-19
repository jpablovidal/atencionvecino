<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\ComunicacionAnexo;
use App\Models\ComunicacionOficial;
use App\Models\Expediente;
use App\Models\Tramite;
use App\Services\AccesoRestringidoService;
use App\Services\Admin\ArchivoConfigService;
use App\Services\ExpedienteService;
use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use App\Services\SolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DocDigitalApi;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Envío y recepción de anexos por DocDigital v3 (documentos_anexos / archivo_id de la especificación). */
class DocDigitalAnexosTest extends TestCase
{
    use DocDigitalApi, Escenario, RefreshDatabase;

    private Expediente $e;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        ArchivoConfigService::olvidar();
        $this->ddConfig();
        $this->e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->usuario('funcionario', 1));
    }

    private function cliente(): HttpDocDigitalClient
    {
        return new HttpDocDigitalClient(config('sgd.docdigital'), 'demo');
    }

    private function meta(array $x = []): array
    {
        return $x + ['asunto' => 'Remite', 'destino_codigo' => 'x', 'destino_entidad_id' => 7002, 'origen_codigo' => null, 'numero' => '1', 'tipo' => 'Oficio'];
    }

    // ---------------------------------------------------------------- cliente

    public function test_el_envio_incluye_los_anexos_segun_el_esquema_de_la_especificacion(): void
    {
        $esq = json_decode((string) file_get_contents(base_path('docs/interop/docdigital-openapi-3.2.514.json')), true)['components']['schemas']['DocumentosAnexosRequest'];
        $this->ddFake([], [], [$this->dd.'/documentos/firmado/ingresar' => Http::response($this->ddOk(['id_documento' => 1, 'id_solicitud' => 2, 'anexos' => [['id_documento_anexo' => 'AX-1', 'nombre_anexo' => 'plano.pdf'], ['id_documento_anexo' => 'AX-2', 'nombre_anexo' => 'foto.png']]]), 200)]);
        $r = $this->cliente()->enviar($this->pdfValido(), 'o.pdf', $this->meta(['anexos' => [['nombre' => 'plano.pdf', 'bytes' => $this->pdfValido('a')], ['nombre' => 'foto.png', 'bytes' => "\x89PNG\r\n\x1A\nxx"]]]));
        $this->assertSame([['id' => 'AX-1', 'nombre' => 'plano.pdf'], ['id' => 'AX-2', 'nombre' => 'foto.png']], $r['anexos']);
        Http::assertSent(function ($req) use ($esq) {
            $ax = $req['documentos_anexos'];
            $this->assertCount(2, $ax);
            foreach ($ax as $a) {
                $this->assertSame([], array_values(array_diff(array_keys($a), array_keys($esq['properties']))));
                $this->assertSame('anexo', $a['tipo']);
            }
            $this->assertStringStartsWith('%PDF', base64_decode($ax[0]['contenido']));
            $this->assertSame('plano.pdf', $ax[0]['nombre_archivo']);

            return true;
        });
    }

    public function test_sin_anexos_no_se_envia_la_clave(): void
    {
        $this->ddFake();
        $this->cliente()->enviar($this->pdfValido(), 'o.pdf', $this->meta());
        Http::assertSent(fn ($r) => ! array_key_exists('documentos_anexos', $r->data()));
    }

    public function test_limite_de_cantidad_y_de_tamano_de_anexos(): void
    {
        Http::fake();
        config(['sgd.docdigital.max_anexos' => 2]);
        $tres = array_map(fn ($i) => ['nombre' => "a$i.pdf", 'bytes' => 'x'], [1, 2, 3]);
        foreach ([$tres, [['nombre' => 'grande.pdf', 'bytes' => str_repeat('A', 50 * 1024 * 1024 + 1)]]] as $caso) {
            try {
                $this->cliente()->enviar($this->pdfValido(), 'o.pdf', $this->meta(['anexos' => $caso]));
                $this->fail('debía rechazarse');
            } catch (DocDigitalException) {
            }
        }
        Http::assertNothingSent();
    }

    public function test_descarga_de_anexo_con_archivo_id_e_ids_seguros(): void
    {
        $this->ddFake([7 => 'Doc'], [], [], [7 => ['AB-1' => ['nombre' => 'x.pdf', 'bytes' => $this->pdfValido('x')]]]);
        $this->assertStringStartsWith('%PDF', (string) $this->cliente()->descargarAnexo('7', 'AB-1'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/documentos/7/archivo/descargar') && ($r['archivo_id'] ?? null) === 'AB-1');
        $this->assertNull($this->cliente()->descargarAnexo('7', 'NO-EXISTE'));
        foreach ([['7', '../x'], ['7', 'a/b'], ['7', ''], ['7/8', 'A']] as [$d, $a]) {
            try {
                $this->cliente()->descargarAnexo($d, $a);
                $this->fail("id inválido aceptado: $d $a");
            } catch (DocDigitalException) {
            }
        }
    }

    public function test_los_anexos_informados_no_usan_la_url_de_la_respuesta(): void
    {
        $this->ddFake([8 => 'Con anexo'], [], [], [8 => ['Z9' => ['nombre' => 'z.pdf', 'bytes' => $this->pdfValido('z')]]]);
        $r = $this->cliente()->recibidos();
        $this->assertSame([['id' => 'Z9', 'nombre' => 'z.pdf', 'tipo' => 'anexo']], $r[0]['anexos']);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'evil.example')); // nunca se visita una URL entregada por el servicio
    }

    // ---------------------------------------------------------------- envío

    private function docFirmado()
    {
        $d = $this->documentoVigente($this->e, $this->usuario('funcionario', 1));
        $d->forceFill(['pdf_firmado_ruta' => $d->pdf_ruta, 'pdf_firmado_sha256' => $d->pdf_sha256])->save();

        return $d->refresh();
    }

    private function anexables(): array
    {
        return Archivo::query()->where('expediente_id', $this->e->id)->where('ambito', 'anexo')->pluck('id')->all();
    }

    public function test_se_envian_archivos_del_expediente_como_anexos_y_se_guarda_el_id_externo(): void
    {
        Queue::fake();
        $u = $this->usuario('funcionario', 1);
        $ids = $this->anexables();
        $this->assertGreaterThanOrEqual(2, count($ids));
        $c = app(ComunicacionService::class)->crearSaliente($this->e, $this->docFirmado(), 'DEMO-SEGPRES', 'Remite con anexos', $u, $ids);
        $this->assertSame(count($ids), ComunicacionAnexo::query()->where('comunicacion_id', $c->id)->where('estado', 'pendiente')->count());
        $this->ddFake([], [], [$this->dd.'/documentos/firmado/ingresar' => Http::response($this->ddOk(['id_documento' => 10, 'id_solicitud' => 1, 'anexos' => [['id_documento_anexo' => 'X-1', 'nombre_anexo' => Archivo::query()->find($ids[0])->nombre_original]]]), 200)]);
        app(ComunicacionService::class)->enviar($c);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/documentos/firmado/ingresar') && count($r['documentos_anexos']) === count($ids) && str_starts_with(base64_decode($r['documentos_anexos'][0]['contenido']), '%PDF'));
        $filas = ComunicacionAnexo::query()->where('comunicacion_id', $c->id)->orderBy('id')->get();
        $this->assertTrue($filas->every(fn ($f) => $f->estado === 'enviado'));
        $this->assertSame('X-1', $filas[0]->id_externo);
        $this->comoFuncionario($u)->get("/app/expedientes/{$this->e->id}")->assertOk()->assertSee('Anexos:')->assertSee($filas[0]->nombre);
    }

    public function test_validaciones_de_los_anexos_a_enviar(): void
    {
        Queue::fake();
        $u = $this->usuario('funcionario', 1);
        $svc = app(ComunicacionService::class);
        $doc = $this->docFirmado();
        $ids = $this->anexables();
        // archivo de otro expediente
        $otro = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM2(), $this->usuario('funcionario', 2));
        $ajeno = Archivo::query()->where('expediente_id', $otro->id)->where('ambito', 'anexo')->value('id');
        // comprobante
        $comprobante = Archivo::query()->where('expediente_id', $this->e->id)->where('ambito', 'comprobante')->value('id');
        foreach ([[$ajeno], [$comprobante ?? 999999], [999999]] as $caso) {
            try {
                $svc->crearSaliente($this->e, $doc, 'DEMO-SEGPRES', 'x asunto', $u, $caso);
                $this->fail('debía rechazar '.json_encode($caso));
            } catch (FlujoException $e) {
                $this->assertStringContainsString('no pertenece', $e->getMessage());
            }
        }
        // máximo de anexos
        config(['sgd.docdigital.max_anexos' => 1]);
        try {
            $svc->crearSaliente($this->e, $doc, 'DEMO-SEGPRES', 'x asunto', $u, $ids);
            $this->fail('debía exigir el máximo');
        } catch (FlujoException $e) {
            $this->assertStringContainsString('hasta 1', $e->getMessage());
        }
        // restringido sin autorización
        config(['sgd.docdigital.max_anexos' => 10]);
        app(AccesoRestringidoService::class)->restringir($u, $this->e, 'Contiene datos personales sensibles del solicitante', Archivo::query()->find($ids[0]));
        try {
            $svc->crearSaliente($this->e, $doc, 'DEMO-SEGPRES', 'x asunto', $this->usuario('funcionario', 2), [$ids[0]]);
            $this->fail('debía exigir autorización');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('restringido', $e->getMessage());
        }
        $this->assertSame(0, ComunicacionOficial::query()->count(), 'nada queda a medias');
    }

    private function solicitudDOM2()
    {
        $t = Tramite::query()->with('anexos')->findOrFail(1);

        return app(SolicitudService::class)->ingresar(['run' => '20000002-1', 'nombres' => 'Otra', 'apellidos' => 'Persona'], $t, 'o@example.com', 'Otra solicitud para probar anexos ajenos.', 'correo',
            [1 => $this->archivo('p.pdf', $this->pdfValido('p2')), 2 => $this->archivo('d.pdf', $this->pdfValido('d2'))], []);
    }

    public function test_en_outbox_los_anexos_se_marcan_enviados_al_registrar_la_evidencia(): void
    {
        $this->ddConfig('outbox');
        $u = $this->usuario('funcionario', 1);
        $svc = app(ComunicacionService::class);
        $c = $svc->crearSaliente($this->e, $this->docFirmado(), 'DEMO-SEGPRES', 'Remite', $u, $this->anexables());
        $svc->registrarEvidencia($c, 'DD-2026-5');
        $this->assertTrue(ComunicacionAnexo::query()->where('comunicacion_id', $c->id)->get()->every(fn ($f) => $f->estado === 'enviado'));
    }

    // ---------------------------------------------------------------- recepción

    public function test_los_anexos_recibidos_se_guardan_cifrados_y_lo_no_permitido_se_omite_con_motivo(): void
    {
        $this->ddFake([601 => 'Oficio con anexos'], [601 => $this->pdfValido('p')], [], [601 => [
            'A1' => ['nombre' => 'plano.pdf', 'bytes' => $this->pdfValido('plano')],
            'A2' => ['nombre' => 'virus.exe', 'bytes' => "MZ\x90\x00 malware"],
            'A3' => ['nombre' => 'activo.pdf', 'bytes' => "%PDF-1.4\n<</JS(x)>>\n%%EOF"],
            'A4' => ['nombre' => 'enlace', 'tipo' => 'url', 'bytes' => null],
            'A5' => ['nombre' => 'caido.pdf', 'bytes' => null],
            'A6' => ['nombre' => 'planilla.csv', 'bytes' => "a;b\n1;2"], // CSV viene deshabilitado por defecto
        ]]);
        $res = app(ComunicacionService::class)->sincronizarRecibidos();
        $this->assertSame(1, $res['nuevas']);
        $c = ComunicacionOficial::query()->where('direccion', 'entrante')->firstOrFail();
        $filas = ComunicacionAnexo::query()->where('comunicacion_id', $c->id)->get()->keyBy('id_externo');
        $this->assertSame('recibido', $filas['A1']->estado);
        foreach (['A2', 'A3', 'A4', 'A5', 'A6'] as $k) {
            $this->assertSame('omitido', $filas[$k]->estado, $k);
            $this->assertNull($filas[$k]->archivo_id);
            $this->assertNotEmpty($filas[$k]->motivo);
        }
        $this->assertStringContainsString('enlace externo', (string) $filas['A4']->motivo);
        $this->assertStringContainsString('no está habilitado', (string) $filas['A6']->motivo);
        // el anexo aceptado queda en el expediente, cifrado y con su ámbito propio
        $a = Archivo::query()->findOrFail($filas['A1']->archivo_id);
        $this->assertSame([$c->expediente_id, 'dd_anexo', 'application/pdf'], [$a->expediente_id, $a->ambito, $a->mime]);
        $this->assertStringNotContainsString('%PDF', (string) file_get_contents(config('sgd.storage_path').'/'.$a->ruta));
        $this->assertSame(1, Archivo::query()->where('ambito', 'dd_anexo')->count());
        $this->assertDatabaseHas('audit_log', ['accion' => 'docdigital.anexo_rechazado']);
        // idempotente
        $this->assertSame(0, app(ComunicacionService::class)->sincronizarRecibidos()['nuevas']);
        $this->assertSame(6, ComunicacionAnexo::query()->count());
        // visible en el expediente (modal) y con el motivo de lo omitido
        $this->comoFuncionario($this->usuario('secretario'))->get("/app/expedientes/{$c->expediente_id}")->assertOk()->assertSee('Anexos recibidos')->assertSee('plano.pdf')
            ->assertSee('no se guardó')->assertSee('enlace externo');
    }

    public function test_el_maximo_de_anexos_recibidos_se_respeta(): void
    {
        config(['sgd.docdigital.max_anexos' => 2]);
        $ax = [];
        foreach (['B1', 'B2', 'B3'] as $id) {
            $ax[$id] = ['nombre' => "$id.pdf", 'bytes' => $this->pdfValido($id)];
        }
        $this->ddFake([602 => 'Muchos anexos'], [], [], [602 => $ax]);
        app(ComunicacionService::class)->sincronizarRecibidos();
        $filas = ComunicacionAnexo::query()->orderBy('id')->get();
        $this->assertSame(['recibido', 'recibido', 'omitido'], $filas->pluck('estado')->all());
        $this->assertStringContainsString('máximo de 2', (string) $filas[2]->motivo);
    }

    public function test_si_falla_la_creacion_no_quedan_archivos_de_anexos_a_medias(): void
    {
        $this->ddFake([603 => 'Falla'], [], [], [603 => ['C1' => ['nombre' => 'c.pdf', 'bytes' => $this->pdfValido('c')]]]);
        $mock = $this->partialMock(ExpedienteService::class, fn ($m) => $m->shouldReceive('crearDesdeComunicacion')->andThrow(new \RuntimeException('boom')));
        $this->app->instance(ExpedienteService::class, $mock);
        $this->app->forgetInstance(ComunicacionService::class);
        try {
            app(ComunicacionService::class)->sincronizarRecibidos();
            $this->fail('debe propagar');
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, ComunicacionAnexo::query()->count());
        $this->assertSame(0, Archivo::query()->where('ambito', 'dd_anexo')->count());
    }
}
