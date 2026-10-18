<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarAvisoMovimiento;
use App\Models\Archivo;
use App\Models\AuditLog;
use App\Models\AvisoMovimiento;
use App\Models\Expediente;
use App\Models\Notificacion;
use App\Models\RespuestaFinal;
use App\Models\SolicitudMovimiento;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\Casilla\CasillaAdapter;
use App\Services\ExpedienteService;
use App\Services\MovimientoService;
use App\Services\RespuestaFinalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Escenario;
use Tests\TestCase;

class RespuestaFinalTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private Expediente $e;

    private Usuario $dom;

    private Usuario $partes;

    private int $docId;

    private int $archId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        $this->dom = $this->usuario('funcionario', 1);
        $this->partes = Usuario::query()->where('rol', 'oficina_partes')->firstOrFail();
        $this->e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $this->dom);
        $this->docId = $this->documentoVigente($this->e, $this->dom)->id;
        $this->archId = Archivo::query()->where('expediente_id', $this->e->id)->where('ambito', 'anexo')->firstOrFail()->id;
    }

    private function svc(): RespuestaFinalService
    {
        return app(RespuestaFinalService::class);
    }

    private function instruir(string $canal = 'casilla_unica', ?string $motivo = null): RespuestaFinal
    {
        return $this->svc()->instruir($this->dom, $this->e->fresh(), [$this->docId], [$this->archId], $canal, $motivo, 'Entregar con el oficio conductor', true);
    }

    private function falla(callable $fn, string $msg): void
    {
        try {
            $fn();
            $this->fail('debía rechazarse');
        } catch (FlujoException $e) {
            $this->assertStringContainsString($msg, mb_strtolower($e->getMessage()));
        }
    }

    public function test_instruir_exige_confirmacion_documentos_y_motivo_si_es_fisica(): void
    {
        $this->falla(fn () => $this->svc()->instruir($this->dom, $this->e->fresh(), [$this->docId], [], 'casilla_unica', null, null, false), 'confirme');
        $this->falla(fn () => $this->svc()->instruir($this->dom, $this->e->fresh(), [], [], 'casilla_unica', null, null, true), 'al menos un documento');
        $this->falla(fn () => $this->svc()->instruir($this->dom, $this->e->fresh(), [$this->docId], [], 'presencial', 'corto', null, true), 'excepcional');
        $this->falla(fn () => $this->svc()->instruir($this->dom, $this->e->fresh(), [99999], [], 'casilla_unica', null, null, true), 'seleccione solo');
        $this->assertSame(0, RespuestaFinal::query()->count());
        $r = $this->instruir();
        $this->assertSame('instruida', $r->estado);
        $this->assertSame(2, $r->items()->count());
        $this->falla(fn () => $this->instruir(), 'ya hay una respuesta');
    }

    public function test_no_se_entregan_consultas_a_otros_organos_ni_archivos_ajenos(): void
    {
        $x = Archivo::query()->create(['solicitud_id' => $this->e->solicitud_id, 'expediente_id' => $this->e->id, 'ambito' => 'pisee', 'nombre_original' => 'p.json', 'mime' => 'application/json', 'tamano' => 2,
            'sha256' => str_repeat('a', 64), 'ruta' => 'ab/'.str_repeat('c', 38), 'subido_por' => 'x', 'sensible' => true]);
        $this->falla(fn () => $this->svc()->instruir($this->dom, $this->e->fresh(), [], [$x->id], 'casilla_unica', null, null, true), 'seleccione solo');
    }

    public function test_solo_quien_gestiona_instruye_y_solo_oficina_de_partes_despacha(): void
    {
        $this->falla(fn () => $this->svc()->instruir($this->usuario('funcionario', 3), $this->e->fresh(), [$this->docId], [], 'casilla_unica', null, null, true), 'no tiene permiso');
        $this->falla(fn () => $this->svc()->instruir($this->usuario('admin'), $this->e->fresh(), [$this->docId], [], 'casilla_unica', null, null, true), 'no tiene permiso');
        $r = $this->instruir();
        app(ExpedienteService::class)->cerrar($this->e->fresh());
        $this->falla(fn () => $this->svc()->despachar($this->dom, $r, null), 'no tiene permiso');
        auth()->forgetGuards();
        $this->comoFuncionario($this->dom)->get('/app/respuestas')->assertForbidden();
        auth()->forgetGuards();
        $this->comoFuncionario($this->partes)->get('/app/respuestas')->assertOk()->assertSee('Respuestas finales a solicitantes')->assertSee($r->solicitud->folio);
        auth()->forgetGuards();
        $this->comoFuncionario($this->partes)->get("/app/respuestas/{$r->id}")->assertOk()->assertSee('Documentos que debe entregar')->assertSee('Entregar con el oficio conductor');
    }

    public function test_despacho_exige_expediente_cerrado_y_crea_casilla_mas_copia_de_correo(): void
    {
        $r = $this->instruir();
        $this->falla(fn () => $this->svc()->despachar($this->partes, $r, null), 'abierto');
        app(ExpedienteService::class)->cerrar($this->e->fresh());
        Queue::fake();
        $n = $this->svc()->despachar($this->partes, $r->fresh(), null);
        $this->assertCount(3, $n); // 2 elementos por Casilla Única + 1 copia por correo
        $this->assertSame(2, collect($n)->where('canal', 'casilla_unica')->count());
        $copia = collect($n)->firstWhere('canal', 'correo');
        $this->assertSame('vecina@example.com', $copia->destinatario_correo);
        $this->assertNull($copia->documento_id);
        $this->assertSame('despachada', $r->fresh()->estado);
        $this->assertSame('respuesta_despachada', SolicitudMovimiento::query()->latest('id')->value('tipo'));
        $this->falla(fn () => $this->svc()->despachar($this->partes, $r->fresh(), null), 'ya fue despachada');
    }

    public function test_evidencia_habilita_la_descarga_del_ciudadano_solo_de_lo_notificado(): void
    {
        $r = $this->instruir();
        app(ExpedienteService::class)->cerrar($this->e->fresh());
        Queue::fake();
        $this->svc()->despachar($this->partes, $r->fresh(), null);
        $folio = $r->solicitud->folio;
        $nd = Notificacion::query()->where('respuesta_id', $r->id)->whereNotNull('documento_id')->firstOrFail();
        $na = Notificacion::query()->where('respuesta_id', $r->id)->whereNotNull('archivo_id')->firstOrFail();
        // sin evidencia: nada descargable
        $this->comoCiudadano()->get("/ciudadano/solicitudes/$folio/documentos/{$nd->documento_id}")->assertNotFound();
        $this->comoCiudadano()->get("/ciudadano/solicitudes/$folio/archivos/{$na->archivo_id}")->assertNotFound();
        $this->svc()->registrarEvidencia($this->partes, $r->fresh(), $nd, 'CU-100');
        $this->svc()->registrarEvidencia($this->partes, $r->fresh(), $na, 'CU-101');
        $this->comoCiudadano()->get("/ciudadano/solicitudes/$folio/documentos/{$nd->documento_id}")->assertOk();
        $this->comoCiudadano()->get("/ciudadano/solicitudes/$folio/archivos/{$na->archivo_id}")->assertOk();
        $otra = ['run' => '20000002-1', 'nombres' => 'Otra', 'apellidos' => 'Persona'];
        $this->withSession(['ciudadano' => $otra, 'login_at' => time(), 'last_seen' => time()])->get("/ciudadano/solicitudes/$folio/archivos/{$na->archivo_id}")->assertNotFound();
        $this->assertSame('en_curso', $r->fresh()->entrega()); // falta la copia por correo
    }

    public function test_entrega_fisica_exige_acta_y_queda_con_evidencia(): void
    {
        $r = $this->instruir('presencial', 'La persona no tiene Casilla Única ni correo válido');
        app(ExpedienteService::class)->cerrar($this->e->fresh());
        $this->falla(fn () => $this->svc()->despachar($this->partes, $r->fresh(), 'corta'), 'acta');
        Queue::fake();
        $n = $this->svc()->despachar($this->partes, $r->fresh(), 'Retiró Vecina De Prueba el 18-10 a las 10:30');
        $this->assertCount(2, $n);
        $this->assertTrue(collect($n)->every(fn ($x) => $x->canal === 'presencial' && $x->fresh()->estado === 'enviada' && $x->fresh()->evidencia_tipo === 'acta_presencial'));
        $this->assertSame('entregada', $r->fresh()->entrega());
        Queue::assertNothingPushed();
    }

    public function test_anular_solo_si_no_se_despacho(): void
    {
        $r = $this->instruir();
        $this->falla(fn () => $this->svc()->anular($this->usuario('funcionario', 3), $r, 'No corresponde a su unidad'), 'no tiene permiso');
        $this->svc()->anular($this->dom, $r, 'Falta un documento');
        $this->assertSame('anulada', $r->fresh()->estado);
        $r2 = $this->instruir();
        app(ExpedienteService::class)->cerrar($this->e->fresh());
        Queue::fake();
        $this->svc()->despachar($this->partes, $r2->fresh(), null);
        $this->falla(fn () => $this->svc()->anular($this->partes, $r2->fresh(), 'Ya despachada'), 'aún no se despacharon');
    }

    public function test_formularios_http_de_instruccion_y_despacho(): void
    {
        $this->comoFuncionario($this->dom)->post("/app/expedientes/{$this->e->id}/respuesta-final", ['documentos' => [$this->docId], 'archivos' => [$this->archId], 'canal' => 'casilla_unica'])->assertSessionHas('error');
        auth()->forgetGuards();
        $this->comoFuncionario($this->dom)->post("/app/expedientes/{$this->e->id}/respuesta-final", ['documentos' => [$this->docId], 'archivos' => [$this->archId], 'canal' => 'casilla_unica', 'confirmo' => 1, 'instruccion' => '<b>Con oficio</b>'])->assertSessionHas('ok');
        $r = RespuestaFinal::query()->firstOrFail();
        $this->assertStringNotContainsString('<', (string) $r->instruccion);
        auth()->forgetGuards();
        $this->comoFuncionario($this->dom)->get("/app/expedientes/{$this->e->id}")->assertOk()->assertSee('Respuesta final al solicitante')->assertSee('Respuesta #'.$r->id);
        auth()->forgetGuards();
        $this->comoFuncionario($this->usuario('auditor'))->post("/app/respuestas/{$r->id}/despachar")->assertForbidden();
        auth()->forgetGuards();
        $this->comoFuncionario($this->partes)->get("/app/respuestas/{$r->id}/items/{$r->items()->first()->id}")->assertOk();
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('accion', 'respuesta.descarga')->count());
    }

    // ---------------------------------------------------------------- movimientos y avisos
    public function test_el_ciudadano_ve_solicitud_expediente_movimientos_y_direccion(): void
    {
        $s = $this->e->solicitud;
        $tipos = SolicitudMovimiento::query()->where('solicitud_id', $s->id)->orderBy('id')->pluck('tipo')->all();
        $this->assertSame(['ingreso', 'expediente'], $tipos);
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}")->assertOk()
            ->assertSee('Solicitud N°', false)->assertSee($s->folio)->assertSee('Expediente N°', false)->assertSee($this->e->codigo)
            ->assertSee('Dirección responsable')->assertSee('Dirección de Obras Municipales')->assertSee('Movimientos de su solicitud')->assertSee('Se abrió el expediente N° '.$this->e->codigo);
        $this->comoCiudadano()->get('/ciudadano')->assertOk()->assertSee('Expediente N°', false)->assertSee($this->e->codigo);
    }

    public function test_cada_movimiento_se_registra_con_su_direccion(): void
    {
        $this->instruir();
        app(ExpedienteService::class)->cerrar($this->e->fresh());
        app(ExpedienteService::class)->reabrir($this->e->fresh(), 'Falta un antecedente');
        $tipos = SolicitudMovimiento::query()->orderBy('id')->pluck('tipo')->all();
        $this->assertSame(['ingreso', 'expediente', 'respuesta_instruida', 'resuelta', 'reabierta'], $tipos);
        $this->assertSame(3, (int) SolicitudMovimiento::query()->where('tipo', 'resuelta')->value('unidad_id'));
    }

    public function test_con_avisos_activos_cada_movimiento_genera_casilla_y_correo(): void
    {
        config(['sgd.avisos.movimientos' => true]);
        Queue::fake();
        $s = $this->e->solicitud;
        $m = app(MovimientoService::class)->registrar($s->fresh(), 'prorroga', 'Plazo prorrogado');
        $this->assertSame(['casilla_unica', 'correo'], AvisoMovimiento::query()->where('movimiento_id', $m->id)->orderBy('canal')->pluck('canal')->all());
        Queue::assertPushed(EnviarAvisoMovimiento::class, 2);
    }

    public function test_aviso_casilla_en_modo_api_y_correo_gmail_guardan_evidencia(): void
    {
        $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($k, $pem);
        $sa = sys_get_temp_dir().'/sa-rf-'.getmypid().'.json';
        file_put_contents($sa, json_encode(['client_email' => 'sa@proj.iam.gserviceaccount.com', 'private_key' => $pem]));
        chmod($sa, 0600);
        config(['mail.default' => 'gmail_api', 'sgd.google.service_account_json' => $sa, 'sgd.google.sender' => 'no-responder@santodomingo.cl', 'sgd.avisos.movimientos' => true,
            'sgd.casilla.mode' => 'api', 'sgd.casilla.api_url' => 'https://casilla.ejemplo.gob.cl/notificar', 'sgd.casilla.api_token' => 'TOK']);
        Cache::flush();
        RateLimiter::clear('gmail-send');
        app()->forgetInstance('mail.manager');
        app()->forgetInstance(CasillaAdapter::class);
        Mail::purge();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok-1'], 200), 'gmail.googleapis.com/*' => Http::response(['id' => 'MSG-1'], 200), 'casilla.ejemplo.gob.cl/*' => Http::response(['id' => 'CU-9'], 200)]);
        $m = app(MovimientoService::class)->registrar($this->e->solicitud->fresh(), 'prorroga', 'Plazo prorrogado 10 dias');
        $av = AvisoMovimiento::query()->where('movimiento_id', $m->id)->get()->keyBy('canal');
        $this->assertSame('enviada', $av['casilla_unica']->fresh()->estado);
        $this->assertSame('CU-9', $av['casilla_unica']->fresh()->evidencia);
        $this->assertSame('enviada', $av['correo']->fresh()->estado);
        $this->assertSame('MSG-1', $av['correo']->fresh()->evidencia);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'casilla.ejemplo.gob.cl') && $r['run'] === '20000001-3' && $r['mensaje'] === 'Plazo prorrogado 10 dias');
        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'gmail.googleapis.com')) {
                return false;
            }
            $raw = base64_decode(strtr((string) $r['raw'], '-_', '+/'));

            return str_contains($raw, 'To: vecina@example.com') && str_contains($raw, 'Plazo prorrogado 10 dias') && ! str_contains($raw, 'Content-Disposition: attachment');
        });
        @unlink($sa);
    }

    public function test_el_correo_del_aviso_no_adjunta_documentos_ni_revela_datos_personales(): void
    {
        $m = SolicitudMovimiento::query()->latest('id')->firstOrFail();
        $mail = new \App\Mail\AvisoMovimiento($this->e->solicitud->fresh()->load('expediente'), $m);
        $html = $mail->render();
        $this->assertStringContainsString($this->e->codigo, $html);
        $this->assertStringNotContainsString('20000001', $html);
        $this->assertSame([], $mail->attachments());
    }

    public function test_un_fallo_del_aviso_nunca_detiene_el_tramite(): void
    {
        config(['sgd.avisos.movimientos' => true, 'mail.default' => 'array']);
        // correo sin ID de Gmail (mailer de prueba) → el aviso queda fallido, pero el movimiento y el flujo se completan
        $m = app(MovimientoService::class)->registrar($this->e->solicitud->fresh(), 'prorroga', 'Plazo prorrogado');
        $this->assertNotNull($m->id);
        $this->assertSame('pendiente', AvisoMovimiento::query()->where('movimiento_id', $m->id)->where('canal', 'casilla_unica')->value('estado')); // outbox
        $this->assertSame('fallida', AvisoMovimiento::query()->where('movimiento_id', $m->id)->where('canal', 'correo')->value('estado'));
        $this->assertInstanceOf(AuditoriaService::class, app(AuditoriaService::class));
    }

    public function test_panel_casilla_lista_avisos_y_permite_registrar_el_acuse(): void
    {
        config(['sgd.avisos.movimientos' => true]);
        Queue::fake();
        $m = app(MovimientoService::class)->registrar($this->e->solicitud->fresh(), 'prorroga', 'Plazo prorrogado');
        $a = AvisoMovimiento::query()->where('movimiento_id', $m->id)->where('canal', 'casilla_unica')->firstOrFail();
        $sec = $this->usuario('secretario');
        $this->comoFuncionario($sec)->get('/app/casilla')->assertOk()->assertSee('Avisos de movimientos por enviar')->assertSee('Plazo prorrogado');
        $this->comoFuncionario($sec)->post("/app/casilla/avisos/{$a->id}/evidencia", ['valor' => 'CU-55'])->assertSessionHas('ok');
        $this->assertSame('enviada', $a->fresh()->estado);
        auth()->forgetGuards();
        $this->comoFuncionario($this->dom)->post("/app/casilla/avisos/{$a->id}/evidencia", ['valor' => 'CU-56'])->assertForbidden();
    }
}
