<?php

namespace App\Console\Commands;

use App\Services\Interop\ComunicacionService;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigitalEstado;
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
            DocDigitalEstado::registrar(null, $e instanceof DocDigitalException ? $e->getMessage() : 'Error inesperado al sincronizar (vea el registro de la aplicación).');
            $this->error('No fue posible sincronizar con DocDigital.');

            return self::FAILURE;
        }
        $this->info("Nuevas: {$r['nuevas']} · omitidas: {$r['omitidas']} · estados consultados: ".$svc->refrescarEstados());

        return self::SUCCESS;
    }
}
