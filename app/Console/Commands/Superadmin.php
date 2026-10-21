<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Run;
use Illuminate\Console\Command;

class Superadmin extends Command
{
    protected $signature = 'sgd:superadmin {run : RUN con guion} {--quitar : Quita la condición de superadministrador} {--crear : Crea el usuario administrador si no existe (o lo pasa a rol de administración)} {--nombres= : Nombres (con --crear)} {--apellidos= : Apellidos (con --crear)} {--correo= : Correo (con --crear)}';

    protected $description = 'Otorga (o quita) a un administrador el acceso a TODAS las vistas del sistema. Con --crear crea la cuenta (rol administrador) si no existe. Ingresa luego con ClaveÚnica.';

    public function handle(AuditoriaService $audit): int
    {
        $run = Run::normalizar((string) $this->argument('run'));
        $u = $run ? Usuario::query()->where('run', $run)->first() : null;
        if ($run && $this->option('crear') && ! $this->option('quitar')) {
            $u = $this->crear($run, $u, $audit);
            if (! $u) {
                return self::FAILURE;
            }
        }
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

    /** Crea (o pasa a administrador) la cuenta; no toca el rol si ya es de administración. */
    private function crear(string $run, ?Usuario $u, AuditoriaService $audit): ?Usuario
    {
        if ($u) {
            if ($u->rolBase() !== 'admin') {
                $u->forceFill(['rol' => 'admin', 'activo' => true])->save();
                $audit->registrar('usuario.rol_admin', 'usuario', $u->id, ['via' => 'comando'], true);
            }

            return $u;
        }
        $n = trim((string) $this->option('nombres'));
        $a = trim((string) $this->option('apellidos'));
        $c = trim((string) $this->option('correo'));
        if ($n === '' || $a === '' || ! filter_var($c, FILTER_VALIDATE_EMAIL)) {
            $this->error('Para crear la cuenta indique --nombres, --apellidos y un --correo válido.');

            return null;
        }
        $u = new Usuario(['run' => $run, 'nombres' => $n, 'apellidos' => $a, 'correo' => $c, 'cargo' => 'Administrador(a) del sistema', 'rol' => 'admin', 'puede_firmar' => false, 'activo' => true]);
        $u->save();
        $audit->registrar('usuario.creado', 'usuario', $u->id, ['rol' => 'admin', 'via' => 'comando'], true);

        return $u;
    }
}
