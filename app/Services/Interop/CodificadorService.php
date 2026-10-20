<?php

namespace App\Services\Interop;

use App\Exceptions\FlujoException;
use App\Models\Institucion;
use App\Models\ProcesoCodificador;
use App\Services\Admin\CodificacionService;
use App\Services\AuditoriaService;
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
     * CSV UTF-8 con cabecera: codigo,nombre[,tipo,codigo_padre] (instituciones) o codigo,nombre[,institucion_codigo] (procesos).
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
        $lineas = preg_split('/\R/', trim($contenido)) ?: [];
        $cab = array_map(fn ($c) => strtolower(trim($c)), str_getcsv((string) array_shift($lineas), ',', '"', ''));
        if (! in_array('codigo', $cab, true) || ! in_array('nombre', $cab, true)) {
            throw new FlujoException('La cabecera debe incluir "codigo" y "nombre".');
        }
        $filas = [];
        foreach ($lineas as $l) {
            if (trim($l) !== '') {
                $v = str_getcsv($l, ',', '"', '');
                $filas[] = array_combine($cab, array_pad(array_slice($v, 0, count($cab)), count($cab), null));
            }
        }

        return $this->upsert($entidad, $filas, $origen);
    }

    /** @param array<int,array<string,mixed>> $filas */
    public function upsert(string $entidad, array $filas, string $origen): array
    {
        $res = ['creados' => 0, 'actualizados' => 0, 'errores' => []];
        if (count($filas) > 20000) {
            throw new FlujoException('Máximo 20.000 filas por carga.');
        }
        DB::transaction(function () use ($entidad, $filas, $origen, &$res) {
            foreach ($filas as $i => $f) {
                $codigo = trim((string) ($f['codigo'] ?? ''));
                $nombre = trim((string) ($f['nombre'] ?? ''));
                if (! preg_match(self::CODIGO, $codigo) || $nombre === '' || mb_strlen($nombre) > 190) {
                    $res['errores'][] = 'Fila '.($i + 2).': código o nombre inválido';

                    continue;
                }
                $datos = ['nombre' => $nombre, 'origen' => $origen, 'sincronizado_en' => now(), 'activo' => true];
                if ($entidad === 'instituciones') {
                    $datos += ['tipo' => $this->corto($f['tipo'] ?? null, 60), 'codigo_padre' => $this->corto($f['codigo_padre'] ?? null, 40)];
                    foreach (['codificador_id' => null, 'oid' => 120, 'info_extra' => 4000] as $k => $max) {
                        if (isset($f[$k]) && $f[$k] !== '') {
                            $datos[$k] = $max === null ? (int) $f[$k] : $this->corto($f[$k], $max);
                        }
                    }
                    $m = Institucion::query()->where('codigo', $codigo)->first();
                } else {
                    $datos += ['institucion_codigo' => $this->corto($f['institucion_codigo'] ?? null, 40)];
                    $m = ProcesoCodificador::query()->where('codigo', $codigo)->first();
                }
                if ($m) {
                    $m->fill($datos)->save();
                    $res['actualizados']++;
                } else {
                    ($entidad === 'instituciones' ? new Institucion : new ProcesoCodificador)->fill($datos + ['codigo' => $codigo])->save();
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
        $filas = array_map(fn ($x) => ['codigo' => $x['codigo'], 'nombre' => $x['descripcion'], 'tipo' => $x['info_extra']['tipo'] ?? null,
            'codigo_padre' => $x['padre'] ? ($porId[$x['padre']] ?? null) : null, 'codificador_id' => $x['id'], 'oid' => $x['identificador'] ?: null,
            'info_extra' => $x['info_extra'] ? json_encode($x['info_extra'], JSON_UNESCAPED_UNICODE) : null], $cods);
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
        } catch (FlujoException $e) {
            $out[] = ['La API del Codificador responde', false, $e->getMessage()];
        }

        return $out;
    }

    /** Deja en el catálogo local a la Municipalidad de Santo Domingo y su institución padre (datos del Codificador). Idempotente. */
    public function asegurarMunicipioPorDefecto(): void
    {
        $base = ['origen' => 'manual', 'activo' => true, 'sincronizado_en' => now()];
        Institucion::query()->firstOrCreate(['codigo' => 'PE-SUB-00573'], $base + ['nombre' => 'Subsecretaría de Desarrollo Regional y Administrativo', 'tipo' => 'SUB']);
        $m = Institucion::query()->firstOrCreate(['codigo' => 'PE-MUN-00243'], $base + ['nombre' => 'Municipalidad de Santo Domingo', 'tipo' => 'MUN', 'codigo_padre' => 'PE-SUB-00573']);
        if ($m->wasRecentlyCreated) {
            $m->forceFill(['codificador_id' => 243, 'oid' => '1.PE-MUN-00243', 'info_extra' => json_encode(['poder' => 'PE', 'tipo' => 'MUN', 'nivel' => 3, 'region' => 'Valparaíso', 'provincia' => 'San Antonio', 'comuna' => 'Santo Domingo', 'grupo' => 'B'], JSON_UNESCAPED_UNICODE)])->save();
        }
    }

    public function institucionActiva(?string $codigo): ?Institucion
    {
        return $codigo ? Institucion::query()->where('codigo', $codigo)->where('activo', true)->first() : null;
    }
}
