<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clasificación opcional del documento (tipo de materia y macrotema) para el listado de documentos vigentes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $t) {
            $t->string('tipo_materia', 120)->nullable();
            $t->string('macrotema', 120)->nullable();
            $t->index(['estado', 'fecha_firma'], 'documentos_estado_fecha_firma_index');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $t) {
            $t->dropIndex('documentos_estado_fecha_firma_index');
            $t->dropColumn(['tipo_materia', 'macrotema']);
        });
    }
};
