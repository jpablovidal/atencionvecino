<?php

namespace App\Console\Commands;

use App\Services\Repositorio\RepositorioService;
use Illuminate\Console\Command;

class RepositorioComandos extends Command
{
    protected $signature = 'repositorio:operar {accion : probar|migrar|verificar|estado} {--lote=25} {--n=10}';

    protected $description = 'Repositorio de documentos (Google Drive): probar conexión, migrar archivos locales, verificar integridad, ver estado';

    public function handle(RepositorioService $svc): int
    {
        switch ($this->argument('accion')) {
            case 'probar':
                $ok = true;
                foreach ($svc->probar() as [$paso, $bien, $det]) {
                    $this->line(($bien ? '[OK]    ' : '[ERROR] ').$paso.' — '.$det);
                    $ok = $ok && $bien;
                }

                return $ok ? self::SUCCESS : self::FAILURE;
            case 'migrar':
                do {
                    $r = $svc->migrarLote((int) $this->option('lote'), 40);
                    $this->info("Migradas: {$r['migradas']} · errores: {$r['errores']} · pendientes: {$r['pendientes']}");
                } while ($r['pendientes'] > 0 && $r['migradas'] > 0);

                return $r['errores'] > 0 ? self::FAILURE : self::SUCCESS;
            case 'verificar':
                $r = $svc->verificarMuestra((int) $this->option('n'));
                $this->info("Revisados: {$r['revisados']} · correctos: {$r['ok']} · fallos: ".count($r['fallos']));

                return $r['fallos'] ? self::FAILURE : self::SUCCESS;
            case 'estado':
                foreach ($svc->estadisticas() as $k => $v) {
                    $this->line("$k: $v");
                }

                return self::SUCCESS;
        }
        $this->error('Acción desconocida.');

        return self::INVALID;
    }
}
