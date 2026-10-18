<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Expedientes, documentos y archivos restringidos: marca, motivo y lista de personas autorizadas (el acceso y los intentos quedan en la bitácora de auditoría). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['expedientes', 'archivos', 'documentos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $t) {
                $t->boolean('restringido')->default(false);
                $t->string('restringido_motivo', 300)->nullable();
                $t->unsignedBigInteger('restringido_por')->nullable();
                $t->timestamp('restringido_en')->nullable();
            });
        }
        Schema::create('acceso_restringido', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes');
            $t->unsignedBigInteger('archivo_id')->nullable(); // vacío en ambos = todo el expediente
            $t->unsignedBigInteger('documento_id')->nullable();
            $t->foreignId('usuario_id')->constrained('usuarios'); // persona autorizada
            $t->foreignId('otorgado_por')->constrained('usuarios');
            $t->string('motivo', 300);
            $t->timestamp('vence_en')->nullable();
            $t->timestamp('revocado_en')->nullable();
            $t->unsignedBigInteger('revocado_por')->nullable();
            $t->timestamps();
            $t->index(['usuario_id', 'expediente_id']);
            $t->index(['expediente_id', 'revocado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceso_restringido');
        foreach (['documentos', 'archivos', 'expedientes'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn(['restringido', 'restringido_motivo', 'restringido_por', 'restringido_en']));
        }
    }
};
