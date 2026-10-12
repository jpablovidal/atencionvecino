<?php

namespace App\Services\Sistema;

use Illuminate\Support\Facades\DB;

/**
 * Consumo de recursos del servidor, apto para hosting compartido: lee /proc y cgroups (si existen), funciones PHP y la BD.
 * Nunca ejecuta programas externos. Todo dato no disponible se informa como null (no se inventa).
 */
class RecursosService
{
    public const AVISO = 70.0;

    public const CRITICO = 90.0;

    /** @return array<string,mixed> */
    public function instantanea(bool $medirCpu = true): array
    {
        $mem = $this->memoria();
        $cpu = $medirCpu ? $this->cpu() : null;
        $dir = (string) config('sgd.storage_path');
        $disco = $this->disco(is_dir($dir) ? $dir : base_path());

        return [
            'tomada_en' => now()->toIso8601String(),
            'cpu' => ['pct' => $cpu, 'nucleos' => $this->nucleos(), 'carga' => $this->carga(), 'limite_cgroup' => $this->limiteCpu()],
            'memoria' => $mem,
            'disco' => $disco,
            'php' => $this->php(),
            'bd' => $this->bd(),
            'app' => $this->app(),
            'sistema' => ['so' => php_uname('s').' '.php_uname('r'), 'arquitectura' => php_uname('m'), 'uptime_s' => $this->uptime()],
        ];
    }

    public static function nivel(?float $pct): string
    {
        if ($pct === null) {
            return 'sin_dato';
        }

        return $pct >= self::CRITICO ? 'critico' : ($pct >= self::AVISO ? 'aviso' : 'ok');
    }

    // ------------------------------------------------------------------ CPU
    /** @return array{0:int,1:int}|null [total, inactivo] */
    private function statCpu(): ?array
    {
        $l = @file('/proc/stat', FILE_IGNORE_NEW_LINES);
        if (! $l || ! str_starts_with($l[0], 'cpu ')) {
            return null;
        }
        $p = array_map('intval', preg_split('/\s+/', trim(substr($l[0], 4))) ?: []);
        if (count($p) < 5) {
            return null;
        }

        return [array_sum(array_slice($p, 0, 8)), $p[3] + $p[4]];
    }

    /** % de CPU del servidor en una ventana corta (~250 ms). */
    private function cpu(): ?float
    {
        $a = $this->statCpu();
        if ($a === null) {
            return null;
        }
        usleep(250_000);
        $b = $this->statCpu();
        if ($b === null || $b[0] <= $a[0]) {
            return null;
        }

        return round(100 * (1 - ($b[1] - $a[1]) / ($b[0] - $a[0])), 1);
    }

    private function nucleos(): ?int
    {
        $t = @file_get_contents('/proc/cpuinfo');
        $n = $t ? preg_match_all('/^processor\s*:/m', $t) : 0;

        return $n > 0 ? $n : null;
    }

    /** @return array{1:?float,5:?float,15:?float} */
    private function carga(): array
    {
        $l = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;

        return $l ? [1 => round($l[0], 2), 5 => round($l[1], 2), 15 => round($l[2], 2)] : [1 => null, 5 => null, 15 => null];
    }

    /** CPUs asignadas al contenedor/plan (cgroup v2 cpu.max), si existe. */
    private function limiteCpu(): ?float
    {
        $v = @file_get_contents('/sys/fs/cgroup/cpu.max');
        if ($v && preg_match('/^(\d+)\s+(\d+)/', trim($v), $m) && (int) $m[2] > 0) {
            return round($m[1] / $m[2], 2);
        }

        return null;
    }

    private function uptime(): ?int
    {
        $v = @file_get_contents('/proc/uptime');

        return $v ? (int) explode(' ', $v)[0] : null;
    }

