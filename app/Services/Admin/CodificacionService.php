<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\Area;
use App\Models\ConfigSitio;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Services\AuditoriaService;

/**
 * Codificación de documentos y expedientes. El administrador elige el municipio (institución del Codificador del Estado) y,
 * si lo necesita, un código distinto por área (salud, educación, corporación). El identificador se arma con un patrón
 * configurable; los patrones por defecto conservan el formato que ya usaba el sistema. El esquema exacto que exigen las
 * normas técnicas (Decretos 7, 8, 10, 11 y 12) no pudo verificarse en línea: queda como A VALIDAR en docs/NORMAS_TECNICAS.md.
 */
class CodificacionService
{
    public const CLAVE_MUNICIPIO = 'municipio_codificador';

    public const CLAVE_PATRONES = 'codificacion_patrones';

    /** Normas técnicas indicadas por el municipio. El contenido NO está verificado: ver matriz de cumplimiento. */
    public const NORMAS = [
        'Decreto7' => 'https://wikiguias.digital.gob.cl/Normas/Decreto7',
        'Decreto8' => 'https://wikiguias.digital.gob.cl/Normas/Decreto8',
        'Decreto10' => 'https://wikiguias.digital.gob.cl/Normas/Decreto10',
        'Decreto11' => 'https://wikiguias.digital.gob.cl/Normas/Decreto11',
        'Decreto12' => 'https://wikiguias.digital.gob.cl/Normas/Decreto12',
    ];

    public const TOKENS_EXPEDIENTE = ['institucion', 'codigo', 'anio', 'area', 'unidad'];

    public const TOKENS_DOCUMENTO = ['institucion', 'tipo', 'anio', 'numero', 'area', 'unidad'];

    public const DEFECTO = ['expediente' => '{institucion}-{codigo}', 'documento' => '{institucion}-{tipo}-{anio}-{numero}'];

    public function __construct(private readonly AuditoriaService $audit) {}

    /** @return array{codigo:?string,nombre:?string,origen:string} */
    public static function municipio(): array
    {
        try {
            $j = json_decode((string) ConfigSitio::query()->where('clave', self::CLAVE_MUNICIPIO)->value('valor'), true);
        } catch (\Throwable) {
            $j = null;
        }
        if (is_array($j) && ! empty($j['codigo'])) {
            return ['codigo' => (string) $j['codigo'], 'nombre' => $j['nombre'] ?? null, 'origen' => (string) ($j['origen'] ?? 'catalogo')];
        }
        $env = (string) config('sgd.institucion_codigo');

        $nombre = null;
        if ($env !== '') {
            try {
                $nombre = Institucion::query()->where('codigo', $env)->value('nombre');
            } catch (\Throwable) {
            }
            $nombre ??= $env === 'PE-MUN-00243' ? 'Municipalidad de Santo Domingo' : null;
        }

        return ['codigo' => $env !== '' ? $env : null, 'nombre' => $nombre, 'origen' => $env === 'PE-MUN-00243' ? 'predeterminado' : ($env !== '' ? 'entorno' : 'sin configurar')];
    }

    /** Código de institución vigente para un área (el del área si lo tiene; si no, el del municipio; si no, «SGD»). */
    public static function institucion(?int $areaId = null): string
    {
        if ($areaId) {
            try {
                $c = Area::query()->where('id', $areaId)->value('institucion_codigo');
            } catch (\Throwable) {
                $c = null;
            }
            if ($c) {
                return (string) $c;
            }
        }

        return self::municipio()['codigo'] ?? 'SGD';
    }

