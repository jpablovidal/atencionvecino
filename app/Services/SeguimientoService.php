<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Support\Plazos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seguimiento de solicitudes para la autoridad y la OIRS (solo lectura): etapa, estado, quién la tiene, si terminó y plazos.
 * No expone documentos ni su contenido: solo el estado del trámite.
 */
class SeguimientoService
{
    public const ETAPAS = [
        'por_asignar' => 'Ingresada, por asignar',
        'en_redaccion' => 'En redacción',
        'en_visacion' => 'En visación o firma',
        'en_revision' => 'En revisión del responsable',
        'devuelta' => 'Devuelta al solicitante',
        'terminada' => 'Terminada',
    ];

    public const PLAZOS = ['vencido' => 'Plazo vencido', 'por_vencer' => 'Por vencer', 'en_plazo' => 'En plazo'];

    private const ESTADOS_FIN = ['finalizada', 'cerrada'];

    /** Consulta filtrada. Filtros: run, desde, hasta, unidad_id, etapa, tramite_id, plazo, folio. */
    public function consulta(array $f): Builder
    {
        $q = Solicitud::query()->orderByDesc('solicitudes.id');
        if (! empty($f['run'])) {
            $q->where('run', $f['run']);
        }
        if (! empty($f['transparencia'])) {
            $q->whereHas('tramite', fn ($t) => $t->where('es_transparencia', true));
        }
        if (! empty($f['folio'])) {
            $q->where('folio', $f['folio']);
        }
        if (! empty($f['tramite_id'])) {
            $q->where('tramite_id', (int) $f['tramite_id']);
        }
        if (! empty($f['desde'])) {
            $q->where('solicitudes.created_at', '>=', Carbon::parse($f['desde'], Plazos::TZ)->startOfDay());
        }
        if (! empty($f['hasta'])) {
            $q->where('solicitudes.created_at', '<=', Carbon::parse($f['hasta'], Plazos::TZ)->endOfDay());
        }
        if (! empty($f['unidad_id'])) {
            $u = (int) $f['unidad_id'];
            $q->where(fn ($w) => $w->whereHas('expediente', fn ($e) => $e->where('unidad_id', $u))
                ->orWhere(fn ($x) => $x->whereNull('expediente_id')->whereHas('tramite', fn ($t) => $t->where('unidad_propietaria_id', $u)->orWhereHas('unidades', fn ($un) => $un->where('unidades.id', $u)))));
        }
        if (! empty($f['etapa']) && isset(self::ETAPAS[$f['etapa']])) {
            $this->aplicarEtapa($q, $f['etapa']);
        }
        if (! empty($f['plazo']) && isset(self::PLAZOS[$f['plazo']])) {
            $q->whereIn('solicitudes.id', $this->idsPorPlazo($f['plazo']));
        }

        return $q;
    }

    private function aplicarEtapa(Builder $q, string $etapa): void
    {
        $activa = fn (Builder $b) => $b->whereNotIn('solicitudes.estado', [...self::ESTADOS_FIN, 'devuelta']);
        match ($etapa) {
            'terminada' => $q->where(fn ($w) => $w->whereIn('solicitudes.estado', self::ESTADOS_FIN)->orWhereHas('expediente', fn ($e) => $e->where('estado', 'cerrado'))),
            'devuelta' => $q->where('solicitudes.estado', 'devuelta'),
            'por_asignar' => $activa($q)->whereNull('expediente_id'),
            'en_visacion' => $activa($q)->whereHas('expediente', fn ($e) => $e->where('estado', 'abierto')->whereHas('documentos', fn ($d) => $d->where('estado', 'en_flujo'))),
            'en_redaccion' => $activa($q)->whereHas('expediente', fn ($e) => $e->where('estado', 'abierto')->whereDoesntHave('documentos', fn ($d) => $d->where('estado', 'en_flujo'))->whereHas('documentos', fn ($d) => $d->where('estado', 'borrador'))),
            'en_revision' => $activa($q)->whereHas('expediente', fn ($e) => $e->where('estado', 'abierto')->whereDoesntHave('documentos', fn ($d) => $d->whereIn('estado', ['en_flujo', 'borrador']))),
        };
    }

