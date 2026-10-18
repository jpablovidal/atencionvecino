<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Anuncios que se muestran en un modal al cargar el sitio (título, descripción, imagen o video), con vigencia y audiencia. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anuncios', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->string('titulo', 160);
            $t->text('descripcion')->nullable();
            $t->string('audiencia', 20)->default('vecinos'); // vecinos | funcionarios | todos
            $t->string('medio_tipo', 10)->nullable(); // imagen | video
            $t->string('medio_mime', 60)->nullable();
            $t->string('medio_ruta', 200)->nullable(); // repositorio cifrado
            $t->string('medio_sha256', 64)->nullable();
            $t->unsignedBigInteger('medio_tamano')->nullable();
            $t->string('medio_alt', 200)->nullable(); // texto alternativo / descripción del video (accesibilidad)
            $t->string('enlace_url', 300)->nullable();
            $t->string('enlace_texto', 60)->nullable();
            $t->timestamp('inicia_en')->nullable();
            $t->timestamp('termina_en')->nullable();
            $t->boolean('activo')->default(true);
            $t->unsignedSmallInteger('orden')->default(10);
            $t->unsignedBigInteger('creado_por')->nullable();
            $t->timestamps();
            $t->index(['activo', 'audiencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anuncios');
    }
};
