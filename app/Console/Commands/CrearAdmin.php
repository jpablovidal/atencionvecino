<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use App\Support\Run;
use Illuminate\Console\Command;

class CrearAdmin extends Command
{
    protected $signature = 'sgd:crear-admin {run : RUN con guion} {nombres} {apellidos} {correo}';

    protected $description = 'Crea (o reactiva) un administrador del sistema. Ingresará con ClaveÚnica (no se crea contraseña).';

    public function handle(): int
    {
        $run = Run::normalizar((string) $this->argument('run'));
        if (! $run) {
            $this->error('RUN inválido (formato 12345678-9 con dígito verificador correcto).');

            return self::FAILURE;
        }
        $u = Usuario::query()->updateOrCreate(['run' => $run], ['nombres' => $this->argument('nombres'), 'apellidos' => $this->argument('apellidos'),
            'correo' => $this->argument('correo'), 'rol' => 'admin', 'cargo' => 'Administrador(a) del sistema', 'puede_firmar' => false, 'activo' => true]);
        $this->info("Administrador listo: {$u->run} ({$u->nombres} {$u->apellidos}).");

        return self::SUCCESS;
    }
}
