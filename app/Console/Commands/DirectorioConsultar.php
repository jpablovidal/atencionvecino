<?php

namespace App\Console\Commands;

use App\Services\Directorio\DirectorioFuncionarios;
use App\Support\Run;
use Illuminate\Console\Command;

class DirectorioConsultar extends Command
{
    protected $signature = 'directorio:consultar {run : RUN en formato 12345678-9}';

    protected $description = 'Explica por qué un RUN se reconoce o no como funcionario(a) del directorio (sin mostrar datos personales).';

    public function handle(DirectorioFuncionarios $dir): int
    {
        $run = Run::normalizar((string) $this->argument('run'));
        if (! $run) {
            $this->error('El RUN no es válido.');

            return self::FAILURE;
        }
        $ok = true;
        foreach ($dir->diagnosticarRun($run) as [$n, $bien, $det]) {
            $this->line(($bien ? '[OK]      ' : '[REVISAR] ').$n.' — '.$det);
            $ok = $ok && $bien;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
