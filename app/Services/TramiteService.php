<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Tramite;
use App\Models\TramiteAnexo;
use App\Models\TramiteVersion;
use App\Models\Unidad;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Trámites y servicios municipales administrados por cada dirección: borrador → publicado → deshabilitado.
 * Ámbito: quien tiene `tramites.gestionar` administra solo los de SU dirección (la administración, todos).
 * Publicar/reactivar exige `tramites.publicar` (control separado: quien redacta puede no poder publicar).
 */
class TramiteService
{
    public const MAX_ANEXOS = 15;

    private const URL = '/(https?:\/\/|www\.|ftp:\/\/|\b[a-z0-9.-]+\.(?:com|net|org|cl|gob|io|info|biz|xyz)\b\/?)/iu';

    public function __construct(private readonly AuditoriaService $audit) {}

    // ---------------------------------------------------------------- ámbito y permisos
    public static function veTodas(Usuario $u): bool
    {
        return $u->tienePermiso('tramites.gestionar') && $u->esRol('admin');
    }

    public static function puedeGestionar(Usuario $u, Tramite $t): bool
    {
        if (! $u->tienePermiso('tramites.gestionar')) {
            return false;
        }

        return self::veTodas($u) || ($u->unidad_id !== null && (int) $t->unidad_propietaria_id === (int) $u->unidad_id);
    }

    public static function puedePublicar(Usuario $u, Tramite $t): bool
    {
        return $u->tienePermiso('tramites.publicar') && self::puedeGestionar($u, $t);
    }

    /** Editar un trámite ya publicado cambia lo que ve la ciudadanía: exige el permiso de publicar. */
    public static function puedeEditar(Usuario $u, Tramite $t): bool
    {
        return self::puedeGestionar($u, $t) && ($t->estado !== 'publicado' || $u->tienePermiso('tramites.publicar'));
    }

    /** @return Builder<Tramite> */
    public function gestionables(Usuario $u): Builder
    {
        $q = Tramite::query();
        if (! $u->tienePermiso('tramites.gestionar')) {
            return $q->whereRaw('1 = 0');
        }
        if (! self::veTodas($u)) {
            $q->where('unidad_propietaria_id', $u->unidad_id ?? 0);
        }

        return $q;
    }

