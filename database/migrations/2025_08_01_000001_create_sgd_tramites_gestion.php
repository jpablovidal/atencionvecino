<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Gestión de trámites y servicios por cada dirección municipal: ciclo de vida (borrador → publicado → deshabilitado), dirección
 * propietaria, instrucciones, ayuda por documento requerido e historial inmutable de versiones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $t) {
            $t->string('estado', 20)->default('borrador'); // borrador | publicado | deshabilitado (activo = publicado)
            $t->foreignId('unidad_propietaria_id')->nullable()->constrained('unidades'); // dirección que lo administra
            $t->text('instrucciones')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('publicado_en')->nullable();
            $t->foreignId('actualizado_por')->nullable()->constrained('usuarios');
            $t->index('estado');
        });

        Schema::table('tramite_anexos', function (Blueprint $t) {
            $t->string('descripcion', 255)->nullable(); // ayuda para la persona que solicita
            $t->unsignedSmallInteger('orden')->default(0);
        });

        Schema::create('tramite_versiones', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('tramite_id')->constrained('tramites');
            $t->unsignedInteger('version');
            $t->string('estado', 20);
            $t->string('evento', 20); // crear | editar | publicar | deshabilitar | reactivar | duplicar
            $t->longText('datos'); // JSON con el contenido completo en ese momento
            $t->foreignId('creado_por')->nullable()->constrained('usuarios');
            $t->timestamp('created_at')->nullable();
            $t->index(['tramite_id', 'version']);
        });

        if (DB::pretending()) {
            return; // la generación de 01_schema.sql no lleva datos (una instalación nueva no tiene trámites previos)
        }

        // Instalaciones existentes: lo que estaba activo queda publicado; la dirección propietaria es la primera responsable
        DB::table('tramites')->where('activo', true)->update(['estado' => 'publicado', 'publicado_en' => now()]);
        DB::table('tramites')->where('activo', false)->update(['estado' => 'deshabilitado']);
        foreach (DB::table('tramites')->get() as $tr) {
            $u = DB::table('tramite_unidad')->where('tramite_id', $tr->id)->orderBy('unidad_id')->value('unidad_id');
            if ($u) {
                DB::table('tramites')->where('id', $tr->id)->update(['unidad_propietaria_id' => $u]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_versiones');
        Schema::table('tramite_anexos', function (Blueprint $t) {
            $t->dropColumn(['descripcion', 'orden']);
        });
        Schema::table('tramites', function (Blueprint $t) {
            $t->dropIndex(['estado']);
            $t->dropConstrainedForeignId('actualizado_por');
            $t->dropConstrainedForeignId('unidad_propietaria_id');
            $t->dropColumn(['estado', 'instrucciones', 'version', 'publicado_en']);
        });
    }
};
