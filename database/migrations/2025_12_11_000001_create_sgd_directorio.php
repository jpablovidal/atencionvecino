<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Usuarios creados y mantenidos a partir del directorio institucional (base «sistemas_usuarios»): siguen su alta/baja. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            $t->boolean('directorio')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn('directorio'));
    }
};
