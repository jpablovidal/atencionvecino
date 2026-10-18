<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transparencia activa y pasiva (Ley 20.285). Pasiva: solicitudes de acceso a la información (trámites marcados), prórroga, requerimientos a
 * direcciones, derivación a otro órgano y feriados para el cómputo de días hábiles. Activa: calendario mensual de publicaciones obligatorias.
 */
return new class extends Migration
{
    private const ACTIVA = [
        ['MARCO', 'Marco normativo aplicable'],
        ['ESTRUCTURA', 'Estructura orgánica, facultades y funciones'],
        ['PERSONAL', 'Personal de planta, contrata y honorarios, con sus remuneraciones'],
        ['CONTRATACIONES', 'Contrataciones para el suministro de bienes y servicios'],
        ['TRANSFERENCIAS', 'Transferencias de fondos públicos'],
        ['ACTOS', 'Actos y resoluciones con efectos sobre terceros'],
        ['TRAMITES', 'Trámites y requisitos que debe cumplir el interesado'],
        ['SUBSIDIOS', 'Programas de subsidios y otros beneficios'],
        ['PARTICIPACION', 'Mecanismos de participación ciudadana'],
        ['PRESUPUESTO', 'Presupuesto asignado e informes de ejecución'],
        ['AUDITORIAS', 'Resultados de auditorías'],
        ['ENTIDADES', 'Entidades en que tiene participación, representación o intervención'],
    ];

    private function tabla(Blueprint $t): void
    {
        $t->engine = 'InnoDB';
        $t->charset = 'utf8mb4';
        $t->collation = 'utf8mb4_unicode_ci';
    }

    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $t) {
            $t->boolean('es_transparencia')->default(false); // solicitud de acceso a la información pública (Ley 20.285)
            $t->boolean('plazo_habiles')->default(false); // el plazo se cuenta en días hábiles (lunes a viernes sin feriados registrados)
        });
        Schema::table('solicitudes', function (Blueprint $t) {
            $t->unsignedTinyInteger('prorroga_dias')->nullable();
            $t->text('prorroga_motivo')->nullable();
            $t->timestamp('prorrogada_en')->nullable();
            $t->unsignedBigInteger('prorrogada_por')->nullable();
        });
        Schema::create('transparencia_requerimientos', function (Blueprint $t) {
            $this->tabla($t);
            $t->id();
            $t->foreignId('solicitud_id')->constrained('solicitudes');
            $t->foreignId('unidad_id')->constrained('unidades'); // dirección que debe responder
            $t->text('instruccion');
            $t->unsignedTinyInteger('plazo_dias');
            $t->timestamp('vence_en');
            $t->string('estado', 12)->default('pendiente'); // pendiente|respondido
            $t->text('respuesta')->nullable();
            $t->foreignId('respondido_por')->nullable()->constrained('usuarios');
            $t->timestamp('respondido_en')->nullable();
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
            $t->index(['unidad_id', 'estado']);
            $t->index(['solicitud_id']);
        });
        Schema::create('transparencia_derivaciones', function (Blueprint $t) {
            $this->tabla($t);
            $t->id();
            $t->foreignId('solicitud_id')->constrained('solicitudes');
            $t->string('organo', 190); // órgano competente al que se deriva (incompetencia)
            $t->text('motivo');
            $t->date('fecha');
            $t->string('canal', 15); // docdigital|oficio|correo|otro
            $t->string('referencia', 120)->nullable(); // N.º de oficio o ID de DocDigital
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
            $t->index(['solicitud_id']);
        });
        Schema::create('transparencia_feriados', function (Blueprint $t) {
            $this->tabla($t);
            $t->id();
            $t->date('fecha')->unique();
            $t->string('nombre', 120);
            $t->timestamps();
        });
        Schema::create('transparencia_activa_items', function (Blueprint $t) {
            $this->tabla($t);
            $t->id();
            $t->string('codigo', 30)->unique();
            $t->string('nombre', 190);
            $t->foreignId('unidad_id')->nullable()->constrained('unidades'); // dirección responsable de la información
            $t->unsignedTinyInteger('dia_limite')->default(10); // día del mes siguiente hasta el que debe estar publicada
            $t->string('url_publicacion', 300)->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
        Schema::create('transparencia_activa_registros', function (Blueprint $t) { // solo se agregan filas (historial)
            $this->tabla($t);
            $t->id();
            $t->foreignId('item_id')->constrained('transparencia_activa_items');
            $t->char('periodo', 7); // AAAA-MM de la información publicada
            $t->date('publicado_en');
            $t->string('url', 300)->nullable();
            $t->string('observacion', 300)->nullable();
            $t->foreignId('registrado_por')->constrained('usuarios');
            $t->timestamp('created_at')->nullable();
            $t->index(['item_id', 'periodo']);
        });

        // Datos base: rol del sistema y catálogo referencial de transparencia activa (A VALIDAR con la Instrucción General del CPLT)
        $now = '2026-11-01 00:00:00';
        DB::table('roles')->insertOrIgnore(['clave' => 'transparencia', 'nombre' => 'Transparencia activa y pasiva', 'descripcion' => 'Administra las solicitudes de acceso a la información, sus expedientes, requerimientos a las direcciones, plazos, la publicación de transparencia activa y los reportes.', 'rol_base' => 'transparencia', 'sistema' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now]);
        foreach (self::ACTIVA as [$codigo, $nombre]) {
            DB::table('transparencia_activa_items')->insertOrIgnore(['codigo' => $codigo, 'nombre' => $nombre, 'dia_limite' => 10, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (['transparencia_activa_registros', 'transparencia_activa_items', 'transparencia_feriados', 'transparencia_derivaciones', 'transparencia_requerimientos'] as $n) {
            Schema::dropIfExists($n);
        }
        Schema::table('solicitudes', fn (Blueprint $t) => $t->dropColumn(['prorroga_dias', 'prorroga_motivo', 'prorrogada_en', 'prorrogada_por']));
        Schema::table('tramites', fn (Blueprint $t) => $t->dropColumn(['es_transparencia', 'plazo_habiles']));
        DB::table('roles')->where('clave', 'transparencia')->where('sistema', 1)->delete();
    }
};
