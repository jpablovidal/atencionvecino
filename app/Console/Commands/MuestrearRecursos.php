<?php

namespace App\Console\Commands;

use App\Services\Sistema\RecursosService;
use Illuminate\Console\Command;

class MuestrearRecursos extends Command
{
    protected $signature = 'recursos:muestrear';

    protected $description = 'Registra una muestra del consumo de recursos del servidor (para el histórico del panel) y depura las de más de 35 días';

    public function handle(RecursosService $s): int
    {
        $s->registrar();
        $this->info('Muestra registrada.');

        return self::SUCCESS;
    }
}
