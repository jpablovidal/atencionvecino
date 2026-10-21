<?php

namespace Tests\Feature;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarNotificacionCorreo;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\Plantilla;
use App\Models\Solicitud;
use App\Models\TipoDocumento;
use App\Services\AuditoriaService;
use App\Services\DocumentoService;
use App\Services\ExpedienteService;
use App\Services\NotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Escenario;
use Tests\TestCase;

class FlujoCompletoTest extends TestCase
{
    use Escenario, RefreshDatabase;

    public function test_flujo_completo_ciudadano_a_notificacion(): void
    {
        $this->sembrar();
        // 1. Ciudadano ingresa por HTTP (solo con sesión de ClaveÚnica)
        $this->comoCiudadano()->post('/ciudadano/solicitudes', [
            'tramite_id' => 1, 'correo' => 'vecina@example.com', 'detalle' => 'Necesito un permiso de edificación.', 'consentimiento' => 'si', 'canal_notificacion' => 'correo',
            'anexo_1' => $this->archivo('planos.pdf'), 'anexo_2' => $this->archivo('dominio.pdf'),
            'run' => '1-9', 'nombres' => 'HACKER', // se ignoran: vienen de ClaveÚnica
        ])->assertRedirect();
        $s = Solicitud::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^SD-\d{4}-[A-Z0-9]{6}$/', $s->folio);
        $this->assertSame('Vecina', $s->nombres);
        $this->assertSame('ingresada', $s->estado);
        // comprobante PDF registrado y descargable
        $r = $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}/comprobante");
        $r->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('%PDF', $r->getContent());

        // 2. Llega a la bandeja de DOM y un funcionario la asigna
        $dom = $this->usuario('funcionario', 1);
        $this->comoFuncionario($dom)->get('/app')->assertOk()->assertSee($s->folio);
        $this->comoFuncionario($this->usuario('funcionario', 3))->get('/app')->assertOk()->assertDontSee($s->folio); // DIDECO no la ve
        $this->comoFuncionario($dom)->post("/app/solicitudes/{$s->folio}/asignar")->assertRedirect();
        $e = $s->refresh()->expediente;
        $this->assertSame('en_tramite', $s->estado);
        $this->assertArrayHasKey('procedimiento', $e->metadatos);

        // 3. Documento: decreto alcaldicio, flujo clásico con 1 visador y 2 firmantes (alcalde + secretaria)
        $ds = app(DocumentoService::class);
        $tipo = TipoDocumento::query()->where('codigo', 'DEC_ALC')->first();
        $doc = $ds->crear($e, $tipo, Plantilla::query()->first(), 'Decreto permiso', $dom);
        $this->assertStringContainsString('Vecina De Prueba', $doc->html);
        $visador = $this->usuario('funcionario', 2);
        $alcalde = $this->usuario('alcalde');
        $secretaria = $this->usuario('secretario');
        $ds->definirFlujo($doc, 'clasico', [$visador->id], [$alcalde->id, $secretaria->id]);
        $ds->visar($doc->refresh(), $visador, true, 'ok');
        $ds->firmar($doc->refresh(), $alcalde, '123456');
        $this->assertSame('en_flujo', $doc->refresh()->estado);
        $doc = $ds->firmar($doc, $secretaria, '654321');

        // 4. Vigente con correlativo, código y copia pública
        $this->assertSame('vigente', $doc->estado);
        $this->assertSame(1, $doc->numero);
        $this->assertSame((int) now('America/Santiago')->year, $doc->anio);
        $this->assertMatchesRegularExpression('/^SDD-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $doc->codigo_validacion);
        $this->get('/validar/'.$doc->codigo_validacion)->assertOk()->assertSee('auténtico')->assertSee('FIRMA SIMULADA');
        $this->assertTrue($doc->pasos->where('tipo', 'firma')->every(fn ($p) => $p->simulada));
        // reservado por defecto => la copia no es pública
        $this->get('/validar/'.$doc->codigo_validacion.'/copia')->assertNotFound();
        $doc->forceFill(['nivel_acceso' => 'publico'])->save();
        $pdf = $this->get('/validar/'.$doc->codigo_validacion.'/copia');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get('/publico')->assertOk()->assertSee('Decreto permiso');
        $doc->forceFill(['sensible' => true])->save();
        $this->get('/publico')->assertDontSee('Decreto permiso'); // sensible: nunca en listado público

        // 5. Cierre y notificación por correo (cola); correo sin adjuntos ni datos sensibles
        $this->assertNotNull(app(ExpedienteService::class)->cerrar($e));
        Queue::fake();
        $ns = app(NotificacionService::class);
        $n = $ns->crear($e->refresh(), [$doc->id], 'correo', $dom)[0];
        Queue::assertPushed(EnviarNotificacionCorreo::class);
        $this->assertSame('pendiente', $n->estado);
        // ciudadano aún no puede descargar (nada enviado)
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}/documentos/{$doc->id}")->assertNotFound();

        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'gmail-abc123'], 200), 'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok'], 200)]);
        config(['mail.default' => 'gmail_api', 'sgd.google.service_account_json' => $this->cuentaServicioFalsa()]);
        app()->forgetInstance('mail.manager');
        (new EnviarNotificacionCorreo($n->id))->handle($ns, app(AuditoriaService::class));
        $n->refresh();
        $this->assertSame('enviada', $n->estado);
        $this->assertSame('gmail_message_id', $n->evidencia_tipo);
        $this->assertSame('gmail-abc123', $n->evidencia);
        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'messages/send')) {
                return false;
            }
            $raw = base64_decode(strtr($req['raw'], '-_', '+/'));

            return str_contains($raw, 'SD-') && ! str_contains($raw, 'Content-Disposition: attachment') && ! str_contains($raw, 'Vecina');
        });
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}/documentos/{$doc->id}")->assertOk();
        $this->assertGreaterThan(10, AuditLog::query()->count());
        $this->assertTrue(app(AuditoriaService::class)->verificar()['ok']);
    }

    private function cuentaServicioFalsa(): string
    {
        $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($k, $pem);
        $path = sys_get_temp_dir().'/sa-test-'.getmypid().'.json';
        file_put_contents($path, json_encode(['client_email' => 'sa@proj.iam.gserviceaccount.com', 'private_key' => $pem]));
        chmod($path, 0600);

        return $path;
    }

    public function test_nada_queda_enviado_sin_evidencia_presencial_y_casilla_outbox(): void
    {
        $this->sembrar();
        $s = $this->solicitudDOM();
        $dom = $this->usuario('funcionario', 1);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $dom);
        $doc = $this->documentoVigente($e, $dom);
        app(ExpedienteService::class)->cerrar($e);
        foreach (['casilla_unica', 'presencial'] as $canal) {
            $n = app(NotificacionService::class)->crear($e->refresh(), [$doc->id], $canal, $dom)[0];
            $this->assertSame('pendiente', $n->refresh()->estado, "$canal debe quedar pendiente (outbox)");
        }
        $n = Notificacion::query()->where('canal', 'presencial')->first();
        app(NotificacionService::class)->registrarEvidencia($n, 'acta_presencial', 'Acta 55/2026');
        $this->assertSame('enviada', $n->refresh()->estado);
        Queue::fake();
        $nc = app(NotificacionService::class)->crear($e, [$doc->id], 'correo', $dom)[0];
        $this->expectException(FlujoException::class); // la evidencia de correo solo la registra el sistema
        app(NotificacionService::class)->registrarEvidencia($nc, 'acta_presencial', 'x');
    }
}
