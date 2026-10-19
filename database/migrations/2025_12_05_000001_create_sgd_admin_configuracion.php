<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración del administrador: áreas municipales (municipal, salud, educación, corporación) y su pertenencia por usuario,
 * segundo factor (TOTP) del administrador, versiones de archivos de expedientes, eventos por versión de documento
 * (quién editó, visó o firmó) e identificador normativo de documentos y expedientes.
 */
return new class extends Migration
{
    private function tabla(string $n, callable $f): void
    {
        Schema::create($n, function (Blueprint $t) use ($f) {
            $t->engine = 'InnoDB';
            $t->charset = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';
            $f($t);
        });
    }

    public function up(): void
    {
        $this->tabla('areas', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 20)->unique(); // municipal | salud | educacion | corporacion
            $t->string('nombre', 80);
            $t->boolean('habilitada')->default(false);
            $t->string('institucion_codigo', 30)->nullable(); // código del Codificador para esta área (si difiere del municipio)
            $t->unsignedSmallInteger('orden')->default(10);
            $t->timestamps();
        });
        $now = '2025-12-05 00:00:00'; // fijo: el SQL generado debe ser reproducible
        foreach ([['municipal', 'Área municipal', true, 1], ['salud', 'Área de salud', false, 2], ['educacion', 'Área de educación', false, 3], ['corporacion', 'Corporación municipal', false, 4]] as [$c, $n, $h, $o]) {
            DB::table('areas')->insertOrIgnore(['codigo' => $c, 'nombre' => $n, 'habilitada' => $h, 'orden' => $o, 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->tabla('usuario_areas', function (Blueprint $t) {
            $t->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $t->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $t->primary(['usuario_id', 'area_id']);
        });
        Schema::table('unidades', fn (Blueprint $t) => $t->unsignedBigInteger('area_id')->nullable());
        Schema::table('expedientes', fn (Blueprint $t) => $t->unsignedBigInteger('area_id')->nullable());

        Schema::table('usuarios', function (Blueprint $t) {
            $t->text('totp_secreto')->nullable(); // cifrado con APP_KEY
            $t->timestamp('totp_confirmado_en')->nullable();
            $t->unsignedBigInteger('totp_ultimo_paso')->nullable(); // anti-reutilización del código
            $t->text('totp_respaldo')->nullable(); // JSON de hashes de códigos de respaldo
        });

        $this->tabla('archivo_versiones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('archivo_id')->constrained('archivos');
            $t->unsignedInteger('numero');
            $t->string('nombre_original', 190);
            $t->string('mime', 60);
            $t->unsignedInteger('tamano');
            $t->string('sha256', 64);
            $t->string('ruta', 80);
            $t->text('motivo')->nullable(); // explicación del cambio (la entrega quien sube la nueva versión)
            $t->text('resumen_cambios')->nullable(); // JSON: diferencias calculadas respecto de la versión anterior
            $t->unsignedBigInteger('creado_por')->nullable(); // vacío si lo subió la ciudadanía
            $t->string('autor_nombre', 160)->nullable();
            $t->string('autor_run', 12)->nullable();
            $t->string('firma_estado', 30)->nullable(); // firmas electrónicas que trae el archivo en esta versión
            $t->text('firma_detalle')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['archivo_id', 'numero']);
        });
        Schema::table('archivos', function (Blueprint $t) {
            $t->unsignedInteger('version')->default(1);
        });

        $this->tabla('documento_version_eventos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('documento_id')->constrained('documentos');
            $t->unsignedInteger('version');
            $t->string('evento', 12); // editado | visado | rechazado | firmado
            $t->foreignId('usuario_id')->constrained('usuarios');
            $t->string('detalle', 300)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['documento_id', 'version']);
        });
        Schema::table('documentos', fn (Blueprint $t) => $t->string('identificador', 120)->nullable());
    }

    public function down(): void
    {
        Schema::table('documentos', fn (Blueprint $t) => $t->dropColumn('identificador'));
        Schema::dropIfExists('documento_version_eventos');
        Schema::table('archivos', fn (Blueprint $t) => $t->dropColumn('version'));
        Schema::dropIfExists('archivo_versiones');
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn(['totp_secreto', 'totp_confirmado_en', 'totp_ultimo_paso', 'totp_respaldo']));
        Schema::table('expedientes', fn (Blueprint $t) => $t->dropColumn('area_id'));
        Schema::table('unidades', fn (Blueprint $t) => $t->dropColumn('area_id'));
        Schema::dropIfExists('usuario_areas');
        Schema::dropIfExists('areas');
    }
};
