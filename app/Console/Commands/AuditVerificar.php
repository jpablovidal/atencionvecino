<?php

namespace App\Console\Commands;

use App\Services\AuditoriaService;
use Illuminate\Console\Command;

class AuditVerificar extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verifica la cadena HMAC de la bitácora de auditoría';

    public function handle(AuditoriaService $a): int
    {
        $r = $a->verificar();
        if ($r['ok']) {
            $this->info("Cadena íntegra: {$r['total']} registros.");

            return self::SUCCESS;
        }
        $this->error("CADENA ROTA en el registro {$r['roto_en']}. Registre un incidente (ver docs/INCIDENTES.md).");
        report(new \RuntimeException('Cadena de auditoría rota en '.$r['roto_en']));

        return self::FAILURE;
    }
}
