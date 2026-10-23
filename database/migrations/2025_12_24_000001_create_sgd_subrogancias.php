<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Subrogancias: un funcionario(a) ausente es reemplazado por otro entre dos fechas y horas; el subrogante gestiona sus expedientes mientras dure. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subrogancias', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('titular_id')->constrained('usuarios'); // quien se ausenta
            $t->foreignId('subrogante_id')->constrained('usuarios'); // quien lo reemplaza
            $t->dateTime('desde');
            $t->dateTime('hasta');
            $t->string('motivo', 250)->nullable();
            $t->boolean('activo')->default(true); // false = terminada antes de tiempo
            $t->dateTime('terminada_en')->nullable();
            $t->foreignId('terminada_por')->nullable()->constrained('usuarios');
            $t->foreignId('creado_por')->nullable()->constrained('usuarios');
            $t->timestamps();
            $t->index(['subrogante_id', 'activo', 'desde', 'hasta']);
            $t->index(['titular_id', 'activo', 'desde', 'hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subrogancias');
    }
};
