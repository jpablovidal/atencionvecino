<?php

use App\Services\CorrelativoService;
use Illuminate\Contracts\Console\Kernel;

// Proceso hijo para la prueba de concurrencia: php correlativo_worker.php <db.sqlite> <iteraciones>
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$argv[1]);
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $argv[1];
$_SERVER['DB_DATABASE'] = $argv[1];
$_SERVER['DB_CONNECTION'] = 'sqlite';
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$svc = new CorrelativoService;
$out = [];
for ($i = 0; $i < (int) $argv[2]; $i++) {
    $out[] = $svc->siguiente(1, 2026)['numero'];
}
echo json_encode($out);
