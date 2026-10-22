<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Directorio de DocDigital: entidades y personas habilitadas (GET /entidades/ y GET /usuarios/ de la API v3), cargado automáticamente
 * y usado para despachar, derivar y gestionar. El RUN de las personas NO se guarda en claro: solo su huella (HMAC) y una versión enmascarada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docdigital_entidades', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('entidad_id')->unique();
            $t->string('nombre', 255);
            $t->unsignedBigInteger('padre_id')->nullable()->index();
            $t->unsignedBigInteger('organismo_id')->nullable();
            $t->string('organismo_nombre', 255)->nullable();
            $t->boolean('vigente')->default(true);
            $t->timestamp('sincronizado_en')->nullable();
            $t->timestamps();
            $t->index('nombre');
        });
        Schema::create('docdigital_usuarios', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('usuario_id')->unique();
            $t->char('run_huella', 64)->nullable()->index();
            $t->string('run_mascara', 16)->nullable();
            $t->string('nombre', 190);
            $t->string('email', 190)->nullable();
            $t->string('cargo', 190)->nullable();
            $t->unsignedBigInteger('entidad_id')->nullable()->index();
            $t->string('entidad_nombre', 255)->nullable();
            $t->unsignedBigInteger('organismo_id')->nullable();
            $t->string('organismo_nombre', 255)->nullable();
            $t->string('roles', 12)->nullable(); // tipos de rol de DocDigital separados por coma: 1 tramitador · 2 OP salida · 3 OP entrada · 4 administrador
            $t->boolean('vigente')->default(true);
            $t->timestamp('sincronizado_en')->nullable();
            $t->timestamps();
            $t->index('nombre');
        });
        Schema::table('comunicaciones_oficiales', function (Blueprint $t) {
            $t->unsignedBigInteger('destino_entidad_id')->nullable()->after('institucion_nombre'); // entidad de DocDigital cuando no está en el Codificador
            $t->text('destino_usuarios')->nullable()->after('destino_entidad_id'); // JSON [{id,nombre}] de personas destinatarias
        });
    }

    public function down(): void
    {
        Schema::table('comunicaciones_oficiales', fn (Blueprint $t) => $t->dropColumn(['destino_entidad_id', 'destino_usuarios']));
        Schema::dropIfExists('docdigital_usuarios');
        Schema::dropIfExists('docdigital_entidades');
    }
};
