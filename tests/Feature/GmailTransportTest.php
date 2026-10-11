<?php

namespace Tests\Feature;

use App\Jobs\EnviarNotificacionCorreo;
use App\Mail\GmailApiTransport;
use App\Mail\NotificacionTramite;
use App\Models\Notificacion;
use App\Services\AuditoriaService;
use App\Services\ExpedienteService;
use App\Services\NotificacionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\Support\Escenario;
use Tests\TestCase;

class GmailTransportTest extends TestCase
{
    use Escenario, RefreshDatabase;

    private string $saPath;

    protected function setUp(): void
    {
        parent::setUp();
        $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($k, $pem);
        $this->saPath = sys_get_temp_dir().'/sa-'.getmypid().'.json';
        file_put_contents($this->saPath, json_encode(['client_email' => 'sa@proj.iam.gserviceaccount.com', 'private_key' => $pem]));
        chmod($this->saPath, 0600);
        config(['mail.default' => 'gmail_api', 'sgd.google.service_account_json' => $this->saPath, 'sgd.google.sender' => 'no-responder@santodomingo.cl']);
        Cache::flush();
        RateLimiter::clear('gmail-send');
        app()->forgetInstance('mail.manager');
        Mail::purge();
    }

    protected function tearDown(): void
    {
        @unlink($this->saPath);
        parent::tearDown();
    }

    private function notificacionCorreo(): Notificacion
    {
        $this->sembrar();
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($this->solicitudDOM(), $dom);
        $doc = $this->documentoVigente($e, $dom);
        app(ExpedienteService::class)->cerrar($e);
        Queue::fake();

        return app(NotificacionService::class)->crear($e->refresh(), [$doc->id], 'correo', $dom)[0];
    }

    private function correr(Notificacion $n): void
    {
        (new EnviarNotificacionCorreo($n->id))->handle(app(NotificacionService::class), app(AuditoriaService::class));
    }

    public function test_envio_exitoso_usa_delegacion_con_scope_minimo_y_guarda_id(): void
    {
        $n = $this->notificacionCorreo();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok-1'], 200), 'gmail.googleapis.com/*' => Http::response(['id' => 'MSG-777', 'threadId' => 't'], 200)]);
        $this->correr($n);
        $this->assertSame('enviada', $n->refresh()->estado);
        $this->assertSame('MSG-777', $n->evidencia);
        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'oauth2.googleapis.com')) {
                return true;
            }
            [, $p] = explode('.', $req['assertion']);
            $claims = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
            $this->assertSame('https://www.googleapis.com/auth/gmail.send', $claims['scope']); // único scope
            $this->assertSame('no-responder@santodomingo.cl', $claims['sub']); // suplanta solo el buzón dedicado
            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $req['grant_type']);

            return true;
        });
        Http::assertSent(fn ($r) => str_contains($r->url(), 'messages/send') && $r->hasHeader('Authorization', 'Bearer tok-1'));
    }

    public function test_fallo_deja_notificacion_fallida_y_reintento_idempotente(): void
    {
        $n = $this->notificacionCorreo();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok'], 200), 'gmail.googleapis.com/*' => Http::sequence()->push(['error' => 'x'], 500)->push(['id' => 'OK-1'], 200)]);
        try {
            $this->correr($n);
            $this->fail('debe propagar para que la cola reintente');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $n->refresh();
        $this->assertSame('fallida', $n->estado, 'nunca "enviada" si falló');
        $this->assertNull($n->evidencia);
        $this->assertSame(1, $n->intentos);
        $this->correr($n); // reintento
        $this->assertSame('enviada', $n->refresh()->estado);
        $this->assertSame('OK-1', $n->evidencia);
        $this->correr($n); // idempotente: no reenvía
        Http::assertSentCount(3); // token + 500 + OK (el token se cachea; el 3.º no envía)
    }

    public function test_si_gmail_no_devuelve_id_no_se_marca_enviada(): void
    {
        $n = $this->notificacionCorreo();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok'], 200), 'gmail.googleapis.com/*' => Http::response([], 200)]);
        try {
            $this->correr($n);
        } catch (\Throwable) {
        }
        $this->assertSame('fallida', $n->refresh()->estado);
    }

    public function test_rechaza_inyeccion_de_cabeceras_en_asunto_y_destinatario(): void
    {
        $t = new GmailApiTransport(config('sgd.google'));
        Http::fake();
        foreach ([
            (new Email)->from('a@b.cl')->to('victima@x.cl')->subject("Hola\r\nBcc: espia@evil.cl")->text('x'),
            (new Email)->from('a@b.cl')->to('victima@x.cl')->subject("Hola\nBcc: espia@evil.cl")->text('x'),
        ] as $mail) {
            try {
                $t->send($mail);
                $this->fail('debió rechazar el asunto');
            } catch (\Throwable $e) {
                $this->assertInstanceOf(TransportException::class, $e->getPrevious() ?? $e);
            }
        }
        Http::assertNothingSent();
        $n = $this->notificacionCorreo();
        $n->forceFill(['destinatario_correo' => "victima@x.cl\r\nBcc: espia@evil.cl"])->save();
        $this->correr($n);
        $this->assertSame('fallida', $n->refresh()->estado);
        Http::assertNothingSent();
    }

    public function test_correo_sin_adjuntos_ni_datos_sensibles(): void
    {
        $this->sembrar();
        $s = $this->solicitudDOM();
        $m = (new NotificacionTramite($s))->render();
        $this->assertStringContainsString($s->folio, $m);
        $this->assertStringContainsString(route('ciudadano.solicitud', $s->folio), $m);
        $this->assertStringNotContainsString($s->run, $m);
        $this->assertStringNotContainsString($s->detalle, $m);
        $this->assertSame([], (new NotificacionTramite($s))->attachments());
    }

    public function test_la_clave_de_la_cuenta_de_servicio_no_se_filtra_en_errores(): void
    {
        $n = $this->notificacionCorreo();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'unauthorized_client'], 401)]);
        try {
            $this->correr($n);
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString('PRIVATE KEY', $e->getMessage());
            $this->assertStringNotContainsString('private_key', $e->getMessage());
        }
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode(DB::table('audit_log')->get()));
        $this->assertSame('fallida', $n->refresh()->estado);
    }

    public function test_respeta_cuota_de_envio_reencolando(): void
    {
        $n = $this->notificacionCorreo();
        config(['sgd.google.rate_per_minute' => 1]);
        RateLimiter::clear('gmail-send');
        RateLimiter::hit('gmail-send', 60);
        Http::fake();
        $job = new EnviarNotificacionCorreo($n->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(NotificacionService::class), app(AuditoriaService::class));
        $job->assertReleased(60);
        Http::assertNothingSent();
        $this->assertSame('pendiente', $n->refresh()->estado);
    }

    public function test_el_job_se_encola_en_la_cola_database(): void
    {
        config(['queue.default' => 'database']);
        $this->assertSame('database', config('queue.default'));
        $this->assertTrue(in_array(ShouldQueue::class, class_implements(EnviarNotificacionCorreo::class)));
    }
}
