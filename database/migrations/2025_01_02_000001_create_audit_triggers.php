<?php

use App\Support\AuditTriggers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Triggers de inmutabilidad. En hosting sin privilegio TRIGGER se omiten (la restricción se logra con GRANT, ver README). */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        foreach (AuditTriggers::sql($driver) as $sql) {
            try {
                DB::unprepared($sql);
            } catch (Throwable $e) {
                if ($driver !== 'sqlite') {
                    logger()->warning('Trigger omitido (¿sin privilegio TRIGGER?)');

                    return;
                }
                throw $e;
            }
        }
    }

    public function down(): void
    {
        foreach (AuditTriggers::drop() as $sql) {
            DB::unprepared($sql);
        }
    }
};
