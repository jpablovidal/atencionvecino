<?php

namespace App\Services\Interop;

use App\Models\ConfigSitio;
use App\Models\DocDigitalEntidad;
use App\Models\DocDigitalUsuario;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Services\Interop\DocDigital\HttpDocDigitalClient;
use App\Support\Run;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Directorio de DocDigital: carga automática de las entidades y de las personas habilitadas (GET /entidades/ y GET /usuarios/ de la API v3)
 * para despachar documentos, derivar y gestionar. La carga es REANUDABLE (un cursor en la base): cada ejecución trabaja dentro de un presupuesto
 * de segundos y la siguiente continúa donde quedó, de modo que funciona en hosting compartido. Lo que ya no figura en DocDigital se marca como no vigente (no se borra).
 *
 * Fases: entidades → personas (todas, o solo las de esta municipalidad) → roles de la propia entidad (tipo 1 tramitador, 2 OP salida, 3 OP entrada, 4 administrador).
 * Del RUN de las personas solo se guarda una huella (HMAC) y una máscara.
 */
class DocDigitalDirectorioService
{
    public const CLAVE = 'docdigital_directorio';

    public const TAM_PAGINA = 1000;

    public const FASES = ['entidades', 'usuarios', 'roles'];

    public function __construct(private readonly AuditoriaService $audit) {}

    public function modoApi(): bool
    {
        return in_array(config('sgd.docdigital.mode'), ['demo', 'prod'], true);
    }

    /** @return array<string,mixed> */
    public function estado(): array
    {
        $j = json_decode((string) ConfigSitio::query()->where('clave', self::CLAVE)->value('valor'), true);

        return is_array($j) ? $j : [];
    }

    private function guardarEstado(array $e): void
    {
        ConfigSitio::query()->updateOrCreate(['clave' => self::CLAVE], ['valor' => json_encode($e, JSON_UNESCAPED_UNICODE)]);
    }

    /** ¿Corresponde cargar ahora? Si hay una carga a medias, sí; si no, cuando pasaron las horas configuradas desde la última completa. */
    public function debeCargar(): bool
    {
        $e = $this->estado();
        if (! empty($e['cursor'])) {
            return true;
        }
        if (empty($e['completado_en'])) {
            return true;
        }

        return now()->diffInHours($e['completado_en'], true) >= max(1, (int) config('sgd.docdigital.directorio_horas', 24));
    }

