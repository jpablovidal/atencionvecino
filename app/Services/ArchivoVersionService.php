<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\ArchivoVersion;
use App\Models\Usuario;
use App\Services\Firma\ArchivoFirmaService;
use App\Support\UploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Versionado de los archivos de un expediente: si un archivo cambia, el cambio es una VERSIÓN NUEVA (la anterior se conserva
 * íntegra y cifrada). Cada versión guarda quién la subió, el motivo declarado, las diferencias respecto de la anterior y las
 * firmas electrónicas que el archivo trae. La fila de `archivos` siempre representa la versión vigente.
 */
class ArchivoVersionService
{
    public function __construct(
        private readonly FileVault $vault,
        private readonly ArchivoFirmaService $firmas,
        private readonly AuditoriaService $audit,
        private readonly AccesoRestringidoService $restringidos,
    ) {}

    /** Ámbitos cuyos archivos pueden versionarse (los PDF firmados de documentos y comprobantes son inmutables). */
    public const VERSIONABLES = ['expediente', 'anexo', 'respuesta', 'docdigital'];

    /** Garantiza que exista la fila de la versión vigente (para archivos anteriores al versionado). */
    public function asegurarOrigen(Archivo $a): ArchivoVersion
    {
        $v = ArchivoVersion::query()->where('archivo_id', $a->id)->where('numero', $a->version ?: 1)->first();
        if ($v) {
            return $v;
        }
        $autor = Usuario::query()->where('run', $a->subido_por)->first();

        return ArchivoVersion::query()->create([
            'archivo_id' => $a->id, 'numero' => $a->version ?: 1, 'nombre_original' => $a->nombre_original, 'mime' => $a->mime, 'tamano' => $a->tamano, 'sha256' => $a->sha256,
            'ruta' => $a->ruta, 'motivo' => 'Versión original', 'resumen_cambios' => null, 'creado_por' => $autor?->id, 'autor_nombre' => $autor?->nombreCompleto() ?? ($a->subido_por === 'ciudadano' ? 'Solicitante' : (string) $a->subido_por),
            'autor_run' => $autor?->run, 'firma_estado' => $a->firma_estado, 'firma_detalle' => $a->firma_detalle, 'created_at' => $a->created_at,
        ]);
    }