    // ------------------------------------------------------------------ memoria
    /** @return array<string,mixed> */
    private function memoria(): array
    {
        $info = [];
        foreach ((array) @file('/proc/meminfo', FILE_IGNORE_NEW_LINES) as $l) {
            if (preg_match('/^(\w+):\s+(\d+) kB/', $l, $m)) {
                $info[$m[1]] = (int) $m[2] * 1024;
            }
        }
        $total = $info['MemTotal'] ?? null;
        $disp = $info['MemAvailable'] ?? (isset($info['MemFree']) ? $info['MemFree'] + ($info['Cached'] ?? 0) + ($info['Buffers'] ?? 0) : null);
        $usada = $total !== null && $disp !== null ? $total - $disp : null;
        [$limite, $usoCg] = $this->memoriaCgroup();
        $out = ['total' => $total, 'usada' => $usada, 'pct' => $total && $usada !== null ? round(100 * $usada / $total, 1) : null,
            'swap_total' => $info['SwapTotal'] ?? null, 'swap_usada' => isset($info['SwapTotal'], $info['SwapFree']) ? $info['SwapTotal'] - $info['SwapFree'] : null,
            'cgroup_limite' => $limite, 'cgroup_usada' => $usoCg, 'cgroup_pct' => $limite && $usoCg !== null ? round(100 * $usoCg / $limite, 1) : null];
        // el límite efectivo es el del contenedor/plan cuando existe y es menor que el del equipo
        $out['efectiva_pct'] = $out['cgroup_pct'] ?? $out['pct'];

        return $out;
    }

    /** @return array{0:?int,1:?int} [límite, uso] del cgroup actual */
    private function memoriaCgroup(): array
    {
        $max = @file_get_contents('/sys/fs/cgroup/memory.max');
        $cur = @file_get_contents('/sys/fs/cgroup/memory.current');
        if ($max !== false && $cur !== false && ctype_digit(trim($max))) {
            return [(int) trim($max), (int) trim($cur)];
        }
        $lim = @file_get_contents('/sys/fs/cgroup/memory/memory.limit_in_bytes');
        $uso = @file_get_contents('/sys/fs/cgroup/memory/memory.usage_in_bytes');
        if ($lim !== false && $uso !== false && ctype_digit(trim($lim)) && (int) trim($lim) < 1 << 60) {
            return [(int) trim($lim), (int) trim($uso)];
        }

        return [null, null];
    }

    // ------------------------------------------------------------------ disco
    /** @return array<string,mixed> */
    private function disco(string $ruta): array
    {
        $total = @disk_total_space($ruta);
        $libre = @disk_free_space($ruta);
        $usado = $total && $libre !== false ? $total - $libre : null;

        return ['ruta' => 'almacenamiento de la aplicación', 'total' => $total ?: null, 'libre' => $libre !== false ? $libre : null, 'usado' => $usado, 'pct' => $total && $usado !== null ? round(100 * $usado / $total, 1) : null];
    }

    // ------------------------------------------------------------------ PHP
    /** @return array<string,mixed> */
    private function php(): array
    {
        $limite = $this->bytes((string) ini_get('memory_limit'));
        $pico = memory_get_peak_usage(true);
        $op = null;
        if (function_exists('opcache_get_status')) {
            $s = @opcache_get_status(false);
            if (is_array($s) && ! empty($s['opcache_enabled'])) {
                $m = $s['memory_usage'] ?? [];
                $op = ['usada' => (int) ($m['used_memory'] ?? 0), 'libre' => (int) ($m['free_memory'] ?? 0), 'hit_rate' => isset($s['opcache_statistics']['opcache_hit_rate']) ? round((float) $s['opcache_statistics']['opcache_hit_rate'], 1) : null,
                    'scripts' => (int) ($s['opcache_statistics']['num_cached_scripts'] ?? 0)];
            }
        }

        return ['version' => PHP_VERSION, 'sapi' => PHP_SAPI, 'memory_limit' => ini_get('memory_limit'), 'memoria_peticion' => memory_get_usage(true), 'memoria_pico' => $pico,
            'memoria_pico_pct' => $limite ? round(100 * $pico / $limite, 1) : null, 'max_execution_time' => ini_get('max_execution_time'), 'opcache' => $op];
    }