    public function elegirMunicipio(Usuario $admin, string $codigo, bool $manual = false): void
    {
        $codigo = trim($codigo);
        if (! preg_match('/^[A-Za-z0-9._-]{1,30}$/', $codigo)) {
            throw new FlujoException('El código solo admite letras, números, punto, guion y guion bajo.');
        }
        $inst = Institucion::query()->where('codigo', $codigo)->first();
        if (! $inst && ! $manual) {
            throw new FlujoException('El código no está en el catálogo del Codificador cargado. Impórtelo o sincronícelo primero, o confirme el ingreso manual.');
        }
        ConfigSitio::query()->updateOrCreate(['clave' => self::CLAVE_MUNICIPIO], ['valor' => json_encode(['codigo' => $codigo, 'nombre' => $inst?->nombre, 'origen' => $inst ? 'catalogo' : 'manual']), 'actualizado_por' => $admin->id]);
        $this->audit->registrar('codificacion.municipio', 'config', self::CLAVE_MUNICIPIO, ['codigo' => $codigo, 'origen' => $inst ? 'catalogo' : 'manual']);
    }

    /** @return array{expediente:string,documento:string} */
    public static function patrones(): array
    {
        try {
            $j = json_decode((string) ConfigSitio::query()->where('clave', self::CLAVE_PATRONES)->value('valor'), true);
        } catch (\Throwable) {
            $j = null;
        }

        return [
            'expediente' => is_array($j) && ! empty($j['expediente']) ? (string) $j['expediente'] : self::DEFECTO['expediente'],
            'documento' => is_array($j) && ! empty($j['documento']) ? (string) $j['documento'] : self::DEFECTO['documento'],
        ];
    }

    /** @param array{expediente?:string,documento?:string} $in */
    public function guardarPatrones(Usuario $admin, array $in): void
    {
        $exp = trim((string) ($in['expediente'] ?? ''));
        $doc = trim((string) ($in['documento'] ?? ''));
        $this->validarPatron($exp, self::TOKENS_EXPEDIENTE, ['codigo'], 'expediente');
        $this->validarPatron($doc, self::TOKENS_DOCUMENTO, ['tipo', 'anio', 'numero'], 'documento');
        ConfigSitio::query()->updateOrCreate(['clave' => self::CLAVE_PATRONES], ['valor' => json_encode(['expediente' => $exp, 'documento' => $doc]), 'actualizado_por' => $admin->id]);
        $this->audit->registrar('codificacion.patrones', 'config', self::CLAVE_PATRONES, ['expediente' => $exp, 'documento' => $doc]);
    }

    /** @param list<string> $permitidos @param list<string> $obligatorios */
    private function validarPatron(string $p, array $permitidos, array $obligatorios, string $que): void
    {
        if ($p === '' || mb_strlen($p) > 100 || ! preg_match('/^[A-Za-z0-9{}._\/-]+$/', $p)) {
            throw new FlujoException("El patrón de $que solo admite letras, números, . _ / - y marcadores entre llaves (máx. 100 caracteres).");
        }
        preg_match_all('/\{([a-z]+)\}/', $p, $m);
        foreach ($m[1] as $t) {
            if (! in_array($t, $permitidos, true)) {
                throw new FlujoException("El marcador {{$t}} no existe para $que. Use: ".implode(', ', array_map(fn ($x) => '{'.$x.'}', $permitidos)).'.');
            }
        }
        if (preg_replace('/\{[a-z]+\}/', '', $p) !== preg_replace('/[{}]/', '', preg_replace('/\{[a-z]+\}/', '', $p))) {
            throw new FlujoException("El patrón de $que tiene llaves sin cerrar.");
        }
        foreach ($obligatorios as $o) {
            if (! in_array($o, $m[1], true)) {
                throw new FlujoException("El patrón de $que debe incluir {{$o}} para garantizar que el identificador sea único.");
            }
        }
    }

    /** @param array<string,string> $valores */
    private static function aplicar(string $patron, array $valores): string
    {
        return preg_replace_callback('/\{([a-z]+)\}/', fn ($m) => $valores[$m[1]] ?? '', $patron) ?? $patron;
    }

