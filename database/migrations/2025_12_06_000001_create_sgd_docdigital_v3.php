<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DocDigital API v3: las instituciones se envían por el id de su entidad en DocDigital (distinto del código del Codificador). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituciones', function (Blueprint $t) {
            $t->unsignedBigInteger('docdigital_entidad_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('instituciones', fn (Blueprint $t) => $t->dropColumn('docdigital_entidad_id'));
    }
};
