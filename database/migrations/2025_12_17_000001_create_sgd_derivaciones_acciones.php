<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derivaciones con acción requerida, plazo y estado de cumplimiento, y estado de gestión de las comunicaciones oficiales recibidas por DocDigital.
 * Las derivaciones anteriores quedan sin estado (sin seguimiento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('derivaciones', function (Blueprint $t) {
            $t->string('accion', 20)->nullable(); // responder|informar|revisar|conocimiento|redactar|archivar|otra
            $t->date('plazo')->nullable();
            $t->string('estado', 15)->nullable(); // pendiente|en_proceso|cumplida|devuelta (null = derivación anterior, sin seguimiento)
            $t->text('nota')->nullable();
            $t->timestamp('resuelto_en')->nullable();
            $t->index(['a_usuario_id', 'estado'], 'derivaciones_a_usuario_estado_index');
        });
        Schema::table('comunicaciones_oficiales', function (Blueprint $t) {
            $t->string('gestion', 15)->default('recibida'); // recibida|en_gestion|respondida|archivada (solo entrantes)
            $t->timestamp('gestion_en')->nullable();
            $t->unsignedBigInteger('gestion_por_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comunicaciones_oficiales', fn (Blueprint $t) => $t->dropColumn(['gestion', 'gestion_en', 'gestion_por_id']));
        Schema::table('derivaciones', function (Blueprint $t) {
            $t->dropIndex('derivaciones_a_usuario_estado_index');
            $t->dropColumn(['accion', 'plazo', 'estado', 'nota', 'resuelto_en']);
        });
    }
};
