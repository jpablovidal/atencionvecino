<?php

namespace App\Services\Interop;

use App\Exceptions\FlujoException;
use App\Models\Institucion;
use App\Models\ProcesoCodificador;
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
        if ($c['mode'] !== 'api' || ! str_starts_with((string) $c['api_url'], 'https://')) {
            throw new FlujoException('Sincronización por API no configurada (CODIFICADOR_MODE=api y CODIFICADOR_API_URL https).');
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

    public function institucionActiva(?string $codigo): ?Institucion
    {
        return $codigo ? Institucion::query()->where('codigo', $codigo)->where('activo', true)->first() : null;
    }
}
