<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expedientes iniciados de oficio por un funcionario (sin solicitud ciudadana) y sus partes: ciudadanos, funcionarios, externos y organismos
 * públicos de DocDigital. Las notificaciones de cierre pueden dirigirse a cada parte por su canal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_partes', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $t->string('tipo', 12); // ciudadano|funcionario|externo|organismo
            $t->string('rol', 20)->default('interesado'); // interesado|destinatario|copia
            $t->string('nombre', 190);
            $t->string('run', 12)->nullable();
            $t->string('correo', 190)->nullable();
            $t->foreignId('usuario_id')->nullable()->constrained('usuarios'); // funcionario de este sistema
            $t->unsignedBigInteger('dd_entidad_id')->nullable(); // organismo en DocDigital
            $t->text('dd_usuarios')->nullable(); // JSON [{id,nombre}] de personas de DocDigital
            $t->string('canal', 15)->nullable(); // casilla_unica|correo|presencial|interno|docdigital
            $t->string('notas', 250)->nullable();
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
            $t->index(['expediente_id', 'tipo']);
        });
        Schema::table('notificaciones', function (Blueprint $t) {
            $t->unsignedBigInteger('solicitud_id')->nullable()->change();
            $t->string('destinatario_run', 12)->nullable()->change();
            $t->unsignedBigInteger('parte_id')->nullable()->index();
            $t->unsignedBigInteger('comunicacion_id')->nullable();
            $t->string('destinatario_nombre', 190)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', fn (Blueprint $t) => $t->dropColumn(['parte_id', 'comunicacion_id', 'destinatario_nombre']));
        Schema::dropIfExists('expediente_partes');
    }
};
