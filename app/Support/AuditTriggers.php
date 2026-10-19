<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Triggers que bloquean UPDATE/DELETE de tablas de evidencia (si el hosting concede el privilegio TRIGGER). */
class AuditTriggers
{
    public const TABLAS = ['audit_log', 'documento_versiones'];

    /** @return array<int,string> */
    public static function sql(string $driver): array
    {
        $out = [];
        foreach (self::TABLAS as $t) {
            foreach (['UPDATE', 'DELETE'] as $op) {
                $n = $t.'_no_'.strtolower($op);
                $out[] = $driver === 'sqlite'
                    ? "CREATE TRIGGER IF NOT EXISTS $n BEFORE $op ON $t BEGIN SELECT RAISE(ABORT, '$t es de solo lectura (append-only)'); END"
                    : "CREATE TRIGGER $n BEFORE $op ON `$t` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '$t es de solo lectura (append-only)'";
            }
        }

        return $out;
    }

    /** @return array<int,string> */
    public static function drop(): array
    {
        $out = [];
        foreach (self::TABLAS as $t) {
            foreach (['update', 'delete'] as $op) {
                $out[] = "DROP TRIGGER IF EXISTS {$t}_no_{$op}";
            }
        }

        return $out;
    }

    /** ¿Existe el trigger de bloqueo de borrado de la tabla? */
    public static function existeBloqueoDelete(string $tabla): bool
    {
        $n = $tabla.'_no_delete';
        $driver = DB::getDriverName();
        try {
            return $driver === 'sqlite'
                ? DB::table('sqlite_master')->where('type', 'trigger')->where('name', $n)->exists()
                : DB::table('information_schema.triggers')->whereRaw('trigger_schema = database()')->where('trigger_name', $n)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Suspende SOLO el bloqueo de borrado de una tabla de evidencia, ejecuta $fn y lo restaura siempre.
     * Lo usa únicamente el paso de pruebas a producción (borra los datos de prueba; audit_log nunca se toca).
     */
    public static function sinBloqueoDelete(string $tabla, callable $fn): mixed
    {
        $existia = self::existeBloqueoDelete($tabla);
        $n = $tabla.'_no_delete';
        if ($existia) {
            DB::unprepared("DROP TRIGGER IF EXISTS $n");
        }
        try {
            return $fn();
        } finally {
            if ($existia) {
                $driver = DB::getDriverName();
                foreach (self::sql($driver) as $sql) {
                    if (str_contains($sql, " $n ")) {
                        DB::unprepared($sql);
                    }
                }
            }
        }
    }
}
