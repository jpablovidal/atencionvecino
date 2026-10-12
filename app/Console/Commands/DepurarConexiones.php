<?php

namespace App\Console\Commands;

use App\Services\Sistema\ConexionesService;
use Illuminate\Console\Command;

class DepurarConexiones extends Command
{
    protected $signature = 'conexiones:depurar';

    protected $description = 'Elimina los contadores de conexiones más antiguos que la retención configurada (SGD_CONEXIONES_RETENCION_DIAS)';

    public function handle(ConexionesService $s): int
    {
        $n = $s->depurar((int) config('sgd.conexiones_retencion_dias'));
        $this->info("Filas depuradas: $n");

        return self::SUCCESS;
    }
}
