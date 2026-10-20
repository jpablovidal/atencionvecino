<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Superadministrador: administrador con acceso a TODAS las vistas del sistema (para probar y supervisar); se asigna con `sgd:superadmin` o desde Usuarios. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            $t->boolean('superadmin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn('superadmin'));
    }
};
