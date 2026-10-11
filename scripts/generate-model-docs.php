<?php

/*
 * Inserta/actualiza bloques @property en app/Models/* a partir del esquema (para análisis estático).
 * Uso: php scripts/generate-model-docs.php   (requiere BD migrada)
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

foreach (glob(__DIR__.'/../app/Models/*.php') as $file) {
    $class = 'App\\Models\\'.basename($file, '.php');
    $m = new $class;
    $casts = $m->getCasts();
    $props = [];
    foreach (Schema::getColumns($m->getTable()) as $c) {
        $type = match (true) {
            ($casts[$c['name']] ?? null) === 'boolean' => 'bool',
            ($casts[$c['name']] ?? null) === 'array' => 'array<mixed>',
            in_array($casts[$c['name']] ?? null, ['datetime', 'date'], true) => '\Illuminate\Support\Carbon',
            str_contains($c['type'], 'int') => 'int',
            default => 'string',
        };
        $props[] = ' * @property '.$type.($c['nullable'] ? '|null' : '').' $'.$c['name'];
    }
    $doc = "/**\n".implode("\n", $props)."\n */\n";
    $src = file_get_contents($file);
    $src = preg_replace('#/\*\*\n(?: \* @property[^\n]*\n)+ \*/\n#', '', $src);
    $src = preg_replace('/^((?:final |abstract )?class )/m', $doc.'$1', $src, 1);
    file_put_contents($file, $src);
}