    public function identificadorExpediente(Expediente $e, ?string $patron = null): string
    {
        $e->loadMissing('unidad');
        $area = $e->area_id ?? $e->unidad?->area_id;
        $areaCod = $area ? (string) Area::query()->where('id', $area)->value('codigo') : '';
        $anio = preg_match('/^EXP-(\d{4})-/', (string) $e->codigo, $m) ? $m[1] : (string) now('America/Santiago')->year;

        return self::aplicar($patron ?? self::patrones()['expediente'], ['institucion' => self::institucion($area ? (int) $area : null), 'codigo' => (string) $e->codigo, 'anio' => $anio,
            'area' => strtoupper($areaCod), 'unidad' => (string) ($e->unidad?->codigo ?? $e->unidad_id)]);
    }

    public function identificadorDocumento(Documento $d, ?string $patron = null): string
    {
        $d->loadMissing('tipo', 'expediente.unidad');
        $area = $d->expediente?->area_id ?? $d->expediente?->unidad?->area_id;
        $areaCod = $area ? (string) Area::query()->where('id', $area)->value('codigo') : '';

        return self::aplicar($patron ?? self::patrones()['documento'], ['institucion' => self::institucion($area ? (int) $area : null), 'tipo' => (string) $d->tipo->prefijo, 'anio' => (string) $d->anio,
            'numero' => sprintf('%06d', (int) $d->numero), 'area' => strtoupper($areaCod), 'unidad' => (string) ($d->expediente?->unidad?->codigo ?? '')]);
    }

    /**
     * Matriz de cumplimiento de las normas técnicas. El contenido de los decretos NO pudo leerse (el entorno de desarrollo no
     * accede a wikiguias.digital.gob.cl): por eso cada fila declara lo que el sistema implementa y deja «A VALIDAR» contra el texto vigente.
     *
     * @return list<array{tema:string,control:string,estado:string,donde:string}>
     */
    public static function matrizNormas(): array
    {
        $av = 'A VALIDAR contra el texto vigente';

        return [
            ['tema' => 'Identificación única de documentos y expedientes', 'control' => 'Identificador por patrón configurable con código de institución del Codificador del Estado; correlativo único por tipo y año.', 'estado' => $av, 'donde' => 'Administración › Codificación'],
            ['tema' => 'Metadatos del expediente y del documento', 'control' => 'Metadatos MGDEE/MGDDE almacenados por expediente y documento (esquema oficial A VALIDAR).', 'estado' => $av, 'donde' => 'Expediente › Metadatos'],
            ['tema' => 'Firma electrónica simple y avanzada', 'control' => 'Firma simple (Ley 19.799) y avanzada vía FirmaGob; sello de integridad SHA-256 y código de validación público.', 'estado' => $av, 'donde' => 'Documento › Firmas'],
            ['tema' => 'Integridad, autenticidad y no repudio', 'control' => 'Cadena de auditoría con HMAC; archivos cifrados en reposo (XChaCha20-Poly1305) con hash por versión.', 'estado' => $av, 'donde' => 'Seguridad'],
            ['tema' => 'Versionado y trazabilidad', 'control' => 'Toda modificación de un archivo o documento genera una versión nueva con motivo, diferencias y responsables (editó, visó, firmó).', 'estado' => $av, 'donde' => 'Expediente › Archivos'],
            ['tema' => 'Seguridad de la información y ciberseguridad', 'control' => 'Segundo factor del administrador, CSP estricta, validación de archivos por contenido, controles OWASP (docs/PENTEST.md).', 'estado' => $av, 'donde' => 'Seguridad'],
            ['tema' => 'Interoperabilidad (Codificador, PISEE, DocDigital)', 'control' => 'Adaptadores configurables; contratos reales A VALIDAR con la documentación oficial (docs/LIMITACIONES.md).', 'estado' => $av, 'donde' => 'APIs e integraciones'],
            ['tema' => 'Calidad y funcionamiento de plataformas / accesibilidad', 'control' => 'Diseño gob.cl, WCAG 2.1 AA verificado con axe en pantallas clave.', 'estado' => $av, 'donde' => 'docs/QA_NAVEGADOR.md'],
        ];
    }
}
