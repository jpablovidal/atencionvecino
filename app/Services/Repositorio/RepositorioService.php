<?php

namespace App\Services\Repositorio;

use App\Models\Archivo;
use App\Models\ConfigSitio;
use App\Models\RepositorioReubicacion;
use App\Services\AuditoriaService;
use App\Services\FileVault;
use Illuminate\Support\Facades\DB;

/** Operación del repositorio de documentos (local / unidad compartida de Google Drive). */
class RepositorioService
{
    public function __construct(private readonly AuditoriaService $audit, private readonly FileVault $vault) {}

    public function cliente(): DriveClient
    {
        $r = (array) config('sgd.repositorio');

        return new DriveClient(['sa_json' => $r['drive_sa_json'] ?? null, 'shared_drive_id' => $r['drive_shared_drive_id'] ?? null, 'root_folder_id' => $r['drive_root_folder_id'] ?? null,
            'timeout' => (int) ($r['timeout'] ?? 30), 'max_bytes' => (int) ($r['max_bytes'] ?? 26214400)]);
    }

    /** @return array<int,array{0:string,1:bool,2:string}> */
    public function probar(): array
    {
        $pasos = $this->cliente()->probar();
        $ok = collect($pasos)->every(fn ($p) => $p[1]);
        ConfigSitio::query()->updateOrCreate(['clave' => 'repositorio_estado'], ['valor' => (string) json_encode(['fecha' => now()->toIso8601String(), 'ok' => $ok])]);
        $this->audit->registrar('repositorio.probar', null, null, ['ok' => $ok]);

        return $pasos;
    }

    /** @return array{fecha:?string,ok:?bool} */
    public function ultimaPrueba(): array
    {
        $v = ConfigSitio::query()->where('clave', 'repositorio_estado')->value('valor');
        $j = $v ? json_decode($v, true) : null;

        return ['fecha' => $j['fecha'] ?? null, 'ok' => $j['ok'] ?? null];
    }

    /** Rutas locales referenciadas (sin reubicar). */
    private function localesQuery()
    {
        $fuentes = [
            DB::table('archivos')->select('ruta as r'),
            DB::table('documentos')->whereNotNull('pdf_ruta')->select('pdf_ruta as r'),
            DB::table('documentos')->whereNotNull('pdf_firmado_ruta')->select('pdf_firmado_ruta as r'),
            DB::table('documento_versiones')->whereNotNull('pdf_ruta')->select('pdf_ruta as r'),
            DB::table('recursos_imagen')->select('ruta as r'),
        ];
        $u = $fuentes[0];
        foreach (array_slice($fuentes, 1) as $f) {
            $u = $u->union($f);
        }

        return DB::query()->fromSub($u, 'u')->where('r', 'not like', 'gd:%')
            ->whereNotIn('r', DB::table('repositorio_reubicaciones')->select('ruta_origen'));
    }

    /** @return array{modo:string,drive_n:int,drive_bytes:int,local_n:int,local_bytes:int,reubicadas:int,pendientes:int} */
    public function estadisticas(): array
    {
        $d = Archivo::query()->where('almacen', 'drive');
        $l = Archivo::query()->where('almacen', 'local');

        return [
            'modo' => $this->vault->modoEscritura(),
            'drive_n' => (clone $d)->count(), 'drive_bytes' => (int) (clone $d)->sum('tamano'),
            'local_n' => (clone $l)->count(), 'local_bytes' => (int) (clone $l)->sum('tamano'),
            'reubicadas' => RepositorioReubicacion::query()->count(),
            'pendientes' => (int) $this->localesQuery()->count(),
        ];
    }

    /**
     * Mueve blobs locales a Drive: sube el blob CIFRADO tal cual, verifica la suma MD5 de Drive, registra la reubicación
     * (las referencias originales no se modifican) y solo entonces borra la copia local. Idempotente.
     *
     * @return array{migradas:int,errores:int,pendientes:int}
     */
    public function migrarLote(int $limite = 25, int $maxSegundos = 40): array
    {
        if (! $this->vault->driveConfigurado()) {
            throw new DriveException('Google Drive no está configurado.');
        }
        $t0 = time();
        $res = ['migradas' => 0, 'errores' => 0];
        foreach ($this->localesQuery()->limit($limite)->pluck('r') as $ruta) {
            if (time() - $t0 > $maxSegundos) {
                break;
            }
            $destino = null;
            try {
                $blob = $this->vault->blobLocal((string) $ruta);
                $destino = $this->vault->blobADrive($blob);
                DB::transaction(function () use ($ruta, $destino, $blob) {
                    RepositorioReubicacion::query()->create(['ruta_origen' => $ruta, 'ruta_destino' => $destino, 'md5' => md5($blob)]);
                    Archivo::query()->where('ruta', $ruta)->update(['almacen' => 'drive']);
                });
                $this->vault->delete((string) $ruta); // solo tras registrar la reubicación
                $res['migradas']++;
            } catch (\Throwable) {
                $res['errores']++;
                if ($destino && ! RepositorioReubicacion::query()->where('ruta_origen', $ruta)->exists()) {
                    $this->vault->delete($destino);
                }
            }
        }
        $res['pendientes'] = (int) $this->localesQuery()->count();
        $this->audit->registrar('repositorio.migrar', null, null, $res, false, ['run' => null, 'rol' => 'sistema']);

        return $res;
    }

    /**
     * Verifica integridad (descarga, descifra y compara SHA-256) de una muestra aleatoria.
     *
     * @return array{revisados:int,ok:int,fallos:array<int,string>}
     */
    public function verificarMuestra(int $n = 10): array
    {
        $filas = collect()
            ->merge(DB::table('archivos')->inRandomOrder()->limit($n)->get(['ruta', 'sha256'])->map(fn ($f) => [$f->ruta, $f->sha256]))
            ->merge(DB::table('documentos')->whereNotNull('pdf_ruta')->inRandomOrder()->limit($n)->get(['pdf_ruta', 'pdf_sha256'])->map(fn ($f) => [$f->pdf_ruta, $f->pdf_sha256]))
            ->shuffle()->take($n);
        $ok = 0;
        $fallos = [];
        foreach ($filas as [$ruta, $sha]) {
            try {
                $this->vault->get((string) $ruta, (string) $sha);
                $ok++;
            } catch (\Throwable) {
                $fallos[] = substr((string) $ruta, 0, 12).'…';
            }
        }
        $this->audit->registrar('repositorio.verificar', null, null, ['revisados' => $filas->count(), 'ok' => $ok, 'fallos' => count($fallos)]);

        return ['revisados' => $filas->count(), 'ok' => $ok, 'fallos' => $fallos];
    }
}
