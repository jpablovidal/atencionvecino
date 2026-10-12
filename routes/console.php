<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Cron de cPanel: * * * * * php /home/USUARIO/sgd/artisan schedule:run >> /dev/null 2>&1
// Cola con driver `database`: procesamiento corto y acotado (respeta límites del hosting compartido).
Schedule::command('queue:work --stop-when-empty --max-time=45 --tries=5 --timeout=50')->everyMinute()->withoutOverlapping(5);
Schedule::command('audit:verify')->dailyAt('03:10');
Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:30');
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::call(fn () => DB::table('sessions')->where('last_activity', '<', now()->subHours(9)->timestamp)->delete())->hourly();
Schedule::command('docdigital:sincronizar')->everyTenMinutes()->withoutOverlapping(10);
Schedule::command('repositorio:operar verificar --n=5')->dailyAt('03:40');
Schedule::command('archivos:analizar-firmas --lote=100')->hourly()->withoutOverlapping(30);
Schedule::command('recursos:muestrear')->everyFiveMinutes()->withoutOverlapping(5);
