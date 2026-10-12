<?php

namespace App\Services\Firma;

use App\Models\Archivo;
use App\Services\FileVault;

/** Analiza y registra si un archivo cargado está firmado electrónicamente (solo PDF/PAdES; otros formatos: no aplica). */
class ArchivoFirmaService
{
    public function __construct(private readonly FirmaPdfInspector $inspector, private readonly FileVault $vault) {}

    /** Analiza con los bytes ya en memoria (al cargar) y deja el resultado en el modelo (sin guardar). */
    public function analizar(Archivo $a, string $bytes): void
    {
        if ($a->mime !== 'application/pdf') {
            $a->firma_estado = 'no_aplica';
            $a->firma_detalle = null;
        } else {
            $r = $this->inspector->inspeccionar($bytes);
            $a->firma_estado = $r['estado'];
            $a->firma_detalle = $r['estado'] === 'sin_firma' ? null : json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $a->firma_analizada_en = now();
    }

    /** Analiza un archivo ya almacenado (descifra y verifica integridad antes). */
    public function reanalizar(Archivo $a): void
    {
        try {
            $this->analizar($a, $this->vault->get($a->ruta, $a->sha256));
        } catch (\Throwable) {
            $a->firma_estado = 'no_verificable';
            $a->firma_detalle = json_encode(['estado' => 'no_verificable', 'firmas' => [], 'observaciones' => ['No se pudo leer el archivo del repositorio.']]);
            $a->firma_analizada_en = now();
        }
        $a->save();
    }

    /** @return array{sin_analizar:int,analizados:int} */
    public function pendientes(int $limite = 50): array
    {
        $n = 0;
        foreach (Archivo::query()->whereNull('firma_estado')->orderBy('id')->limit($limite)->get() as $a) {
            $this->reanalizar($a);
            $n++;
        }

        return ['sin_analizar' => Archivo::query()->whereNull('firma_estado')->count(), 'analizados' => $n];
    }

    public static function etiqueta(?string $estado): string
    {
        return match ($estado) {
            'firmada' => 'Firmado electrónicamente',
            'firmada_con_cambios' => 'Firmado, con cambios posteriores',
            'invalida' => 'Firma inválida o documento alterado',
            'no_verificable' => 'Firma presente, no verificable',
            'sin_firma' => 'Sin firma electrónica',
            'no_aplica' => 'No aplica (no es PDF)',
            default => 'Sin analizar',
        };
    }
}
