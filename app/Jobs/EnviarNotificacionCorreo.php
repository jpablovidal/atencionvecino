<?php

namespace App\Jobs;

use App\Mail\NotificacionTramite;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Services\AuditoriaService;
use App\Services\NotificacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mime\Message;

/** Envío idempotente por correo (Gmail API). Si ya hay evidencia no reenvía; si falla queda "fallida" y se reintenta. */
class EnviarNotificacionCorreo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public int $notificacionId) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(NotificacionService $svc, AuditoriaService $audit): void
    {
        $n = Notificacion::query()->find($this->notificacionId);
        if (! $n || $n->estado === 'enviada' || $n->canal !== 'correo') {
            return; // idempotente
        }
        if (! RateLimiter::attempt('gmail-send', (int) config('sgd.google.rate_per_minute'), fn () => true, 60)) {
            $this->release(60); // respeta la cuota de envío

            return;
        }
        $to = (string) $n->destinatario_correo;
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
            $n->forceFill(['estado' => 'fallida', 'ultimo_error' => 'Destinatario inválido'])->save();
            $this->fail(new \InvalidArgumentException('Destinatario inválido'));

            return;
        }
        try {
            $sent = Mail::to($to)->send(new NotificacionTramite(Solicitud::query()->findOrFail($n->solicitud_id)));
            $orig = $sent?->getOriginalMessage();
            $id = $orig instanceof Message ? $orig->getHeaders()->getHeaderBody('X-Gmail-Api-Id') : null;
            if (! is_string($id) || $id === '') {
                throw new \RuntimeException('Sin ID de mensaje de Gmail');
            }
        } catch (\Throwable $e) {
            $n->forceFill(['estado' => 'fallida', 'intentos' => $n->intentos + 1, 'ultimo_error' => 'Fallo de envío por correo'])->save();
            $audit->registrar('notificacion.correo_fallo', 'notificacion', $n->id, ['intento' => $n->intentos], false, ['run' => null, 'rol' => 'sistema']);
            throw $e; // la cola reintenta con backoff
        }
        $svc->marcarEnviada($n, 'gmail_message_id', $id); // evidencia de ENVÍO, no de lectura ni recepción
    }
}
