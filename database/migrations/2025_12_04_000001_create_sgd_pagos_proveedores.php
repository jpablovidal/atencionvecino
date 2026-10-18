<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fecha de la factura del decreto de pago: junto con el RUT y el número de factura permite al proveedor consultar su situación de pago. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decretos_pago', function (Blueprint $t) {
            $t->date('factura_fecha')->nullable();
            $t->index(['proveedor_run', 'factura_fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('decretos_pago', function (Blueprint $t) {
            $t->dropIndex(['proveedor_run', 'factura_fecha']);
            $t->dropColumn('factura_fecha');
        });
    }
};
