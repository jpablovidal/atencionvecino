<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Cuentas de acceso de PRUEBAS (usuario + contraseña). Solo operan si SGD_AMBIENTE=pruebas y la fecha SGD_ACCESO_PRUEBAS_HASTA no venció. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_prueba', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->string('run', 12)->unique();
            $t->string('tipo', 12); // funcionario | ciudadano
            $t->string('nombres', 120);
            $t->string('apellidos', 120);
            $t->string('password', 255); // hash (bcrypt/argon); nunca la clave en claro
            $t->boolean('activo')->default(true);
            $t->timestamp('ultimo_ingreso')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_prueba');
    }
};
