<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\Area;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Áreas de la municipalidad (municipal, salud, educación, corporación). El administrador señala cuáles existen en su municipio;
 * cada usuario puede pertenecer a una o más áreas y cada unidad a una. Las áreas ORGANIZAN (listados, codificación e informes):
 * NO restringen el acceso a expedientes, que sigue regido por unidad, rol, permisos y restringidos.
 */
class AreaService
{
    public function __construct(private readonly AuditoriaService $audit) {}

    /** @return Collection<int,Area> */
    public function todas()
    {
        return Area::query()->orderBy('orden')->get();
    }

    /** @return Collection<int,Area> */
    public function habilitadas()
    {
        return Area::query()->where('habilitada', true)->orderBy('orden')->get();
    }

    /** @param array<string,array{habilitada?:mixed,institucion_codigo?:?string}> $datos por código de área */
    public function guardar(Usuario $admin, array $datos): void
    {
        DB::transaction(function () use ($datos) {
            $cambios = [];
            foreach (Area::query()->get() as $a) {
                $in = $datos[$a->codigo] ?? [];
                $hab = $a->codigo === 'municipal' ? true : ! empty($in['habilitada']);
                $cod = isset($in['institucion_codigo']) ? trim((string) $in['institucion_codigo']) : '';
                if ($cod !== '' && ! preg_match('/^[A-Za-z0-9._-]{1,30}$/', $cod)) {
                    throw new FlujoException('El código de institución de «'.$a->nombre.'» solo admite letras, números, punto, guion y guion bajo.');
                }
                if ($a->habilitada && ! $hab && ($n = $this->enUso($a))) {
                    throw new FlujoException('No se puede deshabilitar «'.$a->nombre.'»: tiene '.$n.' usuario(s) o unidad(es) asignados. Reasígnelos primero.');
                }
                if ((bool) $a->habilitada !== $hab || (string) $a->institucion_codigo !== $cod) {
                    $a->forceFill(['habilitada' => $hab, 'institucion_codigo' => $cod !== '' ? $cod : null])->save();
                    $cambios[$a->codigo] = ['habilitada' => $hab, 'institucion_codigo' => $cod ?: null];
                }
            }
            if ($cambios) {
                $this->audit->registrar('areas.config', 'config', 'areas', $cambios);
            }
        });
    }

    private function enUso(Area $a): int
    {
        return DB::table('usuario_areas')->where('area_id', $a->id)->count() + Unidad::query()->where('area_id', $a->id)->count();
    }

    /** @param list<int> $areaIds */
    public function asignarUsuario(Usuario $u, array $areaIds): void
    {
        $validas = Area::query()->where('habilitada', true)->whereIn('id', $areaIds)->pluck('id')->all();
        if (count($validas) !== count(array_unique($areaIds))) {
            throw new FlujoException('Solo se pueden asignar áreas habilitadas.');
        }
        $antes = $u->areas()->pluck('areas.id')->all();
        $u->areas()->sync($validas);
        if (array_diff($antes, $validas) || array_diff($validas, $antes)) {
            $this->audit->registrar('usuario.areas', 'usuario', $u->id, ['areas' => array_values($validas)]);
        }
    }

    public function asignarUnidad(Unidad $un, ?int $areaId): void
    {
        if ($areaId !== null && ! Area::query()->where('id', $areaId)->where('habilitada', true)->exists()) {
            throw new FlujoException('Área no habilitada.');
        }
        $un->forceFill(['area_id' => $areaId])->save();
        $this->audit->registrar('unidad.area', 'unidad', $un->id, ['area_id' => $areaId]);
    }
}
