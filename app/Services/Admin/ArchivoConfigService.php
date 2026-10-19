<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\ConfigSitio;
use App\Models\Usuario;
use App\Services\AuditoriaService;

/**
 * Pesos máximos y formatos de archivo que administra el sistema. Se guardan en la base (`config_sitio`) y los aplica
 * `UploadValidator` en todas las subidas. PDF no puede deshabilitarse (el sistema genera y firma PDF). Los formatos se
 * validan SIEMPRE por contenido (firma binaria y, en documentos ofimáticos, estructura sin macros ni objetos incrustados).
 */
class ArchivoConfigService
{
    public const CLAVE = 'archivos_config';

    /** Techo absoluto, aun si el servidor PHP permitiera más. */
    public const TECHO_MB = 100;

    /** @var array<string,array{etiqueta:string,mime:string,ext:string,riesgo:string,defecto:bool,visible:bool}> */
    public const FORMATOS = [
        'pdf' => ['etiqueta' => 'PDF', 'mime' => 'application/pdf', 'ext' => 'pdf', 'riesgo' => 'Se rechazan PDF con JavaScript, acciones de apertura, archivos incrustados o multimedia.', 'defecto' => true, 'visible' => true],
        'jpg' => ['etiqueta' => 'Imagen JPG', 'mime' => 'image/jpeg', 'ext' => 'jpg', 'riesgo' => 'Se valida la firma binaria.', 'defecto' => true, 'visible' => true],
        'png' => ['etiqueta' => 'Imagen PNG', 'mime' => 'image/png', 'ext' => 'png', 'riesgo' => 'Se valida la firma binaria.', 'defecto' => true, 'visible' => true],
        'tif' => ['etiqueta' => 'Imagen TIFF', 'mime' => 'image/tiff', 'ext' => 'tif', 'riesgo' => 'Se valida la firma binaria. No se muestra en el navegador: solo descarga.', 'defecto' => true, 'visible' => false],
        'docx' => ['etiqueta' => 'Word (DOCX)', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'ext' => 'docx', 'riesgo' => 'Se rechazan macros y objetos incrustados. Solo descarga.', 'defecto' => false, 'visible' => false],
        'xlsx' => ['etiqueta' => 'Excel (XLSX)', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ext' => 'xlsx', 'riesgo' => 'Se rechazan macros y objetos incrustados. Solo descarga.', 'defecto' => false, 'visible' => false],
        'pptx' => ['etiqueta' => 'PowerPoint (PPTX)', 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'ext' => 'pptx', 'riesgo' => 'Se rechazan macros y objetos incrustados. Solo descarga.', 'defecto' => false, 'visible' => false],
        'odt' => ['etiqueta' => 'Texto OpenDocument (ODT)', 'mime' => 'application/vnd.oasis.opendocument.text', 'ext' => 'odt', 'riesgo' => 'Se rechazan macros y scripts. Solo descarga.', 'defecto' => false, 'visible' => false],
        'ods' => ['etiqueta' => 'Hoja OpenDocument (ODS)', 'mime' => 'application/vnd.oasis.opendocument.spreadsheet', 'ext' => 'ods', 'riesgo' => 'Se rechazan macros y scripts. Solo descarga.', 'defecto' => false, 'visible' => false],
        'txt' => ['etiqueta' => 'Texto plano (TXT)', 'mime' => 'text/plain', 'ext' => 'txt', 'riesgo' => 'Se rechaza contenido binario o con apariencia de script.', 'defecto' => false, 'visible' => true],
        'csv' => ['etiqueta' => 'Datos CSV', 'mime' => 'text/csv', 'ext' => 'csv', 'riesgo' => 'Se rechaza contenido binario. Al abrirlo en una planilla, desconfíe de celdas que comiencen con = + - @ (inyección de fórmulas).', 'defecto' => false, 'visible' => true],
    ];

    /** Ámbitos con peso máximo propio. */
    public const AMBITOS = [
        'ciudadano' => 'Archivos que adjunta la ciudadanía (solicitudes y respuestas)',
        'funcionario' => 'Archivos que cargan los funcionarios (expedientes, archivo municipal, decretos)',
    ];

    private static ?array $cache = null;

    public function __construct(private readonly AuditoriaService $audit) {}

    public static function olvidar(): void
    {
        self::$cache = null;
    }

    /** @return array{formatos:array<string,bool>,max_mb:array<string,int>,max_archivos:int} */
    public static function defecto(): array
    {
        return ['formatos' => array_map(fn ($f) => $f['defecto'], self::FORMATOS), 'max_mb' => ['ciudadano' => 10, 'funcionario' => 10], 'max_archivos' => (int) config('sgd.upload_max_files', 8)];
    }

    /** @return array{formatos:array<string,bool>,max_mb:array<string,int>,max_archivos:int} */
    public static function actual(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $d = self::defecto();
        try {
            $v = ConfigSitio::query()->where('clave', self::CLAVE)->value('valor');
            $j = $v ? json_decode((string) $v, true) : null;
        } catch (\Throwable) {
            $j = null;
        }
        if (is_array($j)) {
            foreach (array_keys(self::FORMATOS) as $k) {
                if (isset($j['formatos'][$k])) {
                    $d['formatos'][$k] = (bool) $j['formatos'][$k];
                }
            }
            foreach (array_keys(self::AMBITOS) as $k) {
                if (isset($j['max_mb'][$k]) && (int) $j['max_mb'][$k] >= 1) {
                    $d['max_mb'][$k] = min((int) $j['max_mb'][$k], self::TECHO_MB);
                }
            }
            if (isset($j['max_archivos']) && (int) $j['max_archivos'] >= 1) {
                $d['max_archivos'] = min((int) $j['max_archivos'], 30);
            }
        }
        $d['formatos']['pdf'] = true;

        return self::$cache = $d;
    }

    public static function formatoHabilitado(string $clave): bool
    {
        return (bool) (self::actual()['formatos'][$clave] ?? false);
    }

    /** Límite de PHP (upload_max_filesize y post_max_size) en bytes. */
    public static function limitePhp(): int
    {
        if (app()->runningInConsole()) { // la CLI (comandos, pruebas) no está sujeta a upload_max_filesize / post_max_size
            return self::TECHO_MB * 1048576;
        }
        $c = fn (string $k) => self::aBytes((string) ini_get($k));
        $a = $c('upload_max_filesize');
        $b = $c('post_max_size');
        $m = array_filter([$a, $b], fn ($x) => $x > 0);

        return $m ? min($m) : self::TECHO_MB * 1048576;
    }

    public static function aBytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return 0;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /** Peso máximo efectivo en bytes para un ámbito: lo configurado, sin superar el límite del servidor. */
    public static function maxBytes(string $ambito = 'funcionario'): int
    {
        $cfg = self::actual()['max_mb'][$ambito] ?? 10;

        return min($cfg * 1048576, self::limitePhp());
    }

    public static function maxArchivos(): int
    {
        return self::actual()['max_archivos'];
    }

    /** @param array<string,mixed> $in */
    public function guardar(Usuario $admin, array $in): void
    {
        $formatos = [];
        foreach (array_keys(self::FORMATOS) as $k) {
            $formatos[$k] = ! empty($in['formatos'][$k]);
        }
        $formatos['pdf'] = true;
        $mb = [];
        foreach (array_keys(self::AMBITOS) as $k) {
            $n = (int) ($in['max_mb'][$k] ?? 0);
            if ($n < 1 || $n > self::TECHO_MB) {
                throw new FlujoException('El peso máximo debe estar entre 1 y '.self::TECHO_MB.' MB.');
            }
            $mb[$k] = $n;
        }
        $ma = (int) ($in['max_archivos'] ?? 0);
        if ($ma < 1 || $ma > 30) {
            throw new FlujoException('La cantidad máxima de archivos por solicitud debe estar entre 1 y 30.');
        }
        $antes = self::actual();
        ConfigSitio::query()->updateOrCreate(['clave' => self::CLAVE], ['valor' => json_encode(['formatos' => $formatos, 'max_mb' => $mb, 'max_archivos' => $ma]), 'actualizado_por' => $admin->id]);
        self::olvidar();
        $this->audit->registrar('archivos.config', 'config', self::CLAVE, ['antes' => $antes, 'despues' => self::actual()]);
    }
}
