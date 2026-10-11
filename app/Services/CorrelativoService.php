<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Número correlativo por tipo de documento y año calendario (hora de Chile).
 * Atómico: el UPDATE toma el bloqueo de fila InnoDB hasta el COMMIT de la transacción llamadora,
 * por lo que no hay duplicados; si la transacción falla, el número se revierte (sin huecos).
 */
class CorrelativoService
{
    /** @return array{numero:int,anio:int} */
    public function siguiente(int $tipoDocumentoId, ?int $anio = null): array
    {
        $anio ??= (int) now('America/Santiago')->year;

        return DB::transaction(function () use ($tipoDocumentoId, $anio) {
            DB::table('correlativos')->insertOrIgnore(['tipo_documento_id' => $tipoDocumentoId, 'anio' => $anio, 'ultimo' => 0]);
            DB::table('correlativos')->where('tipo_documento_id', $tipoDocumentoId)->where('anio', $anio)->increment('ultimo');
            $n = (int) DB::table('correlativos')->where('tipo_documento_id', $tipoDocumentoId)->where('anio', $anio)->lockForUpdate()->value('ultimo');

            return ['numero' => $n, 'anio' => $anio];
        });
    }

    /** Código de validación: 12 símbolos de 5 bits = 60 bits de entropía. Formato SDD-XXXX-XXXX-XXXX. */
    public static function generarCodigo(): string
    {
        $alfabeto = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 32 símbolos, sin 0/O/1/I
        $bytes = random_bytes(8);
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alfabeto[bindec(substr($bits, $i * 5, 5))];
        }

        return 'SDD-'.implode('-', str_split($out, 4));
    }
}
