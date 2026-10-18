<?php

namespace App\Services;

use App\Jobs\EnviarAvisoMovimiento;
use App\Models\AvisoMovimiento;
use App\Models\Solicitud;
use App\Models\SolicitudMovimiento;
use App\Models\Unidad;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Historial de movimientos de una solicitud (lo que ve el ciudadano) y aviso por cada movimiento: Casilla Única (notificador)
 * y copia al correo electrónico (Gmail API). Los textos son aptos para el ciudadano: sin datos internos ni de otras personas.
 */
class MovimientoService
{
    public const TIPOS = [
        'ingreso' => 'Solicitud ingresada', 'expediente' => 'Expediente abierto', 'derivada' => 'Derivada a otra persona o dirección', 'devuelta' => 'Devuelta con observaciones',
        'respondida' => 'Respuesta del ciudadano recibida', 'prorroga' => 'Plazo prorrogado', 'resuelta' => 'Gestión terminada', 'reabierta' => 'Gestión reabierta',
        'respuesta_instruida' => 'Respuesta final en preparación', 'respuesta_despachada' => 'Respuesta final despachada',
    ];

    public function __construct(private readonly AuditoriaService $audit) {}

    public function registrar(Solicitud $s, string $tipo, string $descripcion, ?int $unidadId = null): SolicitudMovimiento
    {
        $s->loadMissing('expediente', 'tramite');
        $unidadId ??= $s->expediente?->unidad_id ?? $s->tramite?->unidad_propietaria_id ?? $s->tramite?->unidades()->value('unidades.id');
        $m = SolicitudMovimiento::query()->create(['solicitud_id' => $s->id, 'expediente_id' => $s->expediente_id, 'tipo' => $tipo, 'descripcion' => mb_substr($descripcion, 0, 240), 'unidad_id' => $unidadId, 'created_at' => now()]);
        if (config('sgd.avisos.movimientos')) {
            foreach (['casilla_unica', 'correo'] as $canal) {
                $a = AvisoMovimiento::query()->create(['movimiento_id' => $m->id, 'canal' => $canal, 'estado' => 'pendiente']);
                try {
                    EnviarAvisoMovimiento::dispatch($a->id)->afterCommit();
                } catch (\Throwable $e) {
                    report($e); // un fallo del aviso nunca detiene el trámite: queda «pendiente/fallida» y se reintenta
                }
            }
        }

        return $m;
    }

    /** Nombre de la dirección responsable ahora. */
    public function direccion(Solicitud $s): ?string
    {
        $s->loadMissing('expediente.unidad', 'tramite.propietaria', 'tramite.unidades');
        $e = $s->expediente;
        if ($e && $e->unidad) {
            return $e->unidad->nombre;
        }

        return $s->tramite?->propietaria?->nombre ?? $s->tramite?->unidades->first()?->nombre;
    }

    /**
     * Historial para el ciudadano (más antiguo primero): fecha, descripción y dirección responsable. Si la solicitud es anterior
     * al registro de movimientos, se completa con su ingreso.
     *
     * @return Collection<int,array{fecha:Carbon,tipo:string,descripcion:string,direccion:?string}>
     */
    public function linea(Solicitud $s): Collection
    {
        $mov = SolicitudMovimiento::query()->where('solicitud_id', $s->id)->orderBy('id')->get();
        $unidades = Unidad::query()->whereIn('id', $mov->pluck('unidad_id')->filter()->unique())->pluck('nombre', 'id');
        $filas = $mov->map(fn ($m) => ['fecha' => Carbon::parse($m->created_at), 'tipo' => $m->tipo, 'descripcion' => $m->descripcion, 'direccion' => $unidades[$m->unidad_id] ?? null]);
        if (! $mov->contains('tipo', 'ingreso')) {
            $filas->prepend(['fecha' => $s->created_at, 'tipo' => 'ingreso', 'descripcion' => 'Solicitud ingresada.', 'direccion' => null]);
        }

        return $filas->values();
    }

    /** @return array{casilla:int,correo:int} avisos pendientes o fallidos por canal (panel del notificador) */
    public function pendientes(): array
    {
        $q = fn (string $c) => AvisoMovimiento::query()->where('canal', $c)->whereIn('estado', ['pendiente', 'fallida'])->count();

        return ['casilla' => $q('casilla_unica'), 'correo' => $q('correo')];
    }
}
