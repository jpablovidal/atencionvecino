<?php

namespace App\Console\Commands;

use App\Exceptions\FlujoException;
use App\Services\Admin\SimulacionService;
use Illuminate\Console\Command;

/** Carga procesos simulados (vecinos ficticios, expedientes, documentos, versiones, pagos) para conocer el sistema. Solo en pruebas. */
class Simular extends Command
{
    protected $signature = 'sgd:simular';

    protected $description = 'Carga procesos y datos simulados para ver cómo funciona el sistema (solo SGD_AMBIENTE=pruebas; se borran al pasar a producción).';

    public function handle(SimulacionService $sim): int
    {
        try {
            foreach ($sim->cargar() as $l) {
                $this->line($l);
            }
        } catch (FlujoException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
