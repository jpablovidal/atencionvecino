<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/** Procesos PHP paralelos sobre una BD real (SQLite en archivo): sin huecos ni duplicados. */
class CorrelativoConcurrenciaTest extends TestCase
{
    public function test_procesos_paralelos_no_duplican_ni_dejan_huecos(): void
    {
        $db = sys_get_temp_dir().'/sgd_conc_'.getmypid().'.sqlite';
        @unlink($db);
        touch($db);
        $env = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $db, 'APP_ENV' => 'testing'] + getenv();
        $root = dirname(__DIR__, 2);
        exec('cd '.escapeshellarg($root).' && DB_CONNECTION=sqlite DB_DATABASE='.escapeshellarg($db).' php artisan migrate --force --no-interaction 2>&1 && DB_CONNECTION=sqlite DB_DATABASE='.escapeshellarg($db).' php artisan db:seed --force --no-interaction 2>&1', $o, $rc);
        $this->assertSame(0, $rc, implode("\n", $o));

        $procs = [];
        for ($i = 0; $i < 6; $i++) {
            $procs[] = proc_open(['php', __DIR__.'/../Support/correlativo_worker.php', $db, '25'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i], $root, $env);
        }
        $todos = [];
        foreach ($procs as $i => $p) {
            $salida = stream_get_contents($pipes[$i][1]);
            $err = stream_get_contents($pipes[$i][2]);
            proc_close($p);
            $nums = json_decode($salida, true);
            $this->assertIsArray($nums, "worker $i falló: $err");
            $todos = array_merge($todos, $nums);
        }
        sort($todos);
        $this->assertSame(range(1, 150), $todos, 'sin duplicados ni huecos');
        @unlink($db);
    }
}
