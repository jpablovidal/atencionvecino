<?php

namespace App\Support;

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

/** Renderiza el DDL MySQL de las migraciones sin conectarse a MySQL (modo pretend). */
class SchemaSql
{
    /** Renderiza INSERT de MySQL de un seeder (modo pretend). */
    public static function renderSeed(): string
    {
        $out = self::capturar(fn () => (new DemoSeeder)->run());

        return "-- ============================================================================\n"
            ."-- ***** SOLO DESARROLLO ***** Datos de demostración. NO importar en producción.\n"
            ."-- GENERADO con: php scripts/generate-schema-sql.php --seed > database/sql/02_seed_demo.sql\n"
            ."-- Los códigos CPAT son EJEMPLOS con el formato oficial; reemplazar por los reales.\n"
            ."-- ============================================================================\n"
            ."SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n".implode("\n", $out)."\n\nSET FOREIGN_KEY_CHECKS = 1;\n";
    }

    /** @return array<int,string> */
    private static function capturar(callable $fn): array
    {
        DB::connection('mysql')->setPdo(new class extends \PDO
        {
            public function __construct() {}

            public function getAttribute(int $attribute): mixed
            {
                return '8.0.36';
            }

            public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
            {
                return "'".str_replace(['\\', "'"], ['\\\\', "''"], $string)."'";
            }
        });
        $prev = config('database.default');
        config(['database.default' => 'mysql']);
        $queries = DB::connection('mysql')->pretend($fn);
        config(['database.default' => $prev]);
        $out = [];
        foreach ($queries as $q) {
            $sql = $q['query'];
            foreach ($q['bindings'] as $b) {
                $v = $b === null ? 'NULL' : (is_numeric($b) && ! is_string($b) ? (string) $b : "'".str_replace(['\\', "'"], ['\\\\', "''"], (string) $b)."'");
                $sql = preg_replace('/\?/', str_replace('\\', '\\\\', $v), $sql, 1);
            }
            $out[] = $sql.';';
        }

        return $out;
    }

    public static function renderTriggers(): string
    {
        return "-- ============================================================================\n"
            ."-- OPCIONAL: triggers que bloquean UPDATE/DELETE en audit_log y documento_versiones.\n"
            ."-- Requiere el privilegio TRIGGER (muchos hostings compartidos no lo conceden). Si falla, omítalo y\n"
            ."-- aplique los GRANT sin UPDATE/DELETE descritos en el README. Importar tras 01_schema.sql.\n"
            ."-- GENERADO con: php scripts/generate-schema-sql.php --triggers > database/sql/03_triggers_opcional.sql\n"
            ."-- ============================================================================\n"
            .implode(";\n\n", AuditTriggers::sql('mysql')).";\n";
    }

    public static function render(): string
    {
        config(['database.connections.mysql.charset' => 'utf8mb4', 'database.connections.mysql.collation' => 'utf8mb4_unicode_ci']);
        $files = glob(database_path('migrations/2025_*_create_sgd_schema.php')) ?: [];
        sort($files);
        $out = [];
        $prev = config('database.default');
        DB::connection('mysql')->setPdo(new class extends \PDO
        {
            public function __construct() {}

            public function getAttribute(int $attribute): mixed
            {
                return '8.0.36';
            }

            public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
            {
                return "'".str_replace(['\\', "'"], ['\\\\', "''"], $string)."'";
            }
        });
        config(['database.default' => 'mysql']);
        Facade::clearResolvedInstance('db.schema');
        foreach ($files as $f) {
            $migration = require $f;
            $queries = DB::connection('mysql')->pretend(fn () => $migration->up());
            foreach ($queries as $q) {
                $sql = $q['query'];
                foreach ($q['bindings'] as $b) {
                    $sql = preg_replace('/\?/', is_numeric($b) ? (string) $b : "'".str_replace("'", "''", (string) $b)."'", $sql, 1);
                }
                $out[] = $sql.';';
            }
        }
        config(['database.default' => $prev]);

        $header = "-- ============================================================================\n"
            ."-- SGD Municipalidad de Santo Domingo - Esquema (MySQL 8 / MariaDB 10.6+, InnoDB, utf8mb4)\n"
            ."-- GENERADO desde las migraciones con: php scripts/generate-schema-sql.php > database/sql/01_schema.sql\n"
            ."-- Importar en phpMyAdmin (pestaña Importar) sobre una base de datos vacía.\n"
            ."-- ============================================================================\n"
            ."SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        return $header.implode("\n\n", $out)."\n\nSET FOREIGN_KEY_CHECKS = 1;\n";
    }
}
