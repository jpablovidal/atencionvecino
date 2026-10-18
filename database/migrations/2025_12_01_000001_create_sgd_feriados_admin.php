<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Administrador de feriados: tipo y autor, y plazos en días hábiles administrativos (sin sábados, domingos ni feriados) para todos los trámites. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transparencia_feriados', function (Blueprint $t) {
            $t->string('tipo', 15)->default('nacional'); // nacional|regional|comunal|administrativo
            $t->unsignedBigInteger('creado_por')->nullable();
        });
        Schema::table('tramites', function (Blueprint $t) {
            $t->boolean('plazo_habiles')->default(true)->change();
        });
        DB::table('tramites')->update(['plazo_habiles' => true]);
    }

    public function down(): void
    {
        Schema::table('tramites', fn (Blueprint $t) => $t->boolean('plazo_habiles')->default(false)->change());
        Schema::table('transparencia_feriados', fn (Blueprint $t) => $t->dropColumn(['tipo', 'creado_por']));
    }
};
