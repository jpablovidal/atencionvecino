<?php

namespace Tests\Feature;

use App\Support\SchemaSql;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** database/sql/01_schema.sql (phpMyAdmin) debe ser equivalente a las migraciones Laravel. */
class EsquemaSqlTest extends TestCase
{
    use RefreshDatabase;

    private function sqlFile(string $n): string
    {
        return (string) file_get_contents(base_path('database/sql/'.$n));
    }

    public function test_el_sql_esta_generado_desde_las_migraciones_sin_deriva(): void
    {
        $this->assertSame(SchemaSql::render(), $this->sqlFile('01_schema.sql'), 'Regenerar: php scripts/generate-schema-sql.php > database/sql/01_schema.sql');
        $this->assertSame(SchemaSql::renderTriggers(), $this->sqlFile('03_triggers_opcional.sql'));
    }

    public function test_mismas_tablas_y_columnas_que_las_migraciones(): void
    {
        $sql = $this->sqlFile('01_schema.sql');
        preg_match_all('/^create table `(\w+)` \((.+?)\) default character set/ms', $sql, $m, PREG_SET_ORDER);
        $enSql = [];
        foreach ($m as [, $tabla, $cuerpo]) {
            preg_match_all('/`(\w+)` (?:bigint|varchar|char|tinyint|smallint|int|text|longtext|json|decimal|timestamp|date|datetime)/', $cuerpo, $c);
            $enSql[$tabla] = $c[1];
        }
        preg_match_all('/^alter table `(\w+)` add `(\w+)` (?:bigint|varchar|char|tinyint|smallint|int|text|longtext|json|decimal|timestamp|date|datetime)/m', $sql, $alt, PREG_SET_ORDER);
        foreach ($alt as [, $tabla, $col]) {
            $enSql[$tabla][] = $col;
        }
        $enSqlite = [];
        foreach (Schema::getTables() as $t) {
            if (in_array($t['name'], ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true) || str_starts_with($t['name'], 'sqlite_')) {
                continue;
            }
            $enSqlite[$t['name']] = array_column(Schema::getColumns($t['name']), 'name');
        }
        ksort($enSql);
        ksort($enSqlite);
        $this->assertSame(array_keys($enSqlite), array_keys($enSql), 'mismas tablas');
        foreach ($enSqlite as $tabla => $cols) {
            $a = $cols;
            $b = $enSql[$tabla];
            sort($a);
            sort($b);
            $this->assertSame($a, $b, "columnas de $tabla");
        }
    }

    public function test_sql_innodb_utf8mb4_con_claves_foraneas_e_indices(): void
    {
        $sql = $this->sqlFile('01_schema.sql');
        $this->assertSame(substr_count($sql, 'create table'), substr_count($sql, 'engine = InnoDB'));
        $this->assertStringContainsString("collate 'utf8mb4_unicode_ci'", $sql);
        $this->assertStringContainsString('utf8mb4', $sql);
        $this->assertGreaterThan(20, substr_count($sql, 'foreign key'));
        $this->assertStringContainsString('add unique `documentos_tipo_documento_id_anio_numero_unique`', $sql);
        $this->assertStringContainsString('primary key (`tipo_documento_id`, `anio`)', $sql);
        $this->assertStringContainsString('`audit_log`', $sql);
    }

    public function test_seed_demo_marcado_solo_desarrollo_y_cargable(): void
    {
        $seed = $this->sqlFile('02_seed_demo.sql');
        $this->assertStringContainsString('SOLO DESARROLLO', $seed);
        // La sintaxis (backticks, comillas simples) también es válida en SQLite: se carga para comprobar integridad de FK.
        foreach (preg_split('/;\s*\n/', preg_replace('/^--.*$/m', '', $seed)) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '' && ! str_starts_with($stmt, 'SET ')) {
                DB::unprepared($stmt);
            }
        }
        $this->assertSame(8, DB::table('usuarios')->count());
        $this->assertSame(6, DB::table('tipos_documento')->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }
}
