<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Organigrama interno: departamentos y unidades de cada dirección municipal (vienen del directorio de funcionarios); las direcciones siguen siendo `unidades`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_internas', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete(); // dirección a la que pertenece
            $t->unsignedBigInteger('padre_id')->nullable(); // departamento (para tipo «unidad»)
            $t->string('tipo', 20); // departamento|unidad
            $t->string('nombre', 150);
            $t->boolean('activo')->default(true);
            $t->timestamps();
            $t->index(['unidad_id', 'tipo']);
        });
        Schema::table('usuarios', function (Blueprint $t) {
            $t->unsignedBigInteger('departamento_id')->nullable();
            $t->unsignedBigInteger('subunidad_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn(['departamento_id', 'subunidad_id']));
        Schema::dropIfExists('unidades_internas');
    }
};
