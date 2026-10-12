<?php

namespace App\Console\Commands;

use App\Services\Firma\ArchivoFirmaService;
use Illuminate\Console\Command;

class AnalizarFirmas extends Command
{
    protected $signature = 'archivos:analizar-firmas {--lote=200 : Archivos por ejecución}';

    protected $description = 'Reconoce y verifica firmas electrónicas en los archivos PDF aún sin analizar';

    public function handle(ArchivoFirmaService $svc): int
    {
        $r = $svc->pendientes((int) $this->option('lote'));
        $this->info("Analizados: {$r['analizados']}. Pendientes: {$r['sin_analizar']}.");

        return self::SUCCESS;
    }
}
