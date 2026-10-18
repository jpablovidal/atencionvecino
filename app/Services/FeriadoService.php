<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Solicitud;
use App\Models\TransparenciaFeriado;
use App\Models\Usuario;
use App\Support\Plazos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Feriados y días no laborables: no cuentan como días administrativos (hábiles) en ningún plazo de trámites y servicios.
 * No vienen precargados (A VALIDAR con el calendario oficial de cada año).
 */
class FeriadoService
{
    public const TIPOS = ['nacional' => 'Feriado nacional', 'regional' => 'Feriado regional', 'comunal' => 'Feriado comunal', 'administrativo' => 'Día no laborable administrativo'];

    public function __construct(private readonly AuditoriaService $audit) {}

    private function exigir(Usuario $u): void
    {
        if (! $u->tienePermiso('feriados.gestionar')) {
            throw new FlujoException('No tiene permiso para administrar los feriados.');
        }
    }

    private function fecha(string $f): Carbon
    {
        try {
            $c = Carbon::createFromFormat('Y-m-d', trim($f), Plazos::TZ);
        } catch (\Throwable) {
            throw new FlujoException("Fecha inválida: «{$f}».");
        }
        if ($c === false || $c->format('Y-m-d') !== trim($f)) {
            throw new FlujoException("Fecha inválida: «{$f}».");
        }
        if ($c->year < 2020 || $c->year > 2100) {
            throw new FlujoException('Fecha fuera de rango (2020 a 2100).');
        }

        return $c;
    }

    private function nombre(string $n): string
    {
        $n = TramiteService::limpiarTexto($n);
        if (mb_strlen($n) < 3 || mb_strlen($n) > 120) {
            throw new FlujoException('El nombre debe tener entre 3 y 120 caracteres.');
        }

        return $n;
    }

    private function tipo(string $t): string
    {
        if (! isset(self::TIPOS[$t])) {
            throw new FlujoException('Tipo de feriado inválido.');
        }

        return $t;
    }

    public function agregar(Usuario $u, string $fecha, string $nombre, string $tipo = 'nacional'): TransparenciaFeriado
    {
        $this->exigir($u);
        $f = $this->fecha($fecha);
        if (TransparenciaFeriado::query()->where('fecha', $f->format('Y-m-d'))->exists()) {
            throw new FlujoException('Esa fecha ya está registrada como feriado.');
        }
        $r = TransparenciaFeriado::query()->create(['fecha' => $f->format('Y-m-d'), 'nombre' => $this->nombre($nombre), 'tipo' => $this->tipo($tipo), 'creado_por' => $u->id]);
        Plazos::olvidarFeriados();
        $this->audit->registrar('feriado.agregar', 'feriado', $r->id, ['fecha' => $r->fecha, 'tipo' => $r->tipo]);

        return $r;
    }

    public function actualizar(Usuario $u, TransparenciaFeriado $f, string $nombre, string $tipo): TransparenciaFeriado
    {
        $this->exigir($u);
        $f->forceFill(['nombre' => $this->nombre($nombre), 'tipo' => $this->tipo($tipo)])->save();
        $this->audit->registrar('feriado.editar', 'feriado', $f->id, ['fecha' => $f->fecha, 'tipo' => $f->tipo]);

        return $f;
    }

    public function quitar(Usuario $u, TransparenciaFeriado $f): void
    {
        $this->exigir($u);
        [$id, $fecha] = [$f->id, $f->fecha];
        $f->delete();
        Plazos::olvidarFeriados();
        $this->audit->registrar('feriado.quitar', 'feriado', $id, ['fecha' => $fecha]);
    }

    /**
     * Carga masiva: una línea por feriado, «AAAA-MM-DD;Nombre;tipo» (el tipo es opcional). Todo o nada: si hay errores no se guarda nada.
     *
     * @return array{agregados:int,omitidos:int}
     */
    public function importar(Usuario $u, string $texto): array
    {
        $this->exigir($u);
        $lineas = array_values(array_filter(array_map('trim', preg_split('/\R/', $texto) ?: []), fn ($l) => $l !== ''));
        if (! $lineas) {
            throw new FlujoException('No hay líneas para cargar.');
        }
        if (count($lineas) > 200) {
            throw new FlujoException('Máximo 200 líneas por carga.');
        }
        $nuevos = [];
        $errores = [];
        $omitidos = 0;
        foreach ($lineas as $i => $l) {
            $p = array_map('trim', explode(';', $l));
            try {
                $f = $this->fecha($p[0] ?? '');
                $nombre = $this->nombre($p[1] ?? '');
                $tipo = $this->tipo(($p[2] ?? '') !== '' ? $p[2] : 'nacional');
            } catch (FlujoException $e) {
                $errores[] = 'Línea '.($i + 1).': '.$e->getMessage();

                continue;
            }
            $k = $f->format('Y-m-d');
            if (isset($nuevos[$k]) || TransparenciaFeriado::query()->where('fecha', $k)->exists()) {
                $omitidos++;

                continue;
            }
            $nuevos[$k] = ['fecha' => $k, 'nombre' => $nombre, 'tipo' => $tipo, 'creado_por' => $u->id, 'created_at' => now(), 'updated_at' => now()];
        }
        if ($errores) {
            throw new FlujoException(implode(' ', array_slice($errores, 0, 5)).(count($errores) > 5 ? ' (y '.(count($errores) - 5).' más)' : ''));
        }
        DB::transaction(fn () => TransparenciaFeriado::query()->insert(array_values($nuevos)));
        Plazos::olvidarFeriados();
        $this->audit->registrar('feriado.importar', null, null, ['agregados' => count($nuevos), 'omitidos' => $omitidos]);

        return ['agregados' => count($nuevos), 'omitidos' => $omitidos];
    }

    /**
     * Simulador: vencimiento hábil de un plazo y los días que se excluyeron.
     *
     * @return array{vence:Carbon,corridos:Carbon,excluidos:array<int,array{fecha:string,motivo:string}>}
     */
    public function simular(string $desde, int $dias): array
    {
        $ini = $this->fecha($desde);
        if ($dias < 1 || $dias > 365) {
            throw new FlujoException('Los días deben estar entre 1 y 365.');
        }
        $vence = Plazos::vencimiento($ini, $dias, true);
        $feriados = TransparenciaFeriado::query()->pluck('nombre', 'fecha')->mapWithKeys(fn ($n, $f) => [substr((string) $f, 0, 10) => $n]);
        $ex = [];
        for ($d = $ini->copy()->addDay(); $d->lte($vence); $d->addDay()) {
            if ($d->isWeekend()) {
                continue;
            }
            if ($feriados->has($d->format('Y-m-d'))) {
                $ex[] = ['fecha' => $d->format('d-m-Y'), 'motivo' => (string) $feriados->get($d->format('Y-m-d'))];
            }
        }

        return ['vence' => $vence, 'corridos' => Plazos::vencimiento($ini, $dias, false), 'excluidos' => $ex];
    }

    /** Solicitudes en curso cuyo plazo se recalcula al cambiar los feriados. */
    public function solicitudesEnCurso(): int
    {
        return Solicitud::query()->whereNotIn('estado', ['finalizada', 'cerrada'])->count();
    }
}
