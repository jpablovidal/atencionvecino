<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Interoperabilidad: Codificador (instituciones y procesos), PISEE (consumo de servicios) y DocDigital
 * (comunicaciones oficiales). También se genera en database/sql/01_schema.sql.
 */
return new class extends Migration
{
    private function t(string $name, callable $cb): void
    {
        Schema::create($name, function (Blueprint $t) use ($cb) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $cb($t);
        });
    }

    public function up(): void
    {
        $this->t('instituciones', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 40)->unique();
            $t->string('nombre', 190);
            $t->string('tipo', 60)->nullable();
            $t->string('codigo_padre', 40)->nullable();
            $t->boolean('activo')->default(true);
            $t->string('origen', 10)->default('manual'); // manual|csv|api
            $t->timestamp('sincronizado_en')->nullable();
            $t->timestamps();
        });

        $this->t('procesos_codificador', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 40)->unique();
            $t->string('nombre', 190);
            $t->string('institucion_codigo', 40)->nullable();
            $t->boolean('activo')->default(true);
            $t->string('origen', 10)->default('manual');
            $t->timestamp('sincronizado_en')->nullable();
            $t->timestamps();
        });

        Schema::table('tramites', function (Blueprint $t) {
            $t->string('proceso_codigo', 40)->nullable();
        });

        Schema::table('documentos', function (Blueprint $t) {
            $t->string('pdf_firmado_ruta', 80)->nullable(); // PDF devuelto por FirmaGob (FEA); lo exige DocDigital
            $t->string('pdf_firmado_sha256', 64)->nullable();
        });

        $this->t('pisee_servicios', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 40)->unique();
            $t->string('nombre', 190);
            $t->string('institucion_codigo', 40)->nullable();
            $t->string('ruta', 200); // ruta relativa al nodo PISEE configurado (lista blanca administrada)
            $t->string('metodo', 4)->default('GET');
            $t->string('param_run', 40)->nullable(); // nombre del parámetro que lleva el RUN (null = no envía RUN)
            $t->text('descripcion')->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('pisee_consultas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes');
            $t->foreignId('servicio_id')->constrained('pisee_servicios');
            $t->foreignId('usuario_id')->constrained('usuarios');
            $t->string('run_consultado', 12);
            $t->string('finalidad', 255);
            $t->string('estado', 10); // ok|error
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('error', 120)->nullable();
            $t->unsignedBigInteger('archivo_id')->nullable(); // resultado cifrado en archivos (ambito pisee)
            $t->timestamp('created_at')->useCurrent();
        });

        $this->t('comunicaciones_oficiales', function (Blueprint $t) {
            $t->id();
            $t->string('direccion', 10); // saliente|entrante
            $t->foreignId('expediente_id')->nullable()->constrained('expedientes');
            $t->foreignId('documento_id')->nullable()->constrained('documentos');
            $t->string('institucion_codigo', 40)->nullable(); // destino (saliente) u origen (entrante)
            $t->string('institucion_nombre', 190)->nullable();
            $t->string('asunto', 190);
            $t->string('estado', 10)->default('pendiente'); // pendiente|enviada|fallida|recibida
            $t->string('evidencia_tipo', 30)->nullable(); // docdigital_id|docdigital_manual|simulado
            $t->string('evidencia', 255)->nullable();
            $t->timestamp('evidencia_en')->nullable();
            $t->unsignedTinyInteger('intentos')->default(0);
            $t->string('ultimo_error', 120)->nullable();
            $t->unsignedBigInteger('archivo_id')->nullable();
            $t->foreignId('unidad_id')->nullable()->constrained('unidades');
            $t->foreignId('creado_por')->nullable()->constrained('usuarios');
            $t->timestamps();
            $t->index(['direccion', 'estado']);
            $t->unique(['direccion', 'evidencia_tipo', 'evidencia']);
        });
    }

    public function down(): void
    {
        foreach (['comunicaciones_oficiales', 'pisee_consultas', 'pisee_servicios', 'procesos_codificador', 'instituciones'] as $n) {
            Schema::dropIfExists($n);
        }
        Schema::table('documentos', fn (Blueprint $t) => $t->dropColumn(['pdf_firmado_ruta', 'pdf_firmado_sha256']));
        Schema::table('tramites', fn (Blueprint $t) => $t->dropColumn('proceso_codigo'));
    }
};
