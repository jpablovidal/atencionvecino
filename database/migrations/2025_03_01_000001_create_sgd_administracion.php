<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Administración del sistema: apariencia, menú, configuración de APIs, roles y permisos, firmas (estilo y posición),
 * folios por tipo de documento y repositorio (Google Drive compartido). También se genera en database/sql/01_schema.sql.
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
        $now = '2026-01-01 00:00:00';

        $this->t('config_sitio', function (Blueprint $t) {
            $t->id();
            $t->string('clave', 40)->unique(); // apariencia
            $t->longText('valor'); // JSON
            $t->foreignId('actualizado_por')->nullable()->constrained('usuarios');
            $t->timestamps();
        });

        $this->t('menu_items', function (Blueprint $t) {
            $t->id();
            $t->string('area', 10); // publico|ciudadano|app
            $t->string('clave', 40)->nullable()->unique(); // ítem de sistema (sobrescritura); null = enlace propio
            $t->string('etiqueta', 60);
            $t->string('url', 255)->nullable(); // solo enlaces propios: ruta interna (/…) o https://
            $t->boolean('nueva_pestana')->default(false);
            $t->unsignedSmallInteger('orden')->default(100);
            $t->boolean('activo')->default(true);
            $t->string('roles', 120)->nullable(); // roles base separados por coma; vacío = todos los del área
            $t->timestamps();
        });

        $this->t('api_config', function (Blueprint $t) {
            $t->id();
            $t->string('servicio', 30)->unique();
            $t->boolean('activo')->default(false); // true = la configuración de BD prevalece sobre .env
            $t->longText('valores'); // JSON, campos NO secretos
            $t->longText('secretos')->nullable(); // JSON cifrado (solo si SGD_API_SECRETS_EN_BD=1)
            $t->foreignId('actualizado_por')->nullable()->constrained('usuarios');
            $t->timestamps();
        });

        $this->t('roles', function (Blueprint $t) {
            $t->id();
            $t->string('clave', 20)->unique();
            $t->string('nombre', 80);
            $t->string('descripcion', 255)->nullable();
            $t->string('rol_base', 20); // admin|funcionario|alcalde|secretario|auditor: fija las invariantes de seguridad
            $t->boolean('sistema')->default(false);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('rol_permisos', function (Blueprint $t) {
            $t->foreignId('rol_id')->constrained('roles');
            $t->string('permiso', 60);
            $t->primary(['rol_id', 'permiso']);
        });

        Schema::table('usuarios', function (Blueprint $t) {
            $t->timestamp('ultimo_acceso')->nullable();
        });

        $this->t('firma_estilos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 80)->unique();
            $t->string('texto_encabezado', 80)->default('Firmado electrónicamente por');
            $t->boolean('mostrar_nombre')->default(true);
            $t->boolean('mostrar_cargo')->default(true);
            $t->boolean('mostrar_run')->default(false);
            $t->boolean('mostrar_fecha')->default(true);
            $t->boolean('mostrar_codigo')->default(true);
            $t->string('color_borde', 7)->default('#006fb3');
            $t->string('color_fondo', 7)->default('#ffffff');
            $t->string('color_texto', 7)->default('#111111');
            $t->unsignedTinyInteger('tamano_fuente')->default(8); // pt
            $t->unsignedSmallInteger('ancho_mm')->default(70);
            $t->unsignedSmallInteger('alto_mm')->default(28);
            $t->string('borde_estilo', 8)->default('solid'); // solid|dashed|dotted|none
            $t->string('alineacion_texto', 8)->default('left'); // left|center
            $t->boolean('defecto')->default(false);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('firma_posiciones', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 80);
            $t->foreignId('tipo_documento_id')->nullable()->constrained('tipos_documento'); // null = todos los tipos
            $t->unsignedTinyInteger('orden_firma')->default(1); // 1.ª, 2.ª, 3.ª firma
            $t->string('pagina', 8)->default('ultima'); // ultima (flujo al final) | primera (coordenadas)
            $t->string('alineacion', 10)->default('izquierda'); // izquierda|centro|derecha (pagina=ultima)
            $t->unsignedSmallInteger('margen_superior_mm')->default(12); // separación respecto del texto (ultima)
            $t->unsignedSmallInteger('x_mm')->nullable(); // pagina=primera: desde el borde izquierdo
            $t->unsignedSmallInteger('y_mm')->nullable(); // pagina=primera: desde el borde superior
            $t->foreignId('estilo_id')->nullable()->constrained('firma_estilos'); // null = estilo por defecto
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        Schema::table('tipos_documento', function (Blueprint $t) {
            $t->string('folio_formato', 80)->default('{PREFIJO} N° {N}/{AAAA}');
            $t->unsignedTinyInteger('folio_digitos')->default(2);
            $t->boolean('folio_reinicio_anual')->default(true);
            $t->unsignedInteger('folio_inicio')->default(1);
        });

        Schema::table('documentos', function (Blueprint $t) {
            $t->string('folio', 80)->nullable(); // texto del folio emitido (inmutable aunque cambie el formato)
            $t->unique(['tipo_documento_id', 'folio']);
        });

        Schema::table('archivos', function (Blueprint $t) {
            $t->string('almacen', 10)->default('local'); // local|drive
        });

        $this->t('repositorio_carpetas', function (Blueprint $t) {
            $t->id();
            $t->string('clave', 40)->unique(); // p. ej. 2026/10
            $t->string('drive_folder_id', 100);
            $t->timestamp('created_at')->useCurrent();
        });

        $this->t('repositorio_reubicaciones', function (Blueprint $t) {
            $t->id();
            $t->string('ruta_origen', 80)->unique(); // ruta local original (las referencias inmutables no se tocan)
            $t->string('ruta_destino', 80); // gd:<fileId>
            $t->char('md5', 32); // del blob cifrado
            $t->timestamp('migrado_en')->useCurrent();
        });

        // Datos base necesarios en producción (roles del sistema y estilo/posiciones de firma por defecto)
        foreach ([
            ['admin', 'Administrador', 'Gestiona mantenedores, seguridad y configuración. No lee expedientes.'],
            ['funcionario', 'Funcionario', 'Gestiona solicitudes y expedientes de su unidad.'],
            ['alcalde', 'Alcalde / alcaldesa', 'Firma decretos y crea delegaciones de firma.'],
            ['secretario', 'Secretario(a) municipal', 'Visa y firma; unidad receptora de comunicaciones oficiales.'],
            ['auditor', 'Auditor', 'Acceso de solo lectura y verificación de auditoría.'],
        ] as [$clave, $nombre, $desc]) {
            DB::table('roles')->insert(['clave' => $clave, 'nombre' => $nombre, 'descripcion' => $desc, 'rol_base' => $clave, 'sistema' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('firma_estilos')->insert(['nombre' => 'Estándar', 'defecto' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('firma_posiciones')->insert([
            ['nombre' => 'Primera firma (izquierda)', 'orden_firma' => 1, 'pagina' => 'ultima', 'alineacion' => 'izquierda', 'margen_superior_mm' => 14, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Segunda firma (derecha)', 'orden_firma' => 2, 'pagina' => 'ultima', 'alineacion' => 'derecha', 'margen_superior_mm' => 14, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('repositorio_reubicaciones');
        Schema::dropIfExists('repositorio_carpetas');
        Schema::table('archivos', fn (Blueprint $t) => $t->dropColumn('almacen'));
        Schema::table('documentos', function (Blueprint $t) {
            $t->dropUnique(['tipo_documento_id', 'folio']);
            $t->dropColumn('folio');
        });
        Schema::table('tipos_documento', fn (Blueprint $t) => $t->dropColumn(['folio_formato', 'folio_digitos', 'folio_reinicio_anual', 'folio_inicio']));
        foreach (['firma_posiciones', 'firma_estilos'] as $n) {
            Schema::dropIfExists($n);
        }
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn('ultimo_acceso'));
        foreach (['rol_permisos', 'roles', 'api_config', 'menu_items', 'config_sitio'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
