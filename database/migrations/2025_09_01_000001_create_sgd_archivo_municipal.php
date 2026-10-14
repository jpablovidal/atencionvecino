<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Archivo municipal: microformas (registro, archivos maestros cifrados, índice, certificación, disposición de originales y cadena de custodia). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivo_microformas', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->string('codigo', 20)->nullable()->unique(); // MF-AAAA-NNNNNN (se asigna al crear)
            $t->string('titulo', 190);
            $t->text('descripcion')->nullable();
            $t->string('tipo_soporte', 20); // microfilm_16mm|microfilm_35mm|microficha|digital
            $t->foreignId('unidad_id')->nullable()->constrained('unidades'); // unidad productora de los documentos
            $t->string('serie', 120)->nullable();
            $t->date('fecha_desde')->nullable();
            $t->date('fecha_hasta')->nullable();
            $t->unsignedInteger('n_documentos')->default(0);
            $t->unsignedInteger('n_imagenes')->default(0);
            $t->string('ubicacion', 190)->nullable();
            $t->string('nivel_acceso', 15)->default('reservado');
            $t->string('estado', 20)->default('borrador'); // borrador|en_revision|certificada|anulada
            $t->foreignId('expediente_id')->nullable()->constrained('expedientes');
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->foreignId('certificada_por')->nullable()->constrained('usuarios');
            $t->timestamp('certificada_en')->nullable();
            $t->string('acta_numero', 60)->nullable();
            $t->foreignId('acta_archivo_id')->nullable()->constrained('archivos');
            $t->string('sha256_conjunto', 64)->nullable();
            $t->string('disposicion', 12)->default('conservar'); // conservar|eliminar|transferir
            $t->string('disposicion_estado', 12)->default('no_aplica'); // no_aplica|pendiente|autorizada|ejecutada
            $t->text('disposicion_motivo')->nullable();
            $t->foreignId('disposicion_solicitada_por')->nullable()->constrained('usuarios');
            $t->foreignId('disposicion_autorizada_por')->nullable()->constrained('usuarios');
            $t->string('disposicion_acta', 60)->nullable();
            $t->timestamp('disposicion_en')->nullable();
            $t->timestamps();
            $t->index('estado');
            $t->index('tipo_soporte');
        });
        Schema::create('archivo_microforma_archivos', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('microforma_id')->constrained('archivo_microformas');
            $t->foreignId('archivo_id')->constrained('archivos');
            $t->unsignedInteger('orden')->default(0);
            $t->unique('archivo_id');
            $t->index(['microforma_id', 'orden']);
        });
        Schema::create('archivo_microforma_items', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('microforma_id')->constrained('archivo_microformas');
            $t->unsignedInteger('orden')->default(0);
            $t->string('referencia', 120);
            $t->string('descripcion', 255)->nullable();
            $t->string('fojas', 30)->nullable();
            $t->string('posicion', 30)->nullable(); // fotograma / posición en el rollo o ficha
            $t->date('fecha_documento')->nullable();
            $t->index(['microforma_id', 'orden']);
        });
        Schema::create('archivo_eventos', function (Blueprint $t) { // cadena de custodia (solo se agregan filas)
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $t->id();
            $t->foreignId('microforma_id')->constrained('archivo_microformas');
            $t->string('tipo', 30);
            $t->text('detalle')->nullable();
            $t->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $t->timestamp('created_at')->nullable();
            $t->index(['microforma_id', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['archivo_eventos', 'archivo_microforma_items', 'archivo_microforma_archivos', 'archivo_microformas'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
