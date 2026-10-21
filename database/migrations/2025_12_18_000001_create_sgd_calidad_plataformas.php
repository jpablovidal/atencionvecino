<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Decreto 11 (calidad y funcionamiento): catálogo de plataformas electrónicas con línea de base y Plan de Mejora Continua. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calidad_plataformas', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('nombre', 150);
            $t->text('descripcion')->nullable();
            $t->string('tipo', 10)->default('propia'); // propia|externa
            $t->string('responsable', 120)->nullable();
            $t->text('linea_base')->nullable(); // estado de la plataforma cuando entró al ciclo de calidad
            $t->date('linea_base_fecha')->nullable();
            $t->boolean('priorizada')->default(false);
            $t->date('vida_util_hasta')->nullable();
            $t->boolean('activa')->default(true);
            $t->timestamps();
        });
        Schema::create('calidad_iniciativas', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('plataforma_id')->constrained('calidad_plataformas')->cascadeOnDelete();
            $t->text('descripcion');
            $t->text('objetivo')->nullable();
            $t->unsignedTinyInteger('etapa')->default(1); // 1 plan, 2 presupuesto, 3 implementación, 4 evaluación, 5 ajustes
            $t->string('subcaracteristica', 120)->nullable();
            $t->string('metrica', 190)->nullable();
            $t->string('umbral_exitoso', 120)->nullable();
            $t->string('umbral_aceptable', 120)->nullable();
            $t->string('umbral_insuficiente', 120)->nullable();
            $t->string('responsable', 120)->nullable();
            $t->date('plazo')->nullable();
            $t->unsignedBigInteger('costo_estimado')->nullable(); // pesos
            $t->text('riesgos')->nullable();
            $t->text('resultado')->nullable(); // medición y análisis (etapa 4)
            $t->string('estado', 12)->default('planificada'); // planificada|en_curso|terminada|descartada
            $t->timestamps();
            $t->index(['plataforma_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calidad_iniciativas');
        Schema::dropIfExists('calidad_plataformas');
    }
};
