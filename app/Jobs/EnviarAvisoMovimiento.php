<?php

namespace App\Jobs;

use App\Mail\AvisoMovimiento as AvisoMail;
use App\Models\AvisoMovimiento;
use App\Models\Solicitud;
use App\Services\AuditoriaService;
use App\Services\Casilla\CasillaAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mime\Message;

/**
 * Aviso de un movimiento al ciudadano. Idempotente: si ya hay evidencia no reenvía. Casilla Única en modo «outbox» queda pendiente
 * (no se afirma envío sin evidencia); el correo (Gmail API) guarda el ID de mensaje como evidencia de ENVÍO, no de lectura.
 */
class EnviarAvisoMovimiento implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public int $avisoId) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(CasillaAdapter $casilla, AuditoriaService $audit): void
    {
        $a = AvisoMovimiento::query()->with('movimiento')->find($this->avisoId);
        if (! $a || $a->estado === 'enviada') {
            return;
        }
        $m = $a->movimiento;
        $s = Solicitud::query()->with('expediente')->find($m->solicitud_id);
        if (! $s) {
            return;
        }
        if ($a->canal === 'casilla_unica') {
            $this->casilla($a, $s, $casilla, $m->descripcion);

            return;
        }
        $this->correo($a, $s, $m, $audit);
    }

    private function casilla(AvisoMovimiento $a, Solicitud $s, CasillaAdapter $casilla, string $texto): void
    {
        if (! $casilla->modoApi()) {
            return; // outbox: queda pendiente hasta registrar la evidencia real
        }
        try {
            $ev = $casilla->enviarAviso($s->run, $s->folio, 'Actualización de su solicitud '.$s->folio, $texto);
        } catch (\Throwable) {
            $a->forceFill(['estado' => 'fallida', 'intentos' => $a->intentos + 1, 'ultimo_error' => 'Casilla Única no confirmó el envío'])->save();
            throw new \RuntimeException('Casilla Única no confirmó el envío.');
        }
        $a->forceFill(['estado' => 'enviada', 'evidencia_tipo' => $ev['evidencia_tipo'], 'evidencia' => $ev['evidencia'], 'evidencia_en' => now(), 'ultimo_error' => null])->save();
    }

    private function correo(AvisoMovimiento $a, Solicitud $s, $m, AuditoriaService $audit): void
    {
        $to = (string) $s->correo;
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
            $a->forceFill(['estado' => 'fallida', 'ultimo_error' => 'Destinatario inválido'])->save();

            return;
        }
        if (! RateLimiter::attempt('gmail-send', (int) config('sgd.google.rate_per_minute'), fn () => true, 60)) {
            $this->release(60);

            return;
        }
        try {
            $sent = Mail::to($to)->send(new AvisoMail($s, $m));
            $orig = $sent?->getOriginalMessage();
            $id = $orig instanceof Message ? $orig->getHeaders()->getHeaderBody('X-Gmail-Api-Id') : null;
            if (! is_string($id) || $id === '') {
                throw new \RuntimeException('Sin ID de mensaje de Gmail');
            }
        } catch (\Throwable $e) {
            $a->forceFill(['estado' => 'fallida', 'intentos' => $a->intentos + 1, 'ultimo_error' => 'Fallo de envío por correo'])->save();
            $audit->registrar('aviso.correo_fallo', 'aviso', $a->id, ['intento' => $a->intentos], false, ['run' => null, 'rol' => 'sistema']);
            throw $e; // la cola reintenta con backoff
        }
        $a->forceFill(['estado' => 'enviada', 'evidencia_tipo' => 'gmail_message_id', 'evidencia' => $id, 'evidencia_en' => now(), 'ultimo_error' => null])->save();
    }
}