    /** @return array<int,int> ids de solicitudes activas (no terminadas ni devueltas) en esa situación de plazo */
    public function idsPorPlazo(string $situacion): array
    {
        $ahora = now(Plazos::TZ);

        return Solicitud::query()->with('tramite:id,plazo_dias,plazo_habiles')->whereNotIn('estado', [...self::ESTADOS_FIN, 'devuelta'])
            ->get(['id', 'created_at', 'tramite_id', 'prorroga_dias'])
            ->filter(fn ($s) => Plazos::situacion(Plazos::deSolicitud($s), $ahora) === $situacion)
            ->pluck('id')->all();
    }

    /** Relaciones necesarias para describir las filas sin consultas por fila. */
    public function cargar(EloquentCollection $solicitudes): EloquentCollection
    {
        return $solicitudes->load(['tramite.unidades:id,nombre', 'tramite.propietaria:id,nombre', 'expediente.unidad:id,nombre', 'expediente.responsable:id,nombres,apellidos',
            'expediente.derivaciones.a:id,nombres,apellidos', 'expediente.documentos:id,expediente_id,estado', 'expediente.documentos.pasos.usuario:id,nombres,apellidos', 'devoluciones']);
    }

    private function etapaDe(Solicitud $s): string
    {
        if (in_array($s->estado, self::ESTADOS_FIN, true)) {
            return 'terminada';
        }
        if ($s->estado === 'devuelta') {
            return 'devuelta';
        }
        $e = $s->expediente;
        if (! $e) {
            return 'por_asignar';
        }
        if ($e->estado === 'cerrado') {
            return 'terminada';
        }
        if ($e->documentos->contains('estado', 'en_flujo')) {
            return 'en_visacion';
        }

        return $e->documentos->contains('estado', 'borrador') ? 'en_redaccion' : 'en_revision';
    }

    /**
     * Fila de seguimiento de una solicitud (con las relaciones de cargar()).
     *
     * @return array<string,mixed>
     */
    public function fila(Solicitud $s): array
    {
        $etapa = $this->etapaDe($s);
        $e = $s->expediente;
        $t = $s->tramite;
        $ahora = now(Plazos::TZ);
        $vence = Plazos::deSolicitud($s, $t);
        $ultimaDeriv = $e?->derivaciones->last();
        $resp = $ultimaDeriv?->a ?? $e?->responsable;
        $tiene = match ($etapa) {
            'por_asignar' => ($t->unidades->pluck('nombre')->join(' / ') ?: ($t->propietaria?->nombre ?? '—')).' (sin responsable asignado)',
            'devuelta' => 'El solicitante (debe responder)',
            'terminada' => null,
            'en_visacion' => $this->quienFirma($e) ?? $resp?->nombreCompleto(),
            default => $resp?->nombreCompleto(),
        };
        if ($etapa === 'terminada') {
            $fin = $e?->cerrado_en ?? $s->updated_at;
            $plazo = $fin->greaterThan($vence) ? 'fuera_de_plazo' : 'cumplido';
        } elseif ($etapa === 'devuelta') {
            $plazo = 'suspendido';
        } else {
            $plazo = Plazos::situacion($vence, $ahora);
        }
        $dev = $etapa === 'devuelta' ? $s->devoluciones->last() : null;

        return [
            's' => $s, 'folio' => $s->folio, 'run' => $s->run, 'solicitante' => trim($s->nombres.' '.$s->apellidos), 'tramite' => $t->nombre,
            'direccion' => $e?->unidad?->nombre ?? $t->propietaria?->nombre ?? $t->unidades->first()?->nombre ?? '—',
            'ingreso' => $s->created_at, 'estado' => $s->estado, 'etapa' => $etapa, 'etapa_txt' => self::ETAPAS[$etapa],
            'tiene' => $tiene, 'responsable' => $resp?->nombreCompleto(), 'terminada' => $etapa === 'terminada',
            'termino' => $etapa === 'terminada' ? ($e?->cerrado_en ?? $s->updated_at) : null,
            'vence' => $vence, 'plazo' => $plazo, 'dias' => in_array($plazo, ['vencido', 'por_vencer', 'en_plazo'], true) ? Plazos::diasRestantes($vence, $ahora) : null,
            'vence_solicitante' => $dev?->vence_en, 'expediente' => $e?->codigo,
        ];
    }

