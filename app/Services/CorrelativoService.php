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
    /**
     * @return array{numero:int,anio:int}
     *
     * Con reinicio anual el contador es por (tipo, año); sin reinicio es continuo (clave de año 0). El primer número de cada
     * contador es `folio_inicio`.
     */
    public function siguiente(int $tipoDocumentoId, ?int $anio = null): array
    {
        $anio ??= (int) now('America/Santiago')->year;

        // La configuración se lee ANTES de la transacción: la primera sentencia transaccional debe ser de escritura
        // (evita bloqueos por promoción de lectura a escritura en SQLite; en InnoDB es indiferente).
        $t = DB::table('tipos_documento')->where('id', $tipoDocumentoId)->first(['folio_reinicio_anual', 'folio_inicio']);
        $clave = ($t->folio_reinicio_anual ?? true) ? $anio : 0;
        $inicio = max(1, (int) ($t->folio_inicio ?? 1));

        return DB::transaction(function () use ($tipoDocumentoId, $anio, $clave, $inicio) {
            DB::table('correlativos')->insertOrIgnore(['tipo_documento_id' => $tipoDocumentoId, 'anio' => $clave, 'ultimo' => $inicio - 1]);
            DB::table('correlativos')->where('tipo_documento_id', $tipoDocumentoId)->where('anio', $clave)->increment('ultimo');
            $n = (int) DB::table('correlativos')->where('tipo_documento_id', $tipoDocumentoId)->where('anio', $clave)->lockForUpdate()->value('ultimo');

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