    public function nuevaVersion(Archivo $a, UploadedFile $f, Usuario $u, string $motivo): ArchivoVersion
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) {
            throw new FlujoException('Indique el motivo del cambio (entre 5 y 500 caracteres): quedará registrado en el historial.');
        }
        if (! in_array($a->ambito, self::VERSIONABLES, true) || $a->documento_id) {
            throw new FlujoException('Este archivo no admite nuevas versiones (documento firmado o comprobante).');
        }
        $this->restringidos->exigirArchivo($u, $a, 'versionar_archivo');
        try {
            $tipo = UploadValidator::validar($f);
        } catch (\InvalidArgumentException $e) {
            throw new FlujoException($e->getMessage());
        }
        $bytes = (string) file_get_contents($f->getRealPath());
        if (hash('sha256', $bytes) === $a->sha256) {
            throw new FlujoException('El archivo es idéntico a la versión vigente: no hay cambios que versionar.');
        }
        $anterior = $this->vault->get($a->ruta, $a->sha256);
        $nuevo = $this->vault->put($bytes);
        try {
            return DB::transaction(function () use ($a, $f, $u, $motivo, $tipo, $bytes, $anterior, $nuevo) {
                $a = Archivo::query()->lockForUpdate()->findOrFail($a->id);
                $this->asegurarOrigen($a);
                $numero = ((int) ArchivoVersion::query()->where('archivo_id', $a->id)->max('numero')) + 1;
                $nombre = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename($f->getClientOriginalName())) ?: 'archivo', 0, 190);
                $a->forceFill(['nombre_original' => $nombre, 'mime' => $tipo['mime'], 'tamano' => $nuevo['tamano'], 'sha256' => $nuevo['sha256'], 'ruta' => $nuevo['ruta'], 'version' => $numero]);
                $this->firmas->analizar($a, $bytes);
                $resumen = $this->diferencias($anterior, (string) $a->getOriginal('mime'), (string) $a->getOriginal('nombre_original'), (int) $a->getOriginal('tamano'), $bytes, $tipo['mime'], $nombre, $nuevo['tamano']);
                $a->save();
                $v = ArchivoVersion::query()->create([
                    'archivo_id' => $a->id, 'numero' => $numero, 'nombre_original' => $nombre, 'mime' => $tipo['mime'], 'tamano' => $nuevo['tamano'], 'sha256' => $nuevo['sha256'], 'ruta' => $nuevo['ruta'],
                    'motivo' => $motivo, 'resumen_cambios' => json_encode($resumen, JSON_UNESCAPED_UNICODE), 'creado_por' => $u->id, 'autor_nombre' => $u->nombreCompleto(), 'autor_run' => $u->run,
                    'firma_estado' => $a->firma_estado, 'firma_detalle' => $a->firma_detalle,
                ]);
                $this->audit->registrar('archivo.version', 'archivo', $a->id, ['version' => $numero, 'sha256' => $nuevo['sha256']]);

                return $v;
            });
        } catch (\Throwable $e) {
            $this->vault->delete($nuevo['ruta']);
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> versiones, de la más nueva a la más antigua */
    public function historial(Archivo $a): array
    {
        $this->asegurarOrigen($a);

        return ArchivoVersion::query()->where('archivo_id', $a->id)->orderByDesc('numero')->get()->map(fn (ArchivoVersion $v) => [
            'v' => $v, 'vigente' => $v->numero === (int) $a->version, 'resumen' => $v->resumen() ?? [], 'firmas' => $this->firmasDe($v),
        ])->all();
    }

    /** @return list<array{nombre:?string,cadena:?string}> */
    private function firmasDe(ArchivoVersion $v): array
    {
        $d = $v->firma_detalle ? json_decode($v->firma_detalle, true) : null;

        return collect($d['firmas'] ?? [])->map(fn ($f) => ['nombre' => $f['firmante'] ?? $f['nombre'] ?? null, 'cadena' => $f['cadena'] ?? null])->all();
    }

    /**
     * Diferencias entre dos versiones. Siempre: tamaño, nombre y formato. PDF: páginas (aproximado). Texto/CSV: líneas agregadas y quitadas.
     * Para el resto de los formatos NO se compara el contenido (queda dicho en el resumen).
     *
     * @return array<string,mixed>
     */
    public function diferencias(string $antes, string $mimeAntes, string $nombreAntes, int $tamAntes, string $despues, string $mimeDespues, string $nombreDespues, int $tamDespues): array
    {
        $r = ['lineas' => []];
        $r['tamano'] = ['antes' => $tamAntes, 'despues' => $tamDespues, 'delta' => $tamDespues - $tamAntes];
        if ($nombreAntes !== $nombreDespues) {
            $r['nombre'] = ['antes' => $nombreAntes, 'despues' => $nombreDespues];
        }
        if ($mimeAntes !== $mimeDespues) {
            $r['formato'] = ['antes' => $mimeAntes, 'despues' => $mimeDespues];
        }
        if ($mimeAntes === 'application/pdf' && $mimeDespues === 'application/pdf') {
            $p = fn (string $b) => preg_match_all('#/Type\s*/Page(?![s\w])#', $b);
            $r['paginas'] = ['antes' => $p($antes), 'despues' => $p($despues)];
        }
        if (str_starts_with($mimeAntes, 'text/') && str_starts_with($mimeDespues, 'text/')) {
            $la = array_map('rtrim', preg_split('/\R/u', $antes) ?: []);
            $lb = array_map('rtrim', preg_split('/\R/u', $despues) ?: []);
            $q = array_values(array_diff($la, $lb));
            $a = array_values(array_diff($lb, $la));
            $r['texto'] = ['quitadas' => count($q), 'agregadas' => count($a), 'ejemplos_quitadas' => array_map(fn ($x) => mb_substr($x, 0, 120), array_slice($q, 0, 3)), 'ejemplos_agregadas' => array_map(fn ($x) => mb_substr($x, 0, 120), array_slice($a, 0, 3))];
        }
        $r['contenido_comparado'] = isset($r['texto']) ? 'lineas' : (isset($r['paginas']) ? 'paginas' : 'no');
        unset($r['lineas']);
        $r['texto_resumen'] = $this->frase($r);

        return $r;
    }

    /** @param array<string,mixed> $r */
    private function frase(array $r): string
    {
        $p = [];
        $d = $r['tamano']['delta'];
        $p[] = $d === 0 ? 'mismo tamaño' : ($d > 0 ? 'pesa '.number_format($d / 1024, 1, ',', '.').' KB más' : 'pesa '.number_format(-$d / 1024, 1, ',', '.').' KB menos');
        if (isset($r['paginas']) && $r['paginas']['antes'] !== $r['paginas']['despues']) {
            $p[] = 'pasó de '.$r['paginas']['antes'].' a '.$r['paginas']['despues'].' páginas';
        }
        if (isset($r['texto'])) {
            $p[] = $r['texto']['agregadas'].' línea(s) agregadas y '.$r['texto']['quitadas'].' quitadas';
        }
        if (isset($r['formato'])) {
            $p[] = 'cambió el formato';
        }
        if (isset($r['nombre'])) {
            $p[] = 'cambió el nombre del archivo';
        }
        if ($r['contenido_comparado'] === 'no') {
            $p[] = 'el contenido de este formato no se compara automáticamente';
        }

        return ucfirst(implode('; ', $p)).'.';
    }

    public function contenido(ArchivoVersion $v): string
    {
        return $this->vault->get($v->ruta, $v->sha256);
    }
}