    private function quienFirma($e): ?string
    {
        if (! $e) {
            return null;
        }
        foreach ($e->documentos->where('estado', 'en_flujo') as $d) {
            $p = $d->pasos->where('estado', 'pendiente')->sortBy('orden')->first();
            if ($p) {
                return $p->usuario?->nombreCompleto().' ('.($p->tipo === 'firma' ? 'firma' : 'visación').')';
            }
        }

        return null;
    }

    /** @return Collection<int,array<string,mixed>> */
    public function filas(EloquentCollection $solicitudes): Collection
    {
        return $this->cargar($solicitudes)->map(fn ($s) => $this->fila($s));
    }

    /**
     * Foto liviana de TODAS las solicitudes para los tableros (sin cargar modelos): etapa, dirección, situación de plazo.
     *
     * @return Collection<int,array{id:int,etapa:string,unidad_id:?int,plazo:string,ingreso:Carbon,fin:?Carbon,responsable_id:?int,tramite_id:int,vence:Carbon}>
     */
    public function instantanea(): Collection
    {
        $ahora = now(Plazos::TZ);
        $enFlujo = Documento::query()->where('estado', 'en_flujo')->pluck('expediente_id')->flip();
        $borrador = Documento::query()->where('estado', 'borrador')->pluck('expediente_id')->flip();
        $exp = Expediente::query()->whereNotNull('solicitud_id')->get(['id', 'solicitud_id', 'unidad_id', 'responsable_id', 'estado', 'cerrado_en'])->keyBy('solicitud_id');
        $tr = Tramite::query()->get(['id', 'plazo_dias', 'plazo_habiles', 'unidad_propietaria_id'])->keyBy('id');

        return Solicitud::query()->get(['id', 'estado', 'created_at', 'updated_at', 'tramite_id', 'expediente_id', 'prorroga_dias'])->map(function ($s) use ($exp, $tr, $enFlujo, $borrador, $ahora) {
            $e = $exp->get($s->id);
            $t = $tr->get($s->tramite_id);
            $vence = $t ? Plazos::deSolicitud($s, $t) : Plazos::vencimiento($s->created_at, 15);
            if (in_array($s->estado, self::ESTADOS_FIN, true) || $e?->estado === 'cerrado') {
                $etapa = 'terminada';
            } elseif ($s->estado === 'devuelta') {
                $etapa = 'devuelta';
            } elseif (! $e) {
                $etapa = 'por_asignar';
            } else {
                $etapa = isset($enFlujo[$e->id]) ? 'en_visacion' : (isset($borrador[$e->id]) ? 'en_redaccion' : 'en_revision');
            }
            $fin = $etapa === 'terminada' ? ($e?->cerrado_en ?? $s->updated_at) : null;
            $plazo = match ($etapa) {
                'terminada' => $fin->greaterThan($vence) ? 'fuera_de_plazo' : 'cumplido',
                'devuelta' => 'suspendido',
                default => Plazos::situacion($vence, $ahora),
            };

            return ['id' => $s->id, 'etapa' => $etapa, 'unidad_id' => $e?->unidad_id ?? $t?->unidad_propietaria_id, 'plazo' => $plazo, 'ingreso' => $s->created_at, 'fin' => $fin,
                'responsable_id' => $e?->responsable_id, 'tramite_id' => $s->tramite_id, 'vence' => $vence];
        });
    }
}
