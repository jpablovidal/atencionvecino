<?php

namespace App\Services\Sistema;

use App\Support\GeoIp;
use App\Support\MapaMundo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Registro y reporte de conexiones entrantes (clientes) y salientes (integraciones): mapa por país y ranking por IP/destino. */
class ConexionesService
{
    public const RANGOS = ['24h' => 24, '7d' => 168, '30d' => 720];

    public static function registrar(string $direccion, string $clave, string $grupo, bool $error): void
    {
        $clave = mb_substr($clave, 0, 190);
        if ($clave === '' || ($direccion === 'entrante' && ! filter_var($clave, FILTER_VALIDATE_IP))) {
            return;
        }
        if ($direccion === 'entrante') {
            $clave = inet_ntop((string) inet_pton($clave)) ?: $clave; // forma canónica (IPv6)
        }
        $bucket = now()->format('Y-m-d H:00:00');
        $llaves = ['bucket' => $bucket, 'direccion' => $direccion, 'clave' => $clave, 'grupo' => $grupo];
        $actualizar = fn () => DB::table('conexiones_resumen')->where($llaves)->update(['hits' => DB::raw('hits + 1'), 'errores' => DB::raw('errores + '.($error ? 1 : 0)), 'ultimo' => now()]);
        if ($actualizar() > 0) {
            return;
        }
        try {
            DB::table('conexiones_resumen')->insert($llaves + ['hits' => 1, 'errores' => $error ? 1 : 0, 'ultimo' => now()]);
        } catch (QueryException) {
            $actualizar(); // otra petición creó la fila al mismo tiempo
        }
    }

    public function depurar(int $dias): int
    {
        return DB::table('conexiones_resumen')->where('bucket', '<', now()->subDays($dias))->delete();
    }

    /** País de un destino saliente (resuelve el nombre y lo guarda 7 días). */
    public function paisDeHost(string $host): ?string
    {
        $c = DB::table('geo_dns_cache')->where('host', $host)->first();
        if ($c && $c->resuelto_en && now()->diffInDays($c->resuelto_en, true) < 7) {
            return $c->pais;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);
        $ip = $ips[0] ?? null;
        $pais = $ip ? GeoIp::pais($ip) : null;
        DB::table('geo_dns_cache')->updateOrInsert(['host' => $host], ['ip' => $ip, 'pais' => $pais, 'resuelto_en' => now()]);

        return $pais;
    }

    /**
     * @return array<string,mixed>
     */
    public function reporte(string $rango, string $direccion): array
    {
        $horas = self::RANGOS[$rango] ?? 24;
        $desde = now()->subHours($horas)->format('Y-m-d H:00:00');
        $base = fn () => DB::table('conexiones_resumen')->where('bucket', '>=', $desde)->when($direccion !== 'todas', fn ($q) => $q->where('direccion', $direccion));

        $filas = $base()->selectRaw('direccion, clave, sum(hits) as hits, sum(errores) as errores, max(ultimo) as ultimo')->groupBy('direccion', 'clave')->orderByDesc('hits')->limit(5000)->get();
        $porPais = [];
        $total = 0;
        $errores = 0;
        foreach ($filas as $f) {
            $cc = $f->direccion === 'entrante' ? GeoIp::pais($f->clave) : $this->paisDeHost($f->clave);
            $f->pais = $cc;
            $k = $cc ?? '??';
            $porPais[$k] = ($porPais[$k] ?? 0) + (int) $f->hits;
            $total += (int) $f->hits;
            $errores += (int) $f->errores;
        }
        arsort($porPais);

        $top = fn (string $dir) => $filas->where('direccion', $dir)->take(20)->values();
        $grupos = $base()->selectRaw('grupo, sum(hits) as hits')->groupBy('grupo')->orderByDesc('hits')->pluck('hits', 'grupo')->all();
        $porClaveGrupo = function ($dir, $claves) use ($desde) {
            return DB::table('conexiones_resumen')->where('bucket', '>=', $desde)->where('direccion', $dir)->whereIn('clave', $claves)
                ->selectRaw('clave, grupo, sum(hits) as hits')->groupBy('clave', 'grupo')->orderByDesc('hits')->get()->groupBy('clave');
        };
        $ent = $top('entrante');
        $sal = $top('saliente');
        $gEnt = $porClaveGrupo('entrante', $ent->pluck('clave')->all());
        foreach ($ent as $f) {
            $f->grupos = $gEnt->get($f->clave, collect())->map(fn ($g) => $g->grupo.' ('.$g->hits.')')->take(3)->implode(', ');
        }
        // serie: por hora (24 h) o por día
        $porDia = $horas > 24;
        $serie = $base()->selectRaw(($porDia ? 'substr(bucket, 1, 10)' : 'bucket').' as t, sum(hits) as hits')->groupBy('t')->orderBy('t')->pluck('hits', 't')->all();

        return [
            'rango' => $rango, 'direccion' => $direccion, 'desde' => $desde, 'total' => $total, 'errores' => $errores,
            'ips_unicas' => $filas->where('direccion', 'entrante')->count(), 'destinos' => $filas->where('direccion', 'saliente')->count(),
            'paises' => $porPais, 'paises_n' => count(array_diff_key($porPais, ['LAN' => 1, '??' => 1])), 'ranking_ips' => $ent, 'ranking_destinos' => $sal,
            'grupos' => $grupos, 'serie' => $serie, 'por_dia' => $porDia,
            'maximo_pais' => $porPais ? max(array_diff_key($porPais, ['LAN' => 1, '??' => 1]) ?: [0]) : 0,
        ];
    }

    /** Clase de color (0-5) por escala logarítmica respecto del máximo. */
    public static function clase(int $hits, int $max): int
    {
        if ($hits <= 0 || $max <= 0) {
            return 0;
        }

        return max(1, min(5, (int) ceil(log10($hits + 1) / log10($max + 1) * 5)));
    }

    public static function nombrePais(?string $cc): string
    {
        return $cc === '??' ? 'Desconocido' : MapaMundo::nombre($cc);
    }
}
