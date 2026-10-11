<?php

namespace App\Console\Commands;

use App\Services\Interop\ComunicacionService;
use Illuminate\Console\Command;

class DocDigitalSincronizar extends Command
{
    protected $signature = 'docdigital:sincronizar';

    protected $description = 'Descarga las comunicaciones oficiales recibidas en DocDigital';

    public function handle(ComunicacionService $svc): int
    {
        if (! $svc->modoApi()) {
            $this->info('DocDigital en modo outbox: nada que sincronizar.');

            return self::SUCCESS;
        }
        try {
            $r = $svc->sincronizarRecibidos();
        } catch (\Throwable $e) {
            report($e);
            $this->error('No fue posible sincronizar con DocDigital.');

            return self::FAILURE;
        }
        $this->info("Nuevas: {$r['nuevas']} · omitidas: {$r['omitidas']}");

        return self::SUCCESS;
    }
}
