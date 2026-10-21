<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditoriaService;
use App\Support\Run;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Registro de auditoría (cadena HMAC) con filtros y exportación CSV, y cola redactada del log de aplicación. */
class LogsController extends Controller
{
    private const MAX_EXPORT = 20000;

    public function __construct(private readonly AuditoriaService $audit) {}

    public function index(Request $r)
    {
        $f = $this->filtros($r);
        $q = $this->consulta($f);

        return view('app.logs', [
            'f' => $f,
            'filas' => (clone $q)->orderByDesc('id')->paginate(50)->withQueryString(),
            'acciones' => AuditLog::query()->select('accion')->distinct()->orderBy('accion')->limit(300)->pluck('accion'),
            'puedeExportar' => $r->user()->tienePermiso('logs.exportar'),
            'puedeVerApp' => $r->user()->tienePermiso('diagnostico.ver'),
        ]);
    }

    public function exportar(Request $r): StreamedResponse
    {
        $this->permiso('logs.exportar');
        $f = $this->filtros($r);
        $q = $this->consulta($f)->orderBy('id')->limit(self::MAX_EXPORT);
        $this->audit->registrar('logs.exportar', 'audit_log', null, array_filter($f, fn ($v) => $v !== null && $v !== ''), true);
        $nombre = 'auditoria-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF"); // BOM para Excel
            fputcsv($o, ['id', 'fecha', 'fecha_utc', 'rol', 'run_enmascarado', 'accion', 'entidad', 'entidad_id', 'ip', 'sensible', 'detalle', 'hmac'], ';');
            foreach ($q->cursor() as $v) {
                fputcsv($o, array_map([self::class, 'celda'], [$v->id, $v->getRawOriginal('ts'), $v->tsUtc(), $v->actor_rol, $v->actor_run ? Run::enmascarar($v->actor_run) : '', $v->accion, $v->entidad, $v->entidad_id, $v->ip, $v->sensible ? 'si' : 'no', $v->detalle, $v->hmac]), ';');
            }
            fclose($o);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    /** Neutraliza inyección de fórmulas en planillas (=, +, -, @, tab, CR). */
    public static function celda(mixed $v): string
    {
        $s = (string) $v;

        return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false ? "'".$s : $s;
    }

    public function aplicacion(Request $r)
    {
        $this->permiso('diagnostico.ver'); // puede contener trazas internas: solo administración técnica
        $this->audit->registrar('logs.ver_aplicacion');

        return view('app.logs_aplicacion', ['lineas' => self::colaRedactada(200)]);
    }

    /** @return array<int,string> últimas líneas del log de aplicación, sin RUN, correos, tokens ni rutas absolutas. */
    public static function colaRedactada(int $n): array
    {
        $archivos = glob(storage_path('logs/*.log')) ?: [];
        usort($archivos, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        if (! $archivos) {
            return [];
        }
        $fh = fopen($archivos[0], 'rb');
        if (! $fh) {
            return [];
        }
        $tam = fstat($fh)['size'];
        fseek($fh, max(0, $tam - 262144)); // últimos 256 KB
        $txt = (string) stream_get_contents($fh);
        fclose($fh);
        $lineas = array_slice(preg_split('/\R/', $txt) ?: [], -$n);

        return array_map([self::class, 'redactar'], $lineas);
    }

    public static function redactar(string $l): string
    {
        $l = preg_replace('/\b\d{1,2}\.?\d{3}\.?\d{3}-[\dkK]\b/', '[RUN]', $l) ?? $l;
        $l = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[CORREO]', $l) ?? $l;
        $l = preg_replace('/\b(bearer|basic)\s+[^\s"\',;)]+/i', '$1 [REDACTADO]', $l) ?? $l;
        $l = preg_replace('/(bearer|authorization|token|secret|password|api[_-]?key|private_key)(["\'=:\s]+)[^\s"\',;)]+/i', '$1$2[REDACTADO]', $l) ?? $l;
        $l = preg_replace('/[A-Za-z0-9+\/_\-]{40,}={0,2}/', '[REDACTADO]', $l) ?? $l;
        $l = str_replace([base_path().'/', base_path()], ['', ''], $l);

        return mb_substr($l, 0, 600);
    }

    /** @return array<string,mixed> */
    private function filtros(Request $r): array
    {
        $v = $r->validate([
            'desde' => 'nullable|date', 'hasta' => 'nullable|date', 'accion' => 'nullable|string|max:80', 'rol' => 'nullable|string|max:30',
            'run' => 'nullable|string|max:15', 'entidad' => 'nullable|string|max:60', 'ip' => 'nullable|ip', 'sensible' => 'nullable|in:0,1',
        ]);
        $v['run'] = ! empty($v['run']) ? (Run::normalizar($v['run']) ?? '??') : null;

        return $v + ['desde' => null, 'hasta' => null, 'accion' => null, 'rol' => null, 'run' => null, 'entidad' => null, 'ip' => null, 'sensible' => null];
    }

    /** @param array<string,mixed> $f */
    private function consulta(array $f)
    {
        $q = AuditLog::query();
        if ($f['desde']) {
            $q->where('ts', '>=', $f['desde'].' 00:00:00');
        }
        if ($f['hasta']) {
            $q->where('ts', '<=', $f['hasta'].' 23:59:59.999999');
        }
        if ($f['accion']) {
            $q->where('accion', 'like', addcslashes($f['accion'], '%_\\').'%');
        }
        foreach (['rol' => 'actor_rol', 'run' => 'actor_run', 'entidad' => 'entidad', 'ip' => 'ip'] as $k => $col) {
            if ($f[$k]) {
                $q->where($col, $f[$k]);
            }
        }
        if ($f['sensible'] !== null && $f['sensible'] !== '') {
            $q->where('sensible', (int) $f['sensible']);
        }

        return $q;
    }
}
