<?php

/*
 * Genera database/sql/01_schema.sql desde las migraciones, usando la gramática MySQL en modo "pretend"
 * (no requiere servidor MySQL). Uso: php scripts/generate-schema-sql.php [--check]
 */

use App\Support\SchemaSql;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
putenv('DB_CONNECTION=mysql');
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql']);

$arg = $argv[1] ?? '';
echo match ($arg) {
    '--seed' => SchemaSql::renderSeed(),
    '--triggers' => SchemaSql::renderTriggers(),
    default => SchemaSql::render(),
};
