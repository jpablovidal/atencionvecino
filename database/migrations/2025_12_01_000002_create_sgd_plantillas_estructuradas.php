<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plantillas estructuradas (materia, vistos, considerando, cuerpo, firmas y cadena de responsabilidad) y vínculo documento → plantilla. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantillas', fn (Blueprint $t) => $t->json('estructura')->nullable());
        Schema::table('plantilla_versiones', fn (Blueprint $t) => $t->json('estructura')->nullable());
        Schema::table('documentos', fn (Blueprint $t) => $t->unsignedBigInteger('plantilla_id')->nullable());
    }

    public function down(): void
    {
        Schema::table('documentos', fn (Blueprint $t) => $t->dropColumn('plantilla_id'));
        Schema::table('plantilla_versiones', fn (Blueprint $t) => $t->dropColumn('estructura'));
        Schema::table('plantillas', fn (Blueprint $t) => $t->dropColumn('estructura'));
    }
};
