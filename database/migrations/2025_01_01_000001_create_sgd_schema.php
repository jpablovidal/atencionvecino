<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Esquema del SGD. database/sql/01_schema.sql se GENERA desde esta migración
 * con scripts/generate-schema-sql.php (una prueba verifica que no haya deriva).
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
        $this->t('sessions', function (Blueprint $t) {
            $t->string('id', 64)->primary(); // SHA-256 hex del token: el token nunca se guarda
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        $this->t('unidades', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 30)->unique();
            $t->string('nombre', 150);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('usuarios', function (Blueprint $t) {
            $t->id();
            $t->string('run', 12)->unique(); // formato 12345678-9
            $t->string('nombres', 120);
            $t->string('apellidos', 120);
            $t->string('correo', 190);
            $t->string('cargo', 120)->nullable();
            $t->string('rol', 20); // admin|funcionario|alcalde|secretario|auditor
            $t->foreignId('unidad_id')->nullable()->constrained('unidades');
            $t->boolean('puede_firmar')->default(false);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('tramites', function (Blueprint $t) {
            $t->id();
            $t->string('codigo_interno', 40)->unique();
            $t->string('nombre', 190);
            $t->text('descripcion')->nullable();
            $t->string('codigo_cpat', 30)->nullable();
            $t->unsignedSmallInteger('plazo_dias')->default(20);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('tramite_unidad', function (Blueprint $t) {
            $t->foreignId('tramite_id')->constrained('tramites');
            $t->foreignId('unidad_id')->constrained('unidades');
            $t->primary(['tramite_id', 'unidad_id']);
        });

        $this->t('tramite_anexos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tramite_id')->constrained('tramites');
            $t->string('nombre', 190);
            $t->boolean('obligatorio')->default(true);
            $t->boolean('activo')->default(true);
        });

        $this->t('tipos_documento', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 30)->unique();
            $t->string('nombre', 120);
            $t->string('prefijo', 10);
            $t->boolean('firma_alcalde')->default(false); // 1ª firma solo alcalde o delegado vigente
            $t->unsignedTinyInteger('firmas_agil')->default(1);
            $t->unsignedTinyInteger('firmas_clasico')->default(2);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('plantillas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tipo_documento_id')->constrained('tipos_documento');
            $t->string('nombre', 150);
            $t->longText('contenido_html');
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('delegaciones_firma', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tipo_documento_id')->constrained('tipos_documento');
            $t->foreignId('delegado_id')->constrained('usuarios');
            $t->string('decreto_referencia', 120);
            $t->date('vigente_desde');
            $t->date('vigente_hasta');
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $this->t('solicitudes', function (Blueprint $t) {
            $t->id();
            $t->string('folio', 30)->unique();
            $t->string('run', 12)->index();
            $t->string('nombres', 120);
            $t->string('apellidos', 120);
            $t->string('correo', 190);
            $t->foreignId('tramite_id')->constrained('tramites');
            $t->text('detalle');
            $t->string('canal_notificacion', 15); // correo|presencial|casilla_unica
            $t->string('estado', 15)->default('ingresada'); // ingresada|en_tramite|devuelta|finalizada
            $t->unsignedBigInteger('expediente_id')->nullable()->index(); // sin FK: referencia circular con expedientes
            $t->timestamps();
            $t->index(['tramite_id', 'estado']);
        });

        $this->t('expedientes', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 40)->unique();
            $t->foreignId('solicitud_id')->nullable()->unique()->constrained('solicitudes');
            $t->foreignId('unidad_id')->constrained('unidades');
            $t->foreignId('responsable_id')->constrained('usuarios');
            $t->string('estado', 15)->default('abierto'); // abierto|cerrado
            $t->json('metadatos'); // MGDEE
            $t->text('motivo_reapertura')->nullable();
            $t->timestamp('cerrado_en')->nullable();
            $t->timestamps();
        });

        $this->t('devoluciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('solicitud_id')->constrained('solicitudes');
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->text('observacion');
            $t->unsignedTinyInteger('plazo_dias');
            $t->timestamp('vence_en');
            $t->text('respuesta')->nullable();
            $t->timestamp('respondida_en')->nullable();
            $t->timestamps();
        });

        $this->t('documentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes');
            $t->foreignId('tipo_documento_id')->constrained('tipos_documento');
            $t->string('titulo', 190);
            $t->text('resumen')->nullable();
            $t->string('palabras_clave', 255)->nullable();
            $t->string('nivel_acceso', 15)->default('reservado'); // publico|reservado
            $t->boolean('sensible')->default(false);
            $t->string('estado', 15)->default('borrador'); // borrador|en_flujo|vigente|anulado
            $t->string('flujo', 10)->default('agil'); // agil|clasico
            $t->unsignedInteger('version_actual')->default(1);
            $t->longText('html');
            $t->unsignedInteger('numero')->nullable();
            $t->unsignedSmallInteger('anio')->nullable();
            $t->timestamp('fecha_firma')->nullable();
            $t->string('codigo_validacion', 20)->nullable()->unique();
            $t->string('pdf_ruta', 80)->nullable();
            $t->string('pdf_sha256', 64)->nullable();
            $t->json('metadatos')->nullable(); // MGDDE
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
            $t->unique(['tipo_documento_id', 'anio', 'numero']);
            $t->index(['expediente_id', 'estado']);
        });

        $this->t('documento_versiones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('documento_id')->constrained('documentos');
            $t->unsignedInteger('version');
            $t->longText('html');
            $t->string('html_sha256', 64);
            $t->string('pdf_ruta', 80)->nullable();
            $t->string('pdf_sha256', 64)->nullable();
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['documento_id', 'version']);
        });

        $this->t('documento_pasos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('documento_id')->constrained('documentos');
            $t->unsignedTinyInteger('orden');
            $t->string('tipo', 10); // visacion|firma
            $t->foreignId('usuario_id')->constrained('usuarios');
            $t->string('estado', 12)->default('pendiente'); // pendiente|completado|rechazado
            $t->boolean('simulada')->default(false);
            $t->string('checksum_original', 64)->nullable();
            $t->text('comentario')->nullable();
            $t->timestamp('completado_en')->nullable();
            $t->timestamps();
            $t->unique(['documento_id', 'orden']);
        });

        $this->t('correlativos', function (Blueprint $t) {
            $t->foreignId('tipo_documento_id')->constrained('tipos_documento');
            $t->unsignedSmallInteger('anio');
            $t->unsignedInteger('ultimo')->default(0);
            $t->primary(['tipo_documento_id', 'anio']);
        });

        $this->t('archivos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('solicitud_id')->nullable()->constrained('solicitudes');
            $t->foreignId('expediente_id')->nullable()->constrained('expedientes');
            $t->foreignId('documento_id')->nullable()->constrained('documentos');
            $t->string('ambito', 15); // anexo|respuesta|expediente|firmado
            $t->string('nombre_original', 190);
            $t->string('mime', 60);
            $t->unsignedInteger('tamano');
            $t->string('sha256', 64);
            $t->string('ruta', 80)->unique();
            $t->string('titulo', 190)->nullable();
            $t->text('resumen')->nullable();
            $t->string('palabras_clave', 255)->nullable();
            $t->string('nivel_acceso', 15)->default('reservado');
            $t->boolean('sensible')->default(false);
            $t->string('subido_por', 12);
            $t->timestamp('created_at')->useCurrent();
        });

        $this->t('comentarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes');
            $t->foreignId('documento_id')->nullable()->constrained('documentos');
            $t->foreignId('usuario_id')->constrained('usuarios');
            $t->string('tipo', 12)->default('comentario'); // comentario|observacion
            $t->text('texto');
            $t->timestamp('created_at')->useCurrent();
        });

        $this->t('derivaciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes');
            $t->foreignId('de_usuario_id')->constrained('usuarios');
            $t->foreignId('a_usuario_id')->constrained('usuarios');
            $t->text('motivo');
            $t->timestamp('created_at')->useCurrent();
        });

        $this->t('notificaciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes');
            $t->foreignId('documento_id')->nullable()->constrained('documentos');
            $t->foreignId('solicitud_id')->constrained('solicitudes');
            $t->string('canal', 15); // casilla_unica|correo|presencial
            $t->string('estado', 12)->default('pendiente'); // pendiente|enviada|fallida
            $t->string('destinatario_run', 12);
            $t->string('destinatario_correo', 190)->nullable();
            $t->string('evidencia_tipo', 30)->nullable(); // gmail_message_id|folio_casilla|acta_presencial
            $t->string('evidencia', 255)->nullable();
            $t->timestamp('evidencia_en')->nullable();
            $t->unsignedTinyInteger('intentos')->default(0);
            $t->string('ultimo_error', 120)->nullable();
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
        });

        $this->t('audit_log', function (Blueprint $t) {
            $t->id();
            $t->timestamp('ts', 6)->useCurrent();
            $t->string('actor_run', 12)->nullable();
            $t->string('actor_rol', 20)->nullable();
            $t->string('accion', 60)->index();
            $t->string('entidad', 40)->nullable();
            $t->string('entidad_id', 40)->nullable();
            $t->string('ip', 45)->nullable();
            $t->boolean('sensible')->default(false);
            $t->text('detalle')->nullable();
            $t->char('prev_hmac', 64);
            $t->char('hmac', 64);
        });

        $this->t('incidentes', function (Blueprint $t) {
            $t->id();
            $t->string('titulo', 190);
            $t->text('descripcion');
            $t->string('severidad', 10); // baja|media|alta|critica
            $t->string('estado', 12)->default('abierto'); // abierto|contenido|cerrado
            $t->timestamp('detectado_en');
            $t->boolean('reportable_csirt')->default(false);
            $t->timestamp('alerta_temprana_en')->nullable();
            $t->timestamp('segundo_reporte_en')->nullable();
            $t->timestamp('informe_final_en')->nullable();
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['incidentes', 'audit_log', 'notificaciones', 'derivaciones', 'comentarios', 'archivos', 'correlativos',
            'documento_pasos', 'documento_versiones', 'documentos', 'devoluciones'] as $n) {
            Schema::dropIfExists($n);
        }
        foreach (['expedientes', 'solicitudes', 'delegaciones_firma', 'plantillas', 'tipos_documento', 'tramite_anexos',
            'tramite_unidad', 'tramites', 'usuarios', 'unidades', 'sessions'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
