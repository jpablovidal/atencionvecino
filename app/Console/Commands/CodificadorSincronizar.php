<?php

namespace App\Console\Commands;

use App\Exceptions\FlujoException;
use App\Services\Interop\CodificadorService;
use Illuminate\Console\Command;

class CodificadorSincronizar extends Command
{
    protected $signature = 'codificador:sincronizar';

    protected $description = 'Sincroniza instituciones y procesos desde el Codificador (modo api)';

    public function handle(CodificadorService $svc): int
    {
        try {
            foreach ($svc->sincronizar() as $ent => $r) {
                $this->info("$ent: {$r['creados']} creados, {$r['actualizados']} actualizados, ".count($r['errores']).' errores');
            }
        } catch (FlujoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
