<?php

namespace App\Console\Commands;

use App\Models\CuentaPrueba;
use App\Models\Rol;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Support\Ambiente;
use App\Support\Run;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Crea (solo si faltan) la unidad y la cuenta de PRUEBA de Oficina de Partes (rol que responde a los solicitantes) en una instalación existente. */
class PruebasPartes extends Command
{
    protected $signature = 'pruebas:partes';

    protected $description = 'Crea (solo si faltan) la unidad Oficina de Partes y su cuenta de PRUEBA (solo SGD_AMBIENTE=pruebas).';

    public function handle(): int
    {
        if (! Ambiente::esPruebas()) {
            $this->error('SGD_AMBIENTE no es «pruebas»: en producción cree la unidad y los usuarios de Oficina de Partes desde la Administración.');

            return self::FAILURE;
        }
        if (! Rol::query()->where('clave', 'oficina_partes')->exists()) {
            $this->error('Falta el rol oficina_partes: ejecute php artisan migrate --force.');

            return self::FAILURE;
        }
        $unidad = Unidad::query()->firstOrCreate(['codigo' => 'OPARTES'], ['nombre' => 'Oficina de Partes', 'activo' => true]);
        $run = '10000016-'.Run::dv('10000016');
        if (Usuario::query()->where('run', $run)->exists()) {
            $this->line("Ya existe el usuario $run: sin cambios.");

            return self::SUCCESS;
        }
        Usuario::query()->create(['run' => $run, 'nombres' => 'Patricia', 'apellidos' => 'Oficina de Partes Demo', 'correo' => 'patricia.oficina@demo.santodomingo.invalid', 'cargo' => 'Encargada de Oficina de Partes',
            'rol' => 'oficina_partes', 'unidad_id' => $unidad->id, 'puede_firmar' => false, 'activo' => true]);
        $pw = $this->generar();
        CuentaPrueba::query()->updateOrCreate(['run' => $run], ['tipo' => 'funcionario', 'nombres' => 'Patricia', 'apellidos' => 'Oficina de Partes Demo', 'password' => Hash::make($pw), 'activo' => true]);
        $this->table(['RUN (usuario)', 'Nombre', 'Rol', 'Contraseña (se muestra una vez)'], [[$run, 'Patricia Oficina de Partes Demo', 'oficina_partes', $pw]]);

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
