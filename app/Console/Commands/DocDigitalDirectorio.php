<?php

namespace App\Console\Commands;

use App\Services\Interop\DocDigitalDirectorioService;
use Illuminate\Console\Command;

class DocDigitalDirectorio extends Command
{
    protected $signature = 'docdigital:directorio {--forzar : Empieza una carga completa aunque no corresponda} {--segundos=40 : Tiempo máximo de trabajo en esta ejecución}';

    protected $description = 'Carga las entidades y las personas habilitadas en DocDigital (reanudable)';

    public function handle(DocDigitalDirectorioService $svc): int
    {
        if (! $svc->modoApi()) {
            $this->info('DocDigital no está en modo DemoDoc ni Producción: no hay directorio que cargar.');

            return self::SUCCESS;
        }
        try {
            $r = $svc->cargar((int) $this->option('segundos'), (bool) $this->option('forzar'));
        } catch (\Throwable $e) {
            report($e);
            $this->error('No fue posible cargar el directorio de DocDigital (vea el registro de la aplicación).');

            return self::FAILURE;
        }
        if ($r['error']) {
            $this->error($r['error']);

            return self::FAILURE;
        }
        $this->info(($r['completo'] ? 'Directorio al día' : 'Carga en curso (fase: '.$r['fase'].'; continúa en la próxima ejecución)')." · entidades: {$r['entidades']} · personas: {$r['personas']} · roles: {$r['roles']}");

        return self::SUCCESS;
    }
}
