<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Consentimiento del tratamiento de datos personales al ingresar una solicitud (Política de Privacidad, Decreto Alcaldicio N° 2590). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $t) {
            $t->boolean('consentimiento_datos')->nullable();
            $t->timestamp('consentimiento_en')->nullable();
            $t->string('consentimiento_version', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', fn (Blueprint $t) => $t->dropColumn(['consentimiento_datos', 'consentimiento_en', 'consentimiento_version']));
    }
};