    // ---------------------------------------------------------------- texto seguro
    /** Texto plano: sin etiquetas, sin caracteres invisibles/bidireccionales y con saltos de línea normalizados. */
    public static function limpiarTexto(?string $t, bool $multilinea = false): string
    {
        $t = HtmlSanitizer::sinInvisibles(strip_tags((string) $t));
        $t = str_replace(["\r\n", "\r"], "\n", $t);
        if (! $multilinea) {
            return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/u', ' ', $t) ?? '') ?? '');
    }

    public static function contieneDireccionWeb(?string $t): bool
    {
        return (bool) preg_match(self::URL, (string) $t);
    }

    // ---------------------------------------------------------------- crear / editar
    /**
     * @param  array<string,mixed>  $d  nombre, descripcion, instrucciones, plazo_dias, anexos[], y (solo administración) unidad_propietaria_id, unidades[], codigo_interno, codigo_cpat, proceso_codigo
     */
    public function crear(Usuario $u, array $d): Tramite
    {
        if (! $u->tienePermiso('tramites.gestionar')) {
            throw new FlujoException('No tiene permiso para crear trámites.');
        }
        $admin = self::veTodas($u);
        $unidadId = $admin ? (int) ($d['unidad_propietaria_id'] ?? 0) : (int) $u->unidad_id;
        $unidad = Unidad::query()->where('activo', true)->find($unidadId);
        if (! $unidad) {
            throw new FlujoException($admin ? 'Indique la dirección responsable del trámite.' : 'Su usuario no tiene una dirección municipal asignada: pida a la administración que la registre antes de crear trámites.');
        }
        $campos = $this->validarCampos($d);
        $anexos = $this->validarAnexos((array) ($d['anexos'] ?? []));
        $unidades = $admin ? $this->unidadesValidas((array) ($d['unidades'] ?? []), $unidad->id) : [$unidad->id];

        return DB::transaction(function () use ($u, $d, $admin, $unidad, $campos, $anexos, $unidades) {
            $t = Tramite::query()->create($campos + [
                'codigo_interno' => $this->codigo($unidad, $admin ? ($d['codigo_interno'] ?? null) : null), 'estado' => 'borrador', 'activo' => false, 'unidad_propietaria_id' => $unidad->id,
                'codigo_cpat' => $admin ? ($d['codigo_cpat'] ?? null) : null, 'proceso_codigo' => $admin ? ($d['proceso_codigo'] ?? null) : null, 'actualizado_por' => $u->id, 'version' => 0,
            ]);
            $t->unidades()->sync($unidades);
            $this->guardarAnexos($t, $anexos);
            $this->version($t, 'crear', $u);
            $this->audit->registrar('tramite.crear', 'tramite', $t->id, ['unidad' => $unidad->codigo]);

            return $t->refresh();
        });
    }

    /** @param array<string,mixed> $d */
    public function actualizar(Tramite $t, Usuario $u, array $d): Tramite
    {
        if (! self::puedeEditar($u, $t)) {
            throw new FlujoException($t->estado === 'publicado' ? 'Editar un trámite publicado exige el permiso de publicación.' : 'No tiene permiso para editar este trámite.');
        }
        $admin = self::veTodas($u);
        $campos = $this->validarCampos($d);
        $anexos = $this->validarAnexos((array) ($d['anexos'] ?? []));
        $unidades = $admin && isset($d['unidades']) ? $this->unidadesValidas((array) $d['unidades'], (int) $t->unidad_propietaria_id) : null;

        return DB::transaction(function () use ($t, $u, $d, $admin, $campos, $anexos, $unidades) {
            $t = Tramite::query()->lockForUpdate()->findOrFail($t->id);
            $extra = ['actualizado_por' => $u->id];
            if ($admin) {
                $extra += ['codigo_cpat' => $d['codigo_cpat'] ?? null, 'proceso_codigo' => $d['proceso_codigo'] ?? null];
                if (! empty($d['unidad_propietaria_id']) && Unidad::query()->where('activo', true)->whereKey((int) $d['unidad_propietaria_id'])->exists()) {
                    $extra['unidad_propietaria_id'] = (int) $d['unidad_propietaria_id'];
                }
            }
            $t->update($campos + $extra);
            if ($unidades !== null) {
                $t->unidades()->sync($unidades);
            }
            $this->guardarAnexos($t, $anexos);
            $this->version($t, 'editar', $u);
            $this->audit->registrar('tramite.editar', 'tramite', $t->id, ['estado' => $t->estado]);

            return $t->refresh();
        });
    }

    // ---------------------------------------------------------------- ciclo de vida
    /** @return array<int,string> motivos por los que NO se puede publicar */
    public function problemasParaPublicar(Tramite $t): array
    {
        $p = [];
        if (mb_strlen((string) $t->nombre) < 5) {
            $p[] = 'El nombre debe tener al menos 5 caracteres.';
        }
        if (mb_strlen((string) $t->descripcion) < 30) {
            $p[] = 'La descripción debe explicar el trámite (al menos 30 caracteres).';
        }
        if ($t->unidades()->count() < 1) {
            $p[] = 'Debe tener al menos una dirección responsable.';
        }
        if (! $t->unidad_propietaria_id) {
            $p[] = 'Falta la dirección propietaria.';
        }
        if ($t->plazo_dias < 1 || $t->plazo_dias > 365) {
            $p[] = 'El plazo de respuesta debe estar entre 1 y 365 días.';
        }

        return $p;
    }

    /** @return array<int,string> advertencias (no bloquean) */
    public function publicar(Tramite $t, Usuario $u): array
    {
        if (! self::puedePublicar($u, $t)) {
            throw new FlujoException('No tiene permiso para publicar este trámite.');
        }
        $adv = [];
        DB::transaction(function () use ($t, $u, &$adv) {
            $t = Tramite::query()->lockForUpdate()->findOrFail($t->id);
            if ($t->estado === 'publicado') {
                throw new FlujoException('El trámite ya está publicado.');
            }
            if ($p = $this->problemasParaPublicar($t)) {
                throw new FlujoException('No se puede publicar: '.implode(' ', $p));
            }
            if (self::contieneDireccionWeb($t->descripcion.' '.$t->instrucciones)) {
                $adv[] = 'El texto contiene direcciones web: se muestran como texto, sin enlace. Verifique que sean oficiales.';
            }
            $evento = $t->estado === 'deshabilitado' ? 'reactivar' : 'publicar';
            $t->forceFill(['estado' => 'publicado', 'activo' => true, 'publicado_en' => now(), 'actualizado_por' => $u->id])->save();
            $this->version($t, $evento, $u);
            $this->audit->registrar('tramite.'.$evento, 'tramite', $t->id, ['version' => $t->version], true);
        });

        return $adv;
    }

    public function deshabilitar(Tramite $t, Usuario $u): void
    {
        if (! self::puedeGestionar($u, $t)) {
            throw new FlujoException('No tiene permiso para deshabilitar este trámite.');
        }
        DB::transaction(function () use ($t, $u) {
            $t = Tramite::query()->lockForUpdate()->findOrFail($t->id);
            if ($t->estado !== 'publicado') {
                throw new FlujoException('Solo se pueden deshabilitar trámites publicados.');
            }
            $t->forceFill(['estado' => 'deshabilitado', 'activo' => false, 'actualizado_por' => $u->id])->save();
            $this->version($t, 'deshabilitar', $u);
            $this->audit->registrar('tramite.deshabilitar', 'tramite', $t->id, [], true);
        });
    }

    /** Copia como BORRADOR (no se publica sola). La copia queda en la dirección de quien la crea. */
    public function duplicar(Tramite $t, Usuario $u): Tramite
    {
        if (! self::puedeGestionar($u, $t)) {
            throw new FlujoException('No tiene permiso para duplicar este trámite.');
        }
        $copia = $this->crear($u, [
            'nombre' => mb_substr('Copia de '.$t->nombre, 0, 190), 'descripcion' => $t->descripcion, 'instrucciones' => $t->instrucciones, 'plazo_dias' => $t->plazo_dias,
            'unidad_propietaria_id' => $t->unidad_propietaria_id, 'unidades' => $t->unidades()->pluck('unidades.id')->all(),
            'anexos' => $t->anexos->map(fn ($a) => ['nombre' => $a->nombre, 'descripcion' => $a->descripcion, 'obligatorio' => $a->obligatorio])->all(),
        ]);
        $this->audit->registrar('tramite.duplicar', 'tramite', $copia->id, ['origen' => $t->id]);

        return $copia;
    }

    // ---------------------------------------------------------------- internos
    /** @param array<string,mixed> $d @return array<string,mixed> */
    private function validarCampos(array $d): array
    {
        $nombre = self::limpiarTexto($d['nombre'] ?? '');
        $desc = self::limpiarTexto($d['descripcion'] ?? '', true);
        $inst = self::limpiarTexto($d['instrucciones'] ?? '', true);
        $plazo = (int) ($d['plazo_dias'] ?? 0);
        if (mb_strlen($nombre) < 5 || mb_strlen($nombre) > 190) {
            throw new FlujoException('El nombre debe tener entre 5 y 190 caracteres.');
        }
        if (self::contieneDireccionWeb($nombre)) {
            throw new FlujoException('El nombre del trámite no puede contener direcciones web.');
        }
        if (mb_strlen($desc) > 2000 || mb_strlen($inst) > 4000) {
            throw new FlujoException('La descripción admite hasta 2000 caracteres y las instrucciones hasta 4000.');
        }
        if ($plazo < 1 || $plazo > 365) {
            throw new FlujoException('El plazo de respuesta debe estar entre 1 y 365 días.');
        }

        return ['nombre' => $nombre, 'descripcion' => $desc ?: null, 'instrucciones' => $inst ?: null, 'plazo_dias' => $plazo];
    }

    /**
     * @param  array<int,mixed>  $filas
     * @return array<int,array{id:?int,nombre:string,descripcion:?string,obligatorio:bool}>
     */
    private function validarAnexos(array $filas): array
    {
        $out = [];
        $vistos = [];
        foreach ($filas as $f) {
            $f = (array) $f;
            $nombre = self::limpiarTexto($f['nombre'] ?? '');
            if ($nombre === '') {
                continue; // fila vacía = no se usa
            }
            if (mb_strlen($nombre) > 190 || self::contieneDireccionWeb($nombre)) {
                throw new FlujoException('El nombre de un documento requerido es inválido (máx. 190 caracteres, sin direcciones web): '.mb_substr($nombre, 0, 40));
            }
            $clave = mb_strtolower($nombre);
            if (isset($vistos[$clave])) {
                throw new FlujoException('Documento requerido repetido: '.$nombre);
            }
            $vistos[$clave] = true;
            $ayuda = self::limpiarTexto($f['descripcion'] ?? '');
            if (mb_strlen($ayuda) > 255) {
                throw new FlujoException('La ayuda de «'.mb_substr($nombre, 0, 40).'» admite hasta 255 caracteres.');
            }
            $out[] = ['id' => ! empty($f['id']) ? (int) $f['id'] : null, 'nombre' => $nombre, 'descripcion' => $ayuda ?: null, 'obligatorio' => ! empty($f['obligatorio'])];
        }
        if (count($out) > self::MAX_ANEXOS) {
            throw new FlujoException('Máximo '.self::MAX_ANEXOS.' documentos requeridos por trámite.');
        }

        return $out;
    }

    /** @param array<int,array{id:?int,nombre:string,descripcion:?string,obligatorio:bool}> $filas */
    private function guardarAnexos(Tramite $t, array $filas): void
    {
        $conservar = [];
        foreach ($filas as $orden => $f) {
            $a = $f['id'] ? TramiteAnexo::query()->where('tramite_id', $t->id)->find($f['id']) : null; // solo anexos de ESTE trámite
            $datos = ['nombre' => $f['nombre'], 'descripcion' => $f['descripcion'], 'obligatorio' => $f['obligatorio'], 'orden' => $orden, 'activo' => true];
            if ($a) {
                $a->update($datos);
            } else {
                $a = TramiteAnexo::query()->create(['tramite_id' => $t->id] + $datos);
            }
            $conservar[] = $a->id;
        }
        // los quitados se desactivan (las solicitudes antiguas conservan sus archivos)
        TramiteAnexo::query()->where('tramite_id', $t->id)->whereNotIn('id', $conservar)->update(['activo' => false]);
        $t->unsetRelation('anexos');
    }

    /** @param array<int,mixed> $ids @return array<int,int> */
    private function unidadesValidas(array $ids, int $propietaria): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids ?: [$propietaria])));
        $validas = Unidad::query()->where('activo', true)->whereIn('id', $ids)->pluck('id')->all();
        if (! $validas) {
            throw new FlujoException('Indique al menos una dirección responsable válida.');
        }

        return array_map('intval', $validas);
    }

    private function codigo(Unidad $u, ?string $pedido): string
    {
        $pedido = $pedido !== null ? preg_replace('/[^A-Za-z0-9._\-]/', '', $pedido) : '';
        if ($pedido !== '' && ! Tramite::query()->where('codigo_interno', $pedido)->exists()) {
            return mb_substr($pedido, 0, 40);
        }
        do {
            $c = mb_substr($u->codigo, 0, 20).'-T'.strtoupper(bin2hex(random_bytes(3)));
        } while (Tramite::query()->where('codigo_interno', $c)->exists());

        return $c;
    }

    private function version(Tramite $t, string $evento, Usuario $u): void
    {
        $t->refresh();
        $n = $t->version + 1;
        $t->forceFill(['version' => $n])->save();
        TramiteVersion::query()->create(['tramite_id' => $t->id, 'version' => $n, 'estado' => $t->estado, 'evento' => $evento, 'creado_por' => $u->id, 'created_at' => now(), 'datos' => (string) json_encode([
            'nombre' => $t->nombre, 'descripcion' => $t->descripcion, 'instrucciones' => $t->instrucciones, 'plazo_dias' => $t->plazo_dias, 'estado' => $t->estado,
            'unidad_propietaria_id' => $t->unidad_propietaria_id, 'unidades' => $t->unidades()->pluck('unidades.id')->all(),
            'anexos' => $t->anexos()->get()->map(fn ($a) => ['nombre' => $a->nombre, 'obligatorio' => $a->obligatorio, 'descripcion' => $a->descripcion])->all(),
        ], JSON_UNESCAPED_UNICODE)]);
    }
}
