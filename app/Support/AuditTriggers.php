<?php

namespace App\Support;

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
}