    /**
     * Trabaja hasta `$segundos` y deja el cursor para continuar. @return array{completo:bool,entidades:int,personas:int,roles:int,fase:?string,error:?string}
     */
    public function cargar(int $segundos = 40, bool $forzar = false): array
    {
        $out = ['completo' => false, 'entidades' => 0, 'personas' => 0, 'roles' => 0, 'fase' => null, 'error' => null];
        if (! $this->modoApi()) {
            $out['error'] = 'DocDigital no está en modo DemoDoc ni Producción.';

            return $out;
        }
        $est = $this->estado();
        if ($forzar || empty($est['cursor'])) {
            if (! $forzar && ! $this->debeCargar()) {
                $out['completo'] = true;

                return $out;
            }
            $est['cursor'] = ['fase' => 'entidades', 'pagina' => 0, 'tipo' => 1, 'inicio' => now()->toIso8601String()];
        }
        $c = $est['cursor'];
        $limite = microtime(true) + max(5, $segundos);
        $cli = new HttpDocDigitalClient((array) config('sgd.docdigital'), (string) config('sgd.docdigital.mode'));
        try {
            while (microtime(true) < $limite) {
                if ($c['fase'] === 'entidades') {
                    $p = $cli->paginaEntidades((int) $c['pagina'], self::TAM_PAGINA);
                    $this->guardarEntidades($p['items']);
                    $out['entidades'] += count($p['items']);
                    if ((int) $c['pagina'] + 1 >= $p['total_pages']) {
                        $c = array_merge($c, ['fase' => 'usuarios', 'pagina' => 0]);
                    } else {
                        $c['pagina'] = (int) $c['pagina'] + 1;
                    }
                } elseif ($c['fase'] === 'usuarios') {
                    $propia = config('sgd.docdigital.directorio_usuarios') === 'propia' ? $cli->entidadPropiaId() : null;
                    $p = $cli->paginaUsuarios((int) $c['pagina'], self::TAM_PAGINA, null, $propia);
                    $this->guardarUsuarios($p['items']);
                    $out['personas'] += count($p['items']);
                    if ((int) $c['pagina'] + 1 >= $p['total_pages']) {
                        $c = array_merge($c, ['fase' => 'roles', 'pagina' => 0, 'tipo' => 1]);
                        DB::table('docdigital_usuarios')->update(['roles' => null]); // los roles se vuelven a leer completos
                    } else {
                        $c['pagina'] = (int) $c['pagina'] + 1;
                    }
                } else { // roles de la propia entidad
                    $p = $cli->paginaUsuarios((int) $c['pagina'], self::TAM_PAGINA, (int) $c['tipo'], $cli->entidadPropiaId());
                    $this->guardarUsuarios($p['items']); // también asegura que figuren aunque la fase anterior fuera «propia»
                    $this->marcarRol($p['items'], (int) $c['tipo']);
                    $out['roles'] += count($p['items']);
                    if ((int) $c['pagina'] + 1 < $p['total_pages']) {
                        $c['pagina'] = (int) $c['pagina'] + 1;
                    } elseif ((int) $c['tipo'] < 4) {
                        $c = array_merge($c, ['tipo' => (int) $c['tipo'] + 1, 'pagina' => 0]);
                    } else {
                        $this->cerrarCarga((string) $c['inicio']);
                        $this->audit->registrar('docdigital.directorio', null, null, ['entidades' => DocDigitalEntidad::query()->where('vigente', true)->count(), 'personas' => DocDigitalUsuario::query()->where('vigente', true)->count()], false, ['run' => null, 'rol' => 'sistema']);
                        $out['completo'] = true;

                        return $out + ['fase' => null];
                    }
                }
                $est['cursor'] = $c;
                $this->guardarEstado($est + ['error' => null]);
            }
        } catch (DocDigitalException $e) {
            $out['error'] = $e->getMessage();
            $est['cursor'] = $c;
            $this->guardarEstado(array_merge($est, ['error' => $e->getMessage(), 'error_en' => now()->toIso8601String()]));
        }
        $out['fase'] = $c['fase'];

        return $out;
    }

    /** @param list<array<string,mixed>> $items */
    private function guardarEntidades(array $items): void
    {
        $ahora = now();
        foreach (array_chunk($items, 200) as $lote) {
            DB::transaction(function () use ($lote, $ahora) {
                foreach ($lote as $e) {
                    DocDigitalEntidad::query()->updateOrCreate(['entidad_id' => $e['id']], ['nombre' => $e['nombre'], 'padre_id' => $e['padre'], 'organismo_id' => $e['organismo_id'], 'organismo_nombre' => $e['organismo_nombre'], 'vigente' => true, 'sincronizado_en' => $ahora]);
                }
            });
        }
    }

    /** @param list<array<string,mixed>> $items */
    private function guardarUsuarios(array $items): void
    {
        $ahora = now();
        foreach (array_chunk($items, 200) as $lote) {
            DB::transaction(function () use ($lote, $ahora) {
                foreach ($lote as $u) {
                    $run = $u['run'] !== null ? Run::normalizar($u['run']) ?? Run::normalizar(trim($u['run']).Run::dv(trim($u['run']))) : null;
                    $datos = ['run_huella' => $u['run'] !== null ? DocDigitalUsuario::huella($u['run']) : null, 'run_mascara' => $run ? Run::enmascarar($run) : null, 'nombre' => $u['nombre'], 'email' => $u['email'], 'cargo' => $u['cargo'], 'entidad_id' => $u['entidad_id'],
                        'entidad_nombre' => $u['entidad_nombre'], 'organismo_id' => $u['organismo_id'], 'organismo_nombre' => $u['organismo_nombre'], 'vigente' => true, 'sincronizado_en' => $ahora];
                    DocDigitalUsuario::query()->updateOrCreate(['usuario_id' => $u['id']], $datos);
                }
            });
        }
    }

