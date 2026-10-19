<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Anexos de las comunicaciones de DocDigital: los enviados (archivos del expediente) y los recibidos (con los que se omitieron y por qué). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicacion_anexos', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('comunicacion_id')->constrained('comunicaciones_oficiales')->cascadeOnDelete();
            $t->unsignedBigInteger('archivo_id')->nullable()->index(); // enviado: archivo del expediente; recibido: copia guardada (null si se omitió)
            $t->string('nombre', 190);
            $t->string('id_externo', 100)->nullable(); // id del anexo en DocDigital
            $t->string('estado', 10); // pendiente (por enviar) | enviado | recibido | omitido
            $t->string('motivo', 190)->nullable(); // por qué se omitió
            $t->unsignedInteger('tamano')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicacion_anexos');
    }
};
