<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Reconocimiento de firmas electrónicas en archivos cargados y métricas históricas de consumo de recursos del servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivos', function (Blueprint $t) {
            $t->string('firma_estado', 20)->nullable(); // null = sin analizar | no_aplica | sin_firma | firmada | firmada_con_cambios | invalida | no_verificable
            $t->longText('firma_detalle')->nullable(); // JSON con el resultado del análisis
            $t->timestamp('firma_analizada_en')->nullable();
            $t->index('firma_estado');
        });

        Schema::create('metricas_recursos', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->timestamp('ts')->index();
            $t->decimal('cpu_pct', 5, 1)->nullable();
            $t->decimal('carga1', 6, 2)->nullable();
            $t->unsignedBigInteger('mem_usada')->nullable(); // bytes
            $t->unsignedBigInteger('mem_total')->nullable();
            $t->unsignedBigInteger('disco_usado')->nullable();
            $t->unsignedBigInteger('disco_total')->nullable();
            $t->unsignedBigInteger('bd_bytes')->nullable();
            $t->unsignedBigInteger('archivos_bytes')->nullable();
            $t->unsignedInteger('sesiones')->nullable();
            $t->unsignedInteger('cola_pendiente')->nullable();
            $t->unsignedInteger('cola_fallida')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_recursos');
        Schema::table('archivos', function (Blueprint $t) {
            $t->dropIndex(['firma_estado']);
            $t->dropColumn(['firma_estado', 'firma_detalle', 'firma_analizada_en']);
        });
    }
};