    private function bytes(string $v): ?int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return null;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n,
        };
    }

    // ------------------------------------------------------------------ BD y aplicación
    /** @return array<string,mixed> */
    private function bd(): array
    {
        $c = DB::connection();
        $driver = $c->getDriverName();
        $bytes = null;
        $tablas = [];
        try {
            if ($driver === 'mysql') {
                $f = $c->select('select table_name as t, data_length + index_length as b, table_rows as n from information_schema.tables where table_schema = database() order by b desc');
                $bytes = (int) array_sum(array_column(array_map(fn ($r) => (array) $r, $f), 'b'));
                $tablas = array_map(fn ($r) => ['tabla' => $r->t, 'bytes' => (int) $r->b, 'filas' => (int) $r->n], array_slice($f, 0, 8));
            } elseif ($driver === 'sqlite') {
                $p = (string) $c->getDatabaseName();
                $bytes = is_file($p) ? (int) filesize($p) : null;
            }
        } catch (\Throwable) {
        }

        return ['driver' => $driver, 'bytes' => $bytes, 'tablas' => $tablas];
    }

    /** @return array<string,mixed> */
    private function app(): array
    {
        $q = fn (callable $f) => (function () use ($f) {
            try {
                return $f();
            } catch (\Throwable) {
                return null;
            }
        })();
        $logs = 0;
        foreach (glob(storage_path('logs/*.log')) ?: [] as $f) {
            $logs += (int) @filesize($f);
        }

        return [
            'archivos_n' => $q(fn () => DB::table('archivos')->count()),
            'archivos_bytes' => $q(fn () => (int) DB::table('archivos')->sum('tamano') + (int) DB::table('recursos_imagen')->sum('tamano')),
            'sesiones' => $q(fn () => DB::table('sessions')->where('last_activity', '>', time() - (int) config('session.lifetime') * 60)->count()),
            'cola_pendiente' => $q(fn () => DB::table('jobs')->count()),
            'cola_fallida' => $q(fn () => DB::table('failed_jobs')->count()),
            'logs_bytes' => $logs,
            'usuarios_activos' => $q(fn () => DB::table('usuarios')->where('activo', 1)->count()),
            'expedientes' => $q(fn () => DB::table('expedientes')->count()),
        ];
    }

    // ------------------------------------------------------------------ histórico
    public function registrar(): void
    {
        $s = $this->instantanea();
        DB::table('metricas_recursos')->insert([
            'ts' => now(), 'cpu_pct' => $s['cpu']['pct'], 'carga1' => $s['cpu']['carga'][1], 'mem_usada' => $s['memoria']['cgroup_usada'] ?? $s['memoria']['usada'],
            'mem_total' => $s['memoria']['cgroup_limite'] ?? $s['memoria']['total'], 'disco_usado' => $s['disco']['usado'], 'disco_total' => $s['disco']['total'],
            'bd_bytes' => $s['bd']['bytes'], 'archivos_bytes' => $s['app']['archivos_bytes'], 'sesiones' => $s['app']['sesiones'], 'cola_pendiente' => $s['app']['cola_pendiente'], 'cola_fallida' => $s['app']['cola_fallida'],
        ]);
        DB::table('metricas_recursos')->where('ts', '<', now()->subDays(35))->delete();
    }

    /**
     * Serie histórica reducida a un máximo de $max puntos.
     *
     * @return array<int,object>
     */
    public function historico(int $horas, int $max = 144): array
    {
        $f = DB::table('metricas_recursos')->where('ts', '>=', now()->subHours($horas))->orderBy('ts')->get()->all();
        if (count($f) > $max) {
            $paso = (int) ceil(count($f) / $max);
            $f = array_values(array_filter($f, fn ($_, $i) => $i % $paso === 0, ARRAY_FILTER_USE_BOTH));
        }

        return $f;
    }

    public static function humano(int|float|null $b): string
    {
        if ($b === null) {
            return '—';
        }
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $b = (float) $b;
        $i = $b > 0 ? min(4, (int) floor(log($b, 1024))) : 0;

        return number_format($b / (1024 ** $i), $i === 0 ? 0 : 1, ',', '.').' '.$u[$i];
    }

    public static function duracion(?int $s): string
    {
        if ($s === null) {
            return '—';
        }
        $d = intdiv($s, 86400);
        $h = intdiv($s % 86400, 3600);
        $m = intdiv($s % 3600, 60);

        return ($d ? $d.' d ' : '').$h.' h '.$m.' min';
    }
}
