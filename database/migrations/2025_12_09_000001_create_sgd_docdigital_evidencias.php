<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Evidencia de las llamadas a la API de DocDigital (JSON enviado y respuesta, sin archivos ni credenciales) para la certificación de la integración. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docdigital_evidencias', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->timestamp('creado_en')->nullable()->index();
            $t->string('ambiente', 10);
            $t->string('metodo', 8);
            $t->string('ruta', 255);
            $t->text('consulta')->nullable();
            $t->longText('solicitud')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->longText('respuesta')->nullable();
            $t->unsignedInteger('duracion_ms')->nullable();
            $t->unsignedBigInteger('usuario_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docdigital_evidencias');
    }
};
