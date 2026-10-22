<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\ListaOpciones;
use App\Models\ListaOpcionItem;
use App\Models\TramiteCampo;
use App\Models\Unidad;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Listados de opciones reutilizables para los campos de lista de los trámites («Tipo de vivienda», «Sector», «Tarifa»…). Cualquiera que administre
 * trámites puede usarlos; solo su dirección propietaria (o la administración) los edita. Los cambios se reflejan en todos los trámites que los usan;
 * lo ya enviado por la ciudadanía conserva lo que eligió.
 */
class ListasOpcionesService
{
    public function __construct(private readonly AuditoriaService $audit) {}

    public static function puedeEditar(Usuario $u, ListaOpciones $l): bool
    {
        return $u->tienePermiso('tramites.gestionar') && (TramiteService::veTodas($u) || ($l->unidad_id !== null && $u->unidad_id !== null && (int) $l->unidad_id === (int) $u->unidad_id));
    }

    /** @param array{nombre:string,descripcion?:?string,opciones:string,unidad_id?:?int} $d */
    public function guardar(Usuario $u, array $d, ?ListaOpciones $l = null): ListaOpciones
    {
        if (! $u->tienePermiso('tramites.gestionar') || ($l && ! self::puedeEditar($u, $l))) {
            throw new FlujoException('No tiene permiso para editar este listado.');
        }
        $nombre = TramiteService::limpiarTexto($d['nombre'] ?? '');
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120 || TramiteService::contieneDireccionWeb($nombre)) {
            throw new FlujoException('El nombre del listado debe tener entre 3 y 120 caracteres y no contener direcciones web.');
        }
        if (ListaOpciones::query()->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->when($l, fn ($q) => $q->where('id', '!=', $l->id))->exists()) {
            throw new FlujoException('Ya existe un listado con ese nombre.');
        }
        $desc = TramiteService::limpiarTexto($d['descripcion'] ?? '');
        if (mb_strlen($desc) > 250 || TramiteService::contieneDireccionWeb($desc)) {
            throw new FlujoException('La descripción admite hasta 250 caracteres y no puede contener direcciones web.');
        }
        $opciones = TramiteCamposService::parsearOpciones((string) ($d['opciones'] ?? ''), $nombre);
        $admin = TramiteService::veTodas($u);
        $unidadId = $l ? $l->unidad_id : ($admin ? (int) ($d['unidad_id'] ?? 0) ?: null : $u->unidad_id);
        if ($unidadId !== null && ! Unidad::query()->where('activo', true)->whereKey($unidadId)->exists()) {
            throw new FlujoException('La dirección propietaria no existe.');
        }
        if (! $l && ! $admin && $unidadId === null) {
            throw new FlujoException('Su usuario no tiene una dirección municipal asignada: pida a la administración que la registre.');
        }

        return DB::transaction(function () use ($u, $l, $nombre, $desc, $opciones, $unidadId) {
            $l ??= new ListaOpciones;
            $l->fill(['nombre' => $nombre, 'descripcion' => $desc ?: null, 'unidad_id' => $unidadId, 'activa' => $l->exists ? $l->activa : true, 'creado_por' => $l->creado_por ?? $u->id])->save();
            $conservar = [];
            foreach ($opciones as $orden => $o) {
                $it = ListaOpcionItem::query()->where('lista_id', $l->id)->where('valor', $o['valor'])->first() ?? new ListaOpcionItem(['lista_id' => $l->id, 'valor' => $o['valor']]);
                $it->fill(['etiqueta' => $o['etiqueta'], 'orden' => $orden, 'activo' => true])->save();
                $conservar[] = $it->id;
            }
            ListaOpcionItem::query()->where('lista_id', $l->id)->whereNotIn('id', $conservar)->update(['activo' => false]);
            $this->audit->registrar('lista.guardar', 'lista', $l->id, ['opciones' => count($opciones)]);

            return $l->refresh();
        });
    }

    public function activar(Usuario $u, ListaOpciones $l, bool $activa): void
    {
        if (! self::puedeEditar($u, $l)) {
            throw new FlujoException('No tiene permiso para editar este listado.');
        }
        $l->forceFill(['activa' => $activa])->save(); // un listado desactivado deja de ofrecerse, pero los trámites que ya lo usan siguen funcionando
        $this->audit->registrar($activa ? 'lista.activar' : 'lista.desactivar', 'lista', $l->id);
    }

    /** @return array<int,int> trámites activos que usan cada listado (id de listado => cantidad) */
    public function usos(): array
    {
        $o = [];
        foreach (TramiteCampo::query()->where('activo', true)->whereIn('tipo', ['lista', 'multiple'])->get(['config']) as $c) {
            $id = (int) (($c->config ?? [])['lista_id'] ?? 0);
            if ($id) {
                $o[$id] = ($o[$id] ?? 0) + 1;
            }
        }

        return $o;
    }

    /** Texto del editor: una opción por línea, «valor;etiqueta». */
    public static function aTexto(ListaOpciones $l): string
    {
        return $l->items()->get()->map(fn ($i) => $i->valor.';'.$i->etiqueta)->implode("\n");
    }
}
