<?php

namespace App\Jobs;

use App\Services\Repositorio\RepositorioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Migra en lotes cortos (respeta los límites del hosting) y se re-encola mientras queden pendientes. Única a la vez. */
class MigrarRepositorioADrive implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 55;

    public int $uniqueFor = 3600;

    public function handle(RepositorioService $svc): void
    {
        $r = $svc->migrarLote(20, 40);
        if ($r['pendientes'] > 0 && $r['migradas'] > 0) {
            self::dispatch()->delay(now()->addSeconds(5));
        }
    }
}
