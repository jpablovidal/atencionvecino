<?php

namespace App\Services\Interop;

use App\Exceptions\FlujoException;
use App\Models\Institucion;
use App\Models\ProcesoCodificador;
use App\Services\Admin\CodificacionService;
use App\Services\AuditoriaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Codificador de instituciones y procesos: catálogo local para identificar de forma normalizada a las
 * instituciones del Estado (destinatarios de DocDigital, consumidores/proveedores PISEE) y los procesos de los trámites.
 * Carga por CSV (administrador) o sincronización por API (forma del JSON A VALIDAR). Sin borrado: se desactiva.
 */
class CodificadorService
{
    private const CODIGO = '/^[A-Za-z0-9][A-Za-z0-9._\-]{0,39}$/';

    public function __construct(private readonly AuditoriaService $audit) {}

    /**
     * CSV UTF-8 con cabecera. Formatos admitidos:
     *  - sencillo: codigo,nombre[,tipo,codigo_padre] (instituciones) o codigo,nombre[,institucion_codigo] (procesos), separado por coma;
     *  - oficial: «Listado de códigos de Instituciones» del Codificador (separado por «;», columnas id;codigo;descripcion;…;padre;…;vigente;…;tipo;…), con
     *    `padre` como id numérico, vigencia (`vigente` y `fecha_fin`) y datos territoriales. Se detecta por la cabecera.
     *
     * @return array{creados:int,actualizados:int,errores:array<int,string>}
     */
    public function importarCsv(string $entidad, string $contenido, string $origen = 'csv'): array
    {
        if (! in_array($entidad, ['instituciones', 'procesos'], true)) {
            throw new FlujoException('Entidad inválida.');
        }
        if (strlen($contenido) > config('sgd.codificador.max_bytes') || ! mb_check_encoding($contenido, 'UTF-8')) {
            throw new FlujoException('El archivo debe ser UTF-8 y de hasta 5 MB.');
        }
        $contenido = ltrim($contenido, "\xEF\xBB\xBF");
        $primera = (string) strtok($contenido, "\n");
        $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        $h = fopen('php://temp', 'r+');
        fwrite($h, $contenido);
        rewind($h);
        $cab = array_map(fn ($c) => strtolower(trim((string) $c)), (array) fgetcsv($h, 0, $sep, '"', ''));
        $oficial = in_array('descripcion', $cab, true) && in_array('id', $cab, true) && in_array('padre', $cab, true);
        if (! in_array('codigo', $cab, true) || (! in_array('nombre', $cab, true) && ! in_array('descripcion', $cab, true))) {
            fclose($h);
            throw new FlujoException('La cabecera debe incluir "codigo" y "nombre" (o "descripcion", como en el listado oficial del Codificador).');
        }
        $filas = [];
        while (($v = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            if ($v === [null] || count(array_filter($v, fn ($x) => trim((string) $x) !== '')) === 0) {
                continue;
            }
            $filas[] = array_combine($cab, array_pad(array_slice($v, 0, count($cab)), count($cab), null));
        }
        fclose($h);
        if ($entidad === 'instituciones' && $oficial) {
            $filas = $this->filasOficiales($filas);
        } elseif (in_array('descripcion', $cab, true)) {
            $filas = array_map(fn ($f) => $f + ['nombre' => $f['descripcion'] ?? null], $filas);
        }

        return $this->upsert($entidad, $filas, $origen);
    }

    /**
     * Pasa las filas del listado oficial al formato interno: código padre (el CSV trae el id), OID, tipo, vigencia y datos territoriales.
     *
     * @param  array<int,array<string,mixed>>  $filas
     * @return array<int,array<string,mixed>>
     */
    private function filasOficiales(array $filas): array
    {
        $porId = [];
        foreach ($filas as $f) {
            if (isset($f['id']) && $f['id'] !== '') {
                $porId[(int) $f['id']] = trim((string) $f['codigo']);
            }
        }

        return array_map(function ($f) use ($porId) {
            $codigo = trim((string) $f['codigo']);
            $padre = (int) ($f['padre'] ?? 0);
            $vigente = strtolower(trim((string) ($f['vigente'] ?? 'true'))) !== 'false' && self::vigentePorFecha($f['fecha_fin'] ?? null);
            $extra = array_filter([
                'poder' => $f['poder_del_estado'] ?? null, 'tipo' => $f['tipo'] ?? null, 'nivel' => isset($f['nivel']) && $f['nivel'] !== '' ? (int) $f['nivel'] : null, 'sigla' => $f['sigla'] ?? null,
                'dr' => $f['sigla_relacion'] ?? null, 'region' => $f['cod_region'] ?? null, 'provincia' => $f['cod_provincia'] ?? null, 'comuna' => self::comuna($f['cod_comuna'] ?? null),
                'grupo' => $f['grupo_clasif_oae'] ?? null, 'afecta_ley_21180' => ($f['afecta_ley_21180'] ?? '') === '1' ? true : null, 'oae_principal' => ($f['oae_principal'] ?? '') === '1' ? true : null,
            ], fn ($v) => $v !== null && $v !== '');

            return ['codigo' => $codigo, 'nombre' => $f['descripcion'] ?? null, 'tipo' => ($f['tipo'] ?? '') !== '' ? $f['tipo'] : self::tipoDeCodigo($codigo), 'codigo_padre' => $padre > 0 ? ($porId[$padre] ?? null) : null,
                'codificador_id' => isset($f['id']) && $f['id'] !== '' ? (int) $f['id'] : null, 'oid' => ($f['idcodificacion'] ?? '') !== '' ? $f['idcodificacion'].'.'.$codigo : null,
                'info_extra' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null, 'activo' => $vigente];
        }, $filas);
    }

    /** Código de comuna a 5 dígitos: el listado lo entrega como número (5606 en vez de 05606). */
    public static function comuna(mixed $c): ?string
    {
        $c = trim((string) $c);

        return $c !== '' && ctype_digit($c) ? str_pad($c, 5, '0', STR_PAD_LEFT) : null;
    }

    /** Tipo de institución según el código (PE-MUN-00243 → MUN), o null si el código no sigue ese formato. */
    public static function tipoDeCodigo(string $codigo): ?string
    {
        return preg_match('/^[A-Z]{2}-([A-Z]{2,5})-\d+$/', $codigo, $m) ? $m[1] : null;
    }

    /** ¿La fecha de fin de vigencia (p. ej. «2999-12-30 21:00:00 -0300 -03» o «2999-12-30T21:00:00-03:00») es futura? Sin fecha o ilegible: vigente. */
    public static function vigentePorFecha(mixed $fin): bool
    {
        $fin = trim((string) $fin);
        if ($fin === '' || ! preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(?:\.\d+)?\s*(Z|[+-]\d{2}:?\d{2})?/', $fin, $m)) {
            return true;
        }
        try {
            return Carbon::parse($m[1].' '.$m[2].' '.($m[3] ?? '+0000'))->isFuture();
        } catch (\Throwable) {
            return true;
        }
    }

    /** @param array<int,array<string,mixed>> $filas */
    public function upsert(string $entidad, array $filas, string $origen): array
    {
        $res = ['creados' => 0, 'actualizados' => 0, 'errores' => []];
        if (count($filas) > 20000) {
            throw new FlujoException('Máximo 20.000 filas por carga.');
        }
        DB::transaction(function () use ($entidad, $filas, $origen, &$res) {
            $existentes = ($entidad === 'instituciones' ? Institucion::query() : ProcesoCodificador::query())->get()->keyBy('codigo');
            foreach ($filas as $i => $f) {
                $codigo = trim((string) ($f['codigo'] ?? ''));
                $nombre = trim((string) ($f['nombre'] ?? ''));
                if (! preg_match(self::CODIGO, $codigo) || $nombre === '' || mb_strlen($nombre) > 190) {
                    $res['errores'][] = 'Fila '.($i + 2).': código o nombre inválido';

                    continue;
                }
                $datos = ['nombre' => $nombre, 'origen' => $origen, 'sincronizado_en' => now(), 'activo' => array_key_exists('activo', $f) ? (bool) $f['activo'] : true];
                $m = $existentes->get($codigo);
                if ($entidad === 'instituciones') {
                    $datos += ['tipo' => $this->corto($f['tipo'] ?? null, 60), 'codigo_padre' => $this->corto($f['codigo_padre'] ?? null, 40)];
                    foreach (['codificador_id' => null, 'oid' => 120, 'info_extra' => 4000] as $k => $max) {
                        if (isset($f[$k]) && $f[$k] !== '') {
                            $datos[$k] = $max === null ? (int) $f[$k] : $this->corto($f[$k], $max);
                        }
                    }
                    if (isset($datos['info_extra']) && $m && $m->info_extra) { // se conserva lo que ya se sabe (p. ej. nombres de región y comuna) y se actualiza con lo nuevo
                        $datos['info_extra'] = json_encode(array_merge((array) json_decode((string) $m->info_extra, true), (array) json_decode($datos['info_extra'], true)), JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $datos += ['institucion_codigo' => $this->corto($f['institucion_codigo'] ?? null, 40)];
                }
                if ($m) {
                    $m->fill($datos)->save();
                    $res['actualizados']++;
                } else {
                    $nuevo = ($entidad === 'instituciones' ? new Institucion : new ProcesoCodificador)->fill($datos + ['codigo' => $codigo]);
                    $nuevo->save();
                    $existentes->put($codigo, $nuevo);
                    $res['creados']++;
                }
            }
        });
        $this->audit->registrar('codificador.carga', $entidad, null, ['origen' => $origen, 'creados' => $res['creados'], 'actualizados' => $res['actualizados'], 'errores' => count($res['errores'])]);

        return $res;
    }

    private function corto(mixed $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /**
     * Sincronización por API. Se espera JSON {"instituciones":[{codigo,nombre,tipo?,codigo_padre?}], "procesos":[{codigo,nombre,institucion_codigo?}]}
     * — forma A VALIDAR con la fuente oficial; ajuste este método si difiere.
     *
     * @return array<string,array{creados:int,actualizados:int,errores:array<int,string>}>
     */
    public function sincronizar(): array
    {
        $c = config('sgd.codificador');
        if ($c['mode'] !== 'api') {
            throw new FlujoException('Sincronización por API no configurada: elija el modo «Sincronización por API» en Administración → APIs → Codificador.');
        }
        $api = app(CodificadorApi::class);
        if ($api->esOficial()) {
            return $this->sincronizarOficial($api);
        }
        if (! str_starts_with((string) $c['api_url'], 'https://')) {
            throw new FlujoException('La URL de la API del Codificador debe ser https.');
        }
        try {
            $r = Http::timeout((int) $c['timeout'])->connectTimeout(5)->withoutRedirecting()->acceptJson()
                ->when($c['api_token'], fn ($h) => $h->withToken((string) $c['api_token']))->get($c['api_url']);
        } catch (\Throwable) {
            throw new FlujoException('No fue posible contactar el Codificador.');
        }
        $j = $r->successful() && strlen($r->body()) <= $c['max_bytes'] ? $r->json() : null;
        if (! is_array($j)) {
            throw new FlujoException('Respuesta inválida del Codificador.');
        }
        $out = [];
        foreach (['instituciones' => 'instituciones', 'procesos' => 'procesos'] as $k => $ent) {
            if (isset($j[$k]) && is_array($j[$k])) {
                $out[$ent] = $this->upsert($ent, array_values(array_filter($j[$k], 'is_array')), 'api');
            }
        }

        return $out;
    }

    /**
     * Sincronización con la API oficial del Codificador: trae los códigos de la codificación «Instituciones» (y, si se indicó, la de procedimientos)
     * y los guarda en el catálogo local con su id, OID, tipo (de info_extra) y código padre. Nunca borra: lo que ya no llega queda como está.
     *
     * @return array<string,array{creados:int,actualizados:int,errores:array<int,string>}>
     */
    private function sincronizarOficial(CodificadorApi $api): array
    {
        $out = [];
        $cods = $api->codigos();
        $porId = [];
        foreach ($cods as $x) {
            if ($x['id']) {
                $porId[$x['id']] = $x['codigo'];
            }
        }
        $idCod = (int) config('sgd.codificador.id_codificacion', 1);
        $filas = array_map(fn ($x) => ['codigo' => $x['codigo'], 'nombre' => $x['descripcion'], 'tipo' => self::tipoDeCodigo($x['codigo']) ?? ($x['info_extra']['tipo'] ?? null),
            'codigo_padre' => $x['padre'] ? ($porId[$x['padre']] ?? null) : null, 'codificador_id' => $x['id'], 'oid' => $x['identificador'] ?: ($x['codigo'] !== '' ? $idCod.'.'.$x['codigo'] : null),
            'info_extra' => $x['info_extra'] ? json_encode($x['info_extra'], JSON_UNESCAPED_UNICODE) : null, 'activo' => self::vigentePorFecha($x['fecha_fin'])], $cods);
        $out['instituciones'] = $this->upsert('instituciones', $filas, 'api');
        $idProc = (int) config('sgd.codificador.id_codificacion_procesos');
        if ($idProc > 0) {
            $out['procesos'] = $this->upsert('procesos', array_map(fn ($x) => ['codigo' => $x['codigo'], 'nombre' => $x['descripcion']], $api->codigos([], $idProc)), 'api');
        }

        return $out;
    }

    /**
     * Verificación de solo lectura: la institución configurada existe en el Codificador y la API responde.
     *
     * @return list<array{0:string,1:bool,2:string}>
     */
    public function probar(): array
    {
        $cod = CodificacionService::municipio();
        $out = [['Código de esta institución', (bool) $cod['codigo'], $cod['codigo'] ? $cod['codigo'].' — '.($cod['nombre'] ?? 'sin nombre en el catálogo local').' ('.$cod['origen'].')' : 'sin definir']];
        $api = app(CodificadorApi::class);
        if (! $api->esOficial() || ! $cod['codigo']) {
            return $out;
        }
        try {
            $r = $api->codigos(['codigo' => $cod['codigo']]);
            $m = collect($r)->firstWhere('codigo', $cod['codigo']);
            $out[] = ['La API del Codificador responde', true, $api->base()];
            $out[] = ['La institución existe en el Codificador', $m !== null, $m ? ($m['descripcion'].' · id '.$m['id'].' · OID '.($m['identificador'] ?: 'sin dato')) : 'no se encontró el código '.$cod['codigo']];
            if ($m) {
                $vig = self::vigentePorFecha($m['fecha_fin']);
                $out[] = ['La institución está vigente', $vig, $vig ? 'vigente (fin de vigencia: '.(substr((string) $m['fecha_fin'], 0, 10) ?: 'sin fecha').')' : 'su vigencia terminó el '.substr((string) $m['fecha_fin'], 0, 10)];
                $out[] = ['Tipo y dependencia', true, (self::tipoDeCodigo($m['codigo']) ?: ($m['info_extra']['tipo'] ?? 'sin dato')).' · institución padre (id) '.($m['padre'] ?: 'ninguna')];
            }
        } catch (FlujoException $e) {
            $out[] = ['La API del Codificador responde', false, $e->getMessage()];
        }

        return $out;
    }

    /**
     * Deja en el catálogo local a la Municipalidad de Santo Domingo y su cadena de dependencia, con los datos del Codificador (listado oficial de
     * instituciones): PE-MUN-00243 (id 243, OID 1.PE-MUN-00243, tipo MUN, nivel 3, región 05, provincia 056, comuna 05606, grupo B, vigente desde 14-12-2021),
     * dependiente de PE-SUB-00573 (Subsecretaría de Desarrollo Regional y Administrativo), que depende de PE-MIN-00526 (Ministerio del Interior). Idempotente:
     * completa lo que falte y no pisa lo que ya cargó la API o un CSV.
     */
    public function asegurarMunicipioPorDefecto(): void
    {
        $base = ['origen' => 'manual', 'activo' => true, 'sincronizado_en' => now()];
        $asegurar = function (string $codigo, array $datos, array $extra) use ($base) {
            $i = Institucion::query()->firstOrCreate(['codigo' => $codigo], $base + $datos);
            $actual = (array) json_decode((string) $i->info_extra, true);
            $nuevo = $actual + $extra; // lo que ya figura manda
            $cambios = [];
            foreach (['codificador_id', 'oid', 'codigo_padre', 'tipo'] as $k) {
                if (empty($i->{$k}) && ! empty($datos[$k])) {
                    $cambios[$k] = $datos[$k];
                }
            }
            if ($nuevo !== $actual) {
                $cambios['info_extra'] = json_encode($nuevo, JSON_UNESCAPED_UNICODE);
            }
            if ($cambios) {
                $i->forceFill($cambios)->save();
            }
        };
        $asegurar('PE-MIN-00526', ['nombre' => 'Ministerio del Interior', 'tipo' => 'MIN', 'codificador_id' => 526, 'oid' => '1.PE-MIN-00526'], ['poder' => 'PE', 'tipo' => 'MIN']);
        $asegurar('PE-SUB-00573', ['nombre' => 'Subsecretaría de Desarrollo Regional y Administrativo', 'tipo' => 'SUB', 'codigo_padre' => 'PE-MIN-00526', 'codificador_id' => 573, 'oid' => '1.PE-SUB-00573'],
            ['poder' => 'PE', 'tipo' => 'SUB', 'nivel' => 2, 'sigla' => 'SUBDERE', 'dr' => 'DE', 'grupo' => 'A']);
        $asegurar('PE-MUN-00243', ['nombre' => 'Municipalidad de Santo Domingo', 'tipo' => 'MUN', 'codigo_padre' => 'PE-SUB-00573', 'codificador_id' => 243, 'oid' => '1.PE-MUN-00243'],
            ['poder' => 'PE', 'tipo' => 'MUN', 'nivel' => 3, 'dr' => 'OA', 'grupo' => 'B', 'region' => '05', 'provincia' => '056', 'comuna' => '05606', 'region_nombre' => 'Valparaíso', 'provincia_nombre' => 'San Antonio', 'comuna_nombre' => 'Santo Domingo',
                'afecta_ley_21180' => true, 'oae_principal' => true]);
    }

    public function institucionActiva(?string $codigo): ?Institucion
    {
        return $codigo ? Institucion::query()->where('codigo', $codigo)->where('activo', true)->first() : null;
    }
}
