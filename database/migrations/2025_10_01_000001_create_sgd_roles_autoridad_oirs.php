<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Roles del sistema «Autoridad» (consulta de seguimiento de solicitudes) y «OIRS» (solicitudes, responsables y plazos). Solo lectura. */
return new class extends Migration
{
    private const ROLES = [
        ['autoridad', 'Autoridad', 'Consulta el seguimiento de las solicitudes: etapa, estado, quién la tiene y si terminó. Solo lectura; no ve documentos.'],
        ['oirs', 'OIRS', 'Visualiza solicitudes, su estado, responsables y plazos vencidos o por vencer. Solo lectura; no ve documentos.'],
    ];

    public function up(): void
    {
        $now = '2026-10-01 00:00:00'; // fijo: el SQL generado debe ser reproducible
        foreach (self::ROLES as [$clave, $nombre, $desc]) {
            DB::table('roles')->insertOrIgnore(['clave' => $clave, 'nombre' => $nombre, 'descripcion' => $desc, 'rol_base' => $clave, 'sistema' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('clave', array_column(self::ROLES, 0))->where('sistema', 1)->delete();
    }
};
