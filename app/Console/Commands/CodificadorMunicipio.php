<?php

namespace App\Console\Commands;

use App\Services\Interop\CodificadorService;
use Illuminate\Console\Command;

class CodificadorMunicipio extends Command
{
    protected $signature = 'codificador:municipio';

    protected $description = 'Deja en el catálogo del Codificador a la Municipalidad de Santo Domingo (PE-MUN-00243) y su institución padre (idempotente).';

    public function handle(CodificadorService $svc): int
    {
        $svc->asegurarMunicipioPorDefecto();
        $this->info('Municipalidad de Santo Domingo (PE-MUN-00243) lista en el catálogo del Codificador.');

        return self::SUCCESS;
    }
}
