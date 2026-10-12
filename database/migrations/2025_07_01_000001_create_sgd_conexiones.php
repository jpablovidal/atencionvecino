<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Conexiones hacia y desde el sistema: contadores por hora (no se guarda cada petición ni su contenido) para el mapa y el ranking de IP,
 * y caché de resolución DNS de los destinos salientes. Se depuran según la retención configurada (90 días por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conexiones_resumen', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->dateTime('bucket'); // inicio de la hora
            $t->string('direccion', 10); // entrante | saliente
            $t->string('clave', 190); // entrante: IP del cliente · saliente: host de destino
            $t->string('grupo', 20); // publico | ingreso | ciudadanos | funcionarios | integracion
            $t->unsignedInteger('hits')->default(0);
            $t->unsignedInteger('errores')->default(0); // respuestas >= 400 (o fallos de red al salir)
            $t->timestamp('ultimo')->nullable();
            $t->unique(['bucket', 'direccion', 'clave', 'grupo'], 'conexiones_uniq');
            $t->index(['direccion', 'bucket']);
        });

        Schema::create('geo_dns_cache', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->string('host', 190)->primary();
            $t->string('ip', 45)->nullable();
            $t->string('pais', 2)->nullable();
            $t->timestamp('resuelto_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_dns_cache');
        Schema::dropIfExists('conexiones_resumen');
    }
};
