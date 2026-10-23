<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rol del sistema «Administrador Municipal» (clave `adm_municipal`, cabe en `usuarios.rol`): como el Alcalde pero de menor rango (no firma como alcalde ni
 * crea delegaciones; sin algunos permisos de la Alcaldía). Retira la fila de una versión previa que usaba una clave demasiado larga para la columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = '2025-12-23 00:00:00'; // fijo: el SQL generado debe ser reproducible
        DB::table('roles')->where('sistema', 1)->whereIn('clave', ['administrador_municipal', 'administrador_munici'])->whereNotIn('clave', DB::table('usuarios')->select('rol'))->delete();
        DB::table('roles')->insertOrIgnore([
            'clave' => 'adm_municipal', 'nombre' => 'Administrador Municipal',
            'descripcion' => 'Supervisa la gestión municipal con el panel de Alcaldía, visa y firma lo que se le asigne y opera expedientes; es como el Alcalde pero de menor rango: no firma como alcalde ni crea delegaciones.',
            'rol_base' => 'adm_municipal', 'sistema' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('clave', 'adm_municipal')->delete();
    }
};
