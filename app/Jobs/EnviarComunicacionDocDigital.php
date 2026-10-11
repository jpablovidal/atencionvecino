<?php

namespace App\Jobs;

use App\Models\ComunicacionOficial;
use App\Services\Interop\ComunicacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Envío idempotente a DocDigital por la cola `database` (reintentos con backoff). */
class EnviarComunicacionDocDigital implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public int $comunicacionId) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(ComunicacionService $svc): void
    {
        $c = ComunicacionOficial::query()->find($this->comunicacionId);
        if ($c && $c->estado !== 'enviada') {
            $svc->enviar($c); // propaga la excepción para que la cola reintente
        }
    }
}
