<?php

use App\Support\Permisos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculos entre expedientes (y microformas) con incorporación de su contenido, módulo Decreto de pago, Egreso de Tesorería,
 * firmantes del módulo y firma simple o avanzada en los pasos de firma. Roles «Tesorero(a) municipal» y «Encargado(a) de pagos».
 */
return new class extends Migration
{
    private const ROLES = [
        ['tesorero', 'Tesorero(a) municipal', 'Genera el egreso de los decretos de pago (fecha, medio de pago, monto y detalle) y lo firma con firma simple o avanzada.', ['decretopago.ver', 'egresos.gestionar']],
        ['contabilidad', 'Encargado(a) de pagos', 'Carga el expediente de pago y el proceso de compra, genera o carga el decreto de pago y lo envía a visación y firma.', ['decretopago.ver', 'decretopago.gestionar']],
    ];

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
        $this->tabla('expediente_vinculos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes'); // expediente que se está gestionando
            $t->string('tipo', 12); // expediente | microforma
            $t->unsignedBigInteger('origen_expediente_id')->nullable();
            $t->unsignedBigInteger('microforma_id')->nullable();
            $t->string('motivo', 300);
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
            $t->index(['expediente_id', 'tipo']);
            $t->index('origen_expediente_id');
        });
        Schema::table('archivos', function (Blueprint $t) {
            $t->unsignedBigInteger('vinculo_id')->nullable(); // copia incorporada desde un vínculo
            $t->string('origen_descripcion', 200)->nullable();
            $t->string('origen_sha256', 64)->nullable();
        });
        $this->tabla('decretos_pago', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained('expedientes'); // expediente de pago
            $t->unsignedBigInteger('compra_expediente_id')->nullable(); // expediente del proceso de compra
            $t->string('proveedor_nombre', 160);
            $t->string('proveedor_run', 12)->nullable();
            $t->string('concepto', 500);
            $t->unsignedBigInteger('monto');
            $t->string('imputacion', 160)->nullable();
            $t->string('orden_compra', 60)->nullable();
            $t->string('factura', 60)->nullable();
            $t->date('fecha')->nullable();
            $t->string('estado', 10)->default('abierto'); // abierto | anulado
            $t->string('origen', 10)->nullable(); // generado | cargado
            $t->unsignedBigInteger('documento_id')->nullable();
            $t->unsignedBigInteger('archivo_id')->nullable();
            $t->string('numero_externo', 40)->nullable();
            $t->string('anulado_motivo', 300)->nullable();
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
            $t->index(['estado', 'id']);
            $t->index('expediente_id');
        });
        $this->tabla('egresos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('decreto_pago_id')->constrained('decretos_pago');
            $t->date('fecha');
            $t->string('medio_pago', 20);
            $t->unsignedBigInteger('monto');
            $t->string('detalle', 1000);
            $t->string('referencia', 80)->nullable();
            $t->string('cuenta', 120)->nullable();
            $t->string('estado', 10)->default('abierto'); // abierto | anulado
            $t->unsignedBigInteger('documento_id')->nullable();
            $t->string('anulado_motivo', 300)->nullable();
            $t->foreignId('creado_por')->constrained('usuarios');
            $t->timestamps();
            $t->index('decreto_pago_id');
        });
        $this->tabla('pago_firmantes', function (Blueprint $t) {
            $t->id();
            $t->string('documento', 14); // decreto_pago | egreso
            $t->foreignId('usuario_id')->constrained('usuarios');
            $t->string('cargo', 120);
            $t->string('funcion', 10); // visacion | firma
            $t->string('firma_tipo', 10)->default('avanzada'); // avanzada | simple | ambas
            $t->unsignedSmallInteger('orden')->default(1);
            $t->boolean('activo')->default(true);
            $t->timestamps();
            $t->index(['documento', 'activo', 'orden']);
        });
        Schema::table('documento_pasos', function (Blueprint $t) {
            $t->string('firma_permitida', 10)->default('avanzada'); // avanzada | simple | ambas
            $t->string('firma_tipo', 10)->nullable(); // la efectivamente usada
        });
        Schema::table('tipos_documento', function (Blueprint $t) {
            $t->boolean('permite_firma_simple')->default(false);
        });

        $now = '2026-12-03 00:00:00'; // fijo: el SQL generado debe ser reproducible
        foreach (self::ROLES as [$clave, $nombre, $desc, $extra]) {
            DB::table('roles')->insertOrIgnore(['clave' => $clave, 'nombre' => $nombre, 'descripcion' => $desc, 'rol_base' => 'funcionario', 'sistema' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $id = DB::table('roles')->where('clave', $clave)->value('id');
            if ($id) {
                foreach (array_unique([...Permisos::deBase('funcionario'), ...$extra]) as $p) {
                    DB::table('rol_permisos')->insertOrIgnore(['rol_id' => $id, 'permiso' => $p]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::ROLES as [$clave]) {
            $id = DB::table('roles')->where('clave', $clave)->where('sistema', 1)->value('id');
            if ($id) {
                DB::table('rol_permisos')->where('rol_id', $id)->delete();
                DB::table('roles')->where('id', $id)->delete();
            }
        }
        Schema::table('tipos_documento', fn (Blueprint $t) => $t->dropColumn('permite_firma_simple'));
        Schema::table('documento_pasos', fn (Blueprint $t) => $t->dropColumn(['firma_permitida', 'firma_tipo']));
        Schema::table('archivos', fn (Blueprint $t) => $t->dropColumn(['vinculo_id', 'origen_descripcion', 'origen_sha256']));
        foreach (['pago_firmantes', 'egresos', 'decretos_pago', 'expediente_vinculos'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