    /** @param list<array<string,mixed>> $items */
    private function marcarRol(array $items, int $tipo): void
    {
        foreach ($items as $u) {
            $f = DocDigitalUsuario::query()->where('usuario_id', $u['id'])->first();
            if ($f) {
                $r = array_unique(array_merge($f->rolesLista(), [$tipo]));
                sort($r);
                $f->forceFill(['roles' => implode(',', $r)])->save();
            }
        }
    }

    private function cerrarCarga(string $inicio): void
    {
        $desde = Carbon::parse($inicio);
        DocDigitalEntidad::query()->where('sincronizado_en', '<', $desde)->update(['vigente' => false]);
        if (config('sgd.docdigital.directorio_usuarios') !== 'propia') {
            DocDigitalUsuario::query()->where('sincronizado_en', '<', $desde)->update(['vigente' => false, 'roles' => null]);
        }
        $e = $this->estado();
        unset($e['cursor'], $e['error'], $e['error_en']);
        $e['completado_en'] = now()->toIso8601String();
        $this->guardarEstado($e);
    }

    // ---- Consultas (siempre sobre la copia local)

    /** @return Collection<int,DocDigitalEntidad> */
    public function buscarEntidades(string $q, int $limite = 20): Collection
    {
        $q = trim($q);
        $like = '%'.addcslashes($q, '%_\\').'%';

        return DocDigitalEntidad::query()->where('vigente', true)->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', $like)->orWhere('organismo_nombre', 'like', $like)))->orderByRaw('case when nombre like ? then 0 else 1 end', [addcslashes($q, '%_\\').'%'])->orderBy('nombre')->limit(max(1, min(50, $limite)))->get();
    }

    /** @return Collection<int,DocDigitalUsuario> personas habilitadas, por nombre, cargo o entidad; opcionalmente de una entidad */
    public function buscarPersonas(string $q, ?int $entidadId = null, int $limite = 20): Collection
    {
        $q = trim($q);
        $like = '%'.addcslashes($q, '%_\\').'%';

        return DocDigitalUsuario::query()->where('vigente', true)->when($entidadId, fn ($w) => $w->where('entidad_id', $entidadId))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', $like)->orWhere('cargo', 'like', $like)->orWhere('entidad_nombre', 'like', $like)))->orderBy('nombre')->limit(max(1, min(50, $limite)))->get();
    }

    /** Ficha de DocDigital de un usuario local (por RUN). */
    public function de(Usuario $u): ?DocDigitalUsuario
    {
        $h = DocDigitalUsuario::huella((string) $u->run);

        return $h ? DocDigitalUsuario::query()->where('run_huella', $h)->where('vigente', true)->orderByRaw('roles is null')->first() : null;
    }

    /** @return array{entidades:int,personas:int,propias:int,con_roles:int,sgd_enlazados:int} */
    public function resumen(): array
    {
        $est = $this->estado();
        $propia = (int) (config('sgd.docdigital.entidad_id') ?: 0);
        $propias = DocDigitalUsuario::query()->where('vigente', true)->whereNotNull('roles')->count();

        return ['entidades' => DocDigitalEntidad::query()->where('vigente', true)->count(), 'personas' => DocDigitalUsuario::query()->where('vigente', true)->count(),
            'propias' => $propia ? DocDigitalUsuario::query()->where('vigente', true)->where('entidad_id', $propia)->count() : $propias, 'con_roles' => $propias,
            'sgd_enlazados' => Usuario::query()->where('activo', true)->get(['id', 'run'])->filter(fn ($u) => $this->de($u) !== null)->count()] + ['estado' => $est];
    }
}
