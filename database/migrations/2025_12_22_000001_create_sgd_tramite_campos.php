<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constructor de formularios de los trámites: campos propios (texto, número, fecha, listas, sí/no, cálculos), listados de opciones reutilizables
 * y los datos que responde cada solicitud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listas_opciones', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('nombre', 120)->unique();
            $t->string('descripcion', 250)->nullable();
            $t->foreignId('unidad_id')->nullable()->constrained('unidades'); // dirección propietaria (quien puede editarla); null = solo administración
            $t->boolean('activa')->default(true);
            $t->foreignId('creado_por')->nullable()->constrained('usuarios');
            $t->timestamps();
        });
        Schema::create('lista_opciones_items', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('lista_id')->constrained('listas_opciones')->cascadeOnDelete();
            $t->string('valor', 60);
            $t->string('etiqueta', 190);
            $t->unsignedSmallInteger('orden')->default(0);
            $t->boolean('activo')->default(true);
            $t->unique(['lista_id', 'valor']);
        });
        Schema::create('tramite_campos', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $t->string('clave', 30);
            $t->string('etiqueta', 190);
            $t->string('tipo', 12); // texto|parrafo|numero|fecha|lista|multiple|si_no|calculo|titulo
            $t->string('ayuda', 255)->nullable();
            $t->boolean('obligatorio')->default(false);
            $t->unsignedSmallInteger('orden')->default(0);
            $t->boolean('activo')->default(true);
            $t->text('config')->nullable(); // JSON con las opciones propias de cada tipo
            $t->timestamps();
            $t->unique(['tramite_id', 'clave']);
        });
        Schema::table('solicitudes', function (Blueprint $t) {
            $t->longText('datos')->nullable(); // JSON: respuestas a los campos del trámite (con su etiqueta y texto al momento de enviar)
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', fn (Blueprint $t) => $t->dropColumn('datos'));
        Schema::dropIfExists('tramite_campos');
        Schema::dropIfExists('lista_opciones_items');
        Schema::dropIfExists('listas_opciones');
    }
};
