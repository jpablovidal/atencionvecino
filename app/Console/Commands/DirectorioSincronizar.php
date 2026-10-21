<?php

namespace App\Console\Commands;

use App\Services\Directorio\DirectorioException;
use App\Services\Directorio\DirectorioFuncionarios;
use Illuminate\Console\Command;

class DirectorioSincronizar extends Command
{
    protected $signature = 'directorio:sincronizar {--usuarios : Crea también la cuenta (rol funcionario) de los funcionarios vigentes que aún no la tienen}';

    protected $description = 'Importa del directorio (sistemas_users) las direcciones, departamentos y unidades, y actualiza cargo, correo y ubicación de los funcionarios. Idempotente.';

    public function handle(DirectorioFuncionarios $dir): int
    {
        try {
            $r = $dir->sincronizar((bool) $this->option('usuarios'));
        } catch (DirectorioException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info(sprintf('Filas leídas: %d · vigentes: %d · sin RUT válido: %d', $r['filas'], $r['vigentes'], $r['sin_rut_valido']));
        $this->info(sprintf('Direcciones nuevas: %d · departamentos nuevos: %d · unidades nuevas: %d', $r['direcciones_nuevas'], $r['departamentos_nuevos'], $r['unidades_nuevas']));
        $this->info(sprintf('Usuarios creados: %d · actualizados: %d', $r['usuarios_creados'], $r['usuarios_actualizados']));

        return self::SUCCESS;
    }
}
