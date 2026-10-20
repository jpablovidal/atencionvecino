<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Datos que entrega la API del Codificador (id numérico, OID e información adicional) de cada institución. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituciones', function (Blueprint $t) {
            $t->unsignedBigInteger('codificador_id')->nullable();
            $t->string('oid', 120)->nullable();
            $t->text('info_extra')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instituciones', fn (Blueprint $t) => $t->dropColumn(['codificador_id', 'oid', 'info_extra']));
    }
};
