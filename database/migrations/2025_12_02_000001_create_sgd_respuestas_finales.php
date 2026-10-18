<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Respuesta final al solicitante (instrucción de quien gestionó + despacho de quien responde, p. ej. Oficina de Partes),
 * movimientos de la solicitud (historial que ve el ciudadano) y sus avisos (Casilla Única + copia por correo). Rol «Oficina de Partes».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_movimientos', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('solicitud_id')->constrained('solicitudes');
            $t->unsignedBigInteger('expediente_id')->nullable();
            $t->string('tipo', 24);
            $t->string('descripcion', 240); // texto apto para el ciudadano: sin datos internos
            $t->unsignedBigInteger('unidad_id')->nullable(); // dirección municipal responsable en ese momento
            $t->timestamp('created_at')->useCurrent();
            $t->index(['solicitud_id', 'id']);
        });
        Schema::create('avisos_movimiento', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('movimiento_id')->constrained('solicitud_movimientos');
            $t->string('canal', 15); // casilla_unica | correo
            $t->string('estado', 12)->default('pendiente'); // pendiente | enviada | fallida
            $t->string('evidencia_tipo', 30)->nullable();
            $t->string('evidencia', 255)->nullable();
            $t->timestamp('evidencia_en')->nullable();
            $t->unsignedTinyInteger('intentos')->default(0);
            $t->string('ultimo_error', 120)->nullable();
            $t->timestamps();
            $t->unique(['movimiento_id', 'canal']);
        });
        Schema::create('respuestas_finales', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes');
            $t->foreignId('solicitud_id')->constrained('solicitudes');
            $t->string('estado', 12)->default('instruida'); // instruida | despachada | anulada
            $t->string('canal', 15)->default('casilla_unica'); // casilla_unica (con copia por correo) | presencial (excepcional)
            $t->string('motivo_presencial', 300)->nullable();
            $t->string('instruccion', 600)->nullable(); // indicaciones para quien responde
            $t->foreignId('instruida_por')->constrained('usuarios');
            $t->unsignedBigInteger('despachada_por')->nullable();
            $t->timestamp('despachada_en')->nullable();
            $t->string('anulada_motivo', 300)->nullable();
            $t->timestamps();
            $t->index(['estado', 'id']);
        });
        Schema::create('respuesta_final_items', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('respuesta_id')->constrained('respuestas_finales');
            $t->unsignedBigInteger('documento_id')->nullable();
            $t->unsignedBigInteger('archivo_id')->nullable();
            $t->index('respuesta_id');
        });
        Schema::table('notificaciones', function (Blueprint $t) {
            $t->unsignedBigInteger('archivo_id')->nullable();
            $t->unsignedBigInteger('respuesta_id')->nullable();
        });

        $now = '2026-12-02 00:00:00'; // fijo: el SQL generado debe ser reproducible
        DB::table('roles')->insertOrIgnore(['clave' => 'oficina_partes', 'nombre' => 'Oficina de Partes', 'descripcion' => 'Recibe las respuestas finales instruidas por quienes gestionaron los expedientes y las notifica al solicitante (Casilla Única con copia por correo; excepcionalmente entrega física).',
            'rol_base' => 'funcionario', 'sistema' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $id = DB::table('roles')->where('clave', 'oficina_partes')->value('id');
        if ($id) {
            DB::table('rol_permisos')->insertOrIgnore(['rol_id' => $id, 'permiso' => 'respuestas.despachar']);
        }
    }

    public function down(): void
    {
        $id = DB::table('roles')->where('clave', 'oficina_partes')->where('sistema', 1)->value('id');
        if ($id) {
            DB::table('rol_permisos')->where('rol_id', $id)->delete();
            DB::table('roles')->where('id', $id)->delete();
        }
        Schema::table('notificaciones', fn (Blueprint $t) => $t->dropColumn(['archivo_id', 'respuesta_id']));
        foreach (['respuesta_final_items', 'respuestas_finales', 'avisos_movimiento', 'solicitud_movimientos'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
