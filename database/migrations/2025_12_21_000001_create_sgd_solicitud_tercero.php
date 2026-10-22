<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Solicitudes hechas por un tercero a nombre de una persona asistida (representante, familiar, apoderado o quien le ayuda). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $t) {
            $t->boolean('en_nombre_de')->default(false);
            $t->string('interesado_run', 12)->nullable()->index();
            $t->string('interesado_nombre', 190)->nullable();
            $t->string('interesado_correo', 190)->nullable();
            $t->string('relacion', 20)->nullable();
            $t->string('acreditacion', 500)->nullable();
            $t->timestamp('declaracion_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', fn (Blueprint $t) => $t->dropColumn(['en_nombre_de', 'interesado_run', 'interesado_nombre', 'interesado_correo', 'relacion', 'acreditacion', 'declaracion_en']));
    }
};
