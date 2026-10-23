<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Rol del sistema «Administrador Municipal»: como el Alcalde pero de menor rango (no firma como alcalde ni crea delegaciones; sin algunos permisos de la Alcaldía). */
return new class extends Migration
{
    public function up(): void
    {
        $now = '2025-12-23 00:00:00'; // fijo: el SQL generado debe ser reproducible
        DB::table('roles')->insertOrIgnore([
            'clave' => 'administrador_municipal', 'nombre' => 'Administrador Municipal',
            'descripcion' => 'Supervisa la gestión municipal con el panel de Alcaldía, visa y firma lo que se le asigne y opera expedientes; es como el Alcalde pero de menor rango: no firma como alcalde ni crea delegaciones.',
            'rol_base' => 'administrador_municipal', 'sistema' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('clave', 'administrador_municipal')->delete();
    }
};
