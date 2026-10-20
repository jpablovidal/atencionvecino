<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Run;
use Illuminate\Console\Command;

class Superadmin extends Command
{
    protected $signature = 'sgd:superadmin {run : RUN con guion} {--quitar : Quita la condición de superadministrador}';

    protected $description = 'Otorga (o quita) a un administrador el acceso a TODAS las vistas del sistema. El usuario debe tener un rol de base «administración».';

    public function handle(AuditoriaService $audit): int
    {
        $run = Run::normalizar((string) $this->argument('run'));
        $u = $run ? Usuario::query()->where('run', $run)->first() : null;
        if (! $u) {
            $this->error('No existe un usuario con ese RUN (use el formato 12345678-9).');

            return self::FAILURE;
        }
        $quitar = (bool) $this->option('quitar');
        if (! $quitar && $u->rolBase() !== 'admin') {
            $this->error('El usuario debe tener un rol de administración: asígnelo en Administración → Usuarios y vuelva a intentar.');

            return self::FAILURE;
        }
        $u->forceFill(['superadmin' => ! $quitar, 'activo' => $quitar ? $u->activo : true])->save();
        $audit->registrar('usuario.superadmin', 'usuario', $u->id, ['superadmin' => ! $quitar, 'via' => 'comando'], true);
        $this->info(($quitar ? 'Ya no es superadministrador: ' : 'Superadministrador: ')."{$u->run} ({$u->nombres} {$u->apellidos}). Cierre sesión y vuelva a entrar para ver el cambio.");

        return self::SUCCESS;
    }
}
