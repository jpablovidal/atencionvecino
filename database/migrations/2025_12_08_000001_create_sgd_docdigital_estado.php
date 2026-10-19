<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Estado de tramitación que informa DocDigital (estado_tramitacion) de cada comunicación saliente: cargada, despachada, recibida… */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comunicaciones_oficiales', function (Blueprint $t) {
            $t->string('dd_estado', 50)->nullable();
            $t->timestamp('dd_estado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comunicaciones_oficiales', fn (Blueprint $t) => $t->dropColumn(['dd_estado', 'dd_estado_en']));
    }
};
