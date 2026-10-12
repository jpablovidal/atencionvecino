<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Formatos y plantillas: visibilidad por unidad (toda la municipalidad o direcciones específicas: salud, educación…),
 * historial de versiones y biblioteca de imágenes del editor (cifradas en el repositorio).
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
        Schema::table('plantillas', function (Blueprint $t) {
            $t->string('descripcion', 255)->nullable();
            $t->string('alcance', 10)->default('todas'); // todas (toda la municipalidad) | unidades (solo las indicadas)
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('actualizado_por')->nullable()->constrained('usuarios');
        });

        $this->t('plantilla_unidades', function (Blueprint $t) {
            $t->foreignId('plantilla_id')->constrained('plantillas');
            $t->foreignId('unidad_id')->constrained('unidades');
            $t->primary(['plantilla_id', 'unidad_id']);
        });

        $this->t('plantilla_versiones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plantilla_id')->constrained('plantillas');
            $t->unsignedInteger('version');
            $t->string('nombre', 150);
            $t->longText('contenido_html'); // ya sanitizado
            $t->char('sha256', 64);
            $t->foreignId('creado_por')->nullable()->constrained('usuarios');
            $t->timestamp('created_at')->nullable();
            $t->unique(['plantilla_id', 'version']);
        });

        $this->t('recursos_imagen', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 120);
            $t->string('mime', 20);
            $t->unsignedSmallInteger('ancho');
            $t->unsignedSmallInteger('alto');
            $t->unsignedInteger('tamano');
            $t->char('sha256', 64)->index();
            $t->string('ruta', 255); // referencia en el repositorio cifrado (local o Drive)
            $t->foreignId('subido_por')->nullable()->constrained('usuarios');
            $t->timestamp('created_at')->nullable();
        });

        foreach (DB::table('plantillas')->get() as $p) { // instalaciones existentes: v1 = contenido actual
            DB::table('plantilla_versiones')->insert(['plantilla_id' => $p->id, 'version' => 1, 'nombre' => $p->nombre, 'contenido_html' => $p->contenido_html,
                'sha256' => hash('sha256', (string) $p->contenido_html), 'created_at' => '2026-01-01 00:00:00']);
        }
    }

    public function down(): void
    {
        foreach (['recursos_imagen', 'plantilla_versiones', 'plantilla_unidades'] as $n) {
            Schema::dropIfExists($n);
        }
        Schema::table('plantillas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('actualizado_por');
            $t->dropColumn(['descripcion', 'alcance', 'version']);
        });
    }
};
