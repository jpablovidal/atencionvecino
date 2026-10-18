<?php

namespace App\Console\Commands;

use App\Models\CuentaPrueba;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\Ambiente;
use App\Support\Run;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Crea (solo si faltan) las 2 cuentas de PRUEBA de los roles Autoridad y OIRS en una instalación ya existente. */
class PruebasSeguimiento extends Command
{
    protected $signature = 'pruebas:seguimiento';

    protected $description = 'Crea (solo si faltan) las 2 cuentas de PRUEBA de los roles Autoridad y OIRS en una instalación existente (solo SGD_AMBIENTE=pruebas).';

    public function handle(): int
    {
        if (! Ambiente::esPruebas()) {
            $this->error('SGD_AMBIENTE no es «pruebas»: en producción cree los usuarios Autoridad y OIRS desde la Administración.');

            return self::FAILURE;
        }
        $filas = [];
        foreach ([[10000013, 'Alicia', 'Autoridad Demo', 'autoridad', 'Autoridad municipal'], [10000014, 'Omar', 'OIRS Demo', 'oirs', 'Encargado OIRS']] as [$cuerpo, $nom, $ape, $rol, $cargo]) {
            $run = $cuerpo.'-'.Run::dv((string) $cuerpo);
            if (! Rol::query()->where('clave', $rol)->exists()) {
                $this->error("Falta el rol $rol: ejecute php artisan migrate --force.");

                return self::FAILURE;
            }
            if (Usuario::query()->where('run', $run)->exists()) {
                $this->line("Ya existe el usuario $run: sin cambios.");

                continue;
            }
            Usuario::query()->create(['run' => $run, 'nombres' => $nom, 'apellidos' => $ape, 'correo' => strtolower($nom).'.'.strtolower(explode(' ', $ape)[0]).'@demo.santodomingo.invalid', 'cargo' => $cargo,
                'rol' => $rol, 'unidad_id' => null, 'puede_firmar' => false, 'activo' => true]);
            $pw = $this->generar();
            CuentaPrueba::query()->updateOrCreate(['run' => $run], ['tipo' => 'funcionario', 'nombres' => $nom, 'apellidos' => $ape, 'password' => Hash::make($pw), 'activo' => true]);
            $filas[] = [$run, $nom.' '.$ape, $rol, $pw];
        }
        if ($filas) {
            $this->table(['RUN (usuario)', 'Nombre', 'Rol', 'Contraseña (se muestra una vez)'], $filas);
        }

        return self::SUCCESS;
    }

    private function generar(): string
    {
        $a = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $s = '';
        for ($i = 0; $i < 14; $i++) {
            $s .= $a[random_int(0, strlen($a) - 1)];
        }

        return $s.'-'.random_int(10, 99);
    }
}
