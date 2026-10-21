<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Varios roles por usuario y permisos individuales. `usuarios.rol` sigue siendo el ROL PRINCIPAL (fija el rol base y sus invariantes: segundo
 * factor del administrador, separación de funciones); `usuario_roles` son roles ADICIONALES y `usuario_permisos` concede o deniega permisos sueltos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_roles', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $t->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $t->timestamps();
            $t->primary(['usuario_id', 'rol_id']);
        });
        Schema::create('usuario_permisos', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $t->string('permiso', 60);
            $t->string('efecto', 10); // conceder|denegar
            $t->timestamps();
            $t->primary(['usuario_id', 'permiso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_permisos');
        Schema::dropIfExists('usuario_roles');
    }
};
