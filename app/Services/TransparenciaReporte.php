<?php

namespace App\Services;

use App\Models\Solicitud;
use App\Models\TransparenciaActivaItem;
use App\Models\TransparenciaDerivacion;
use App\Models\TransparenciaRequerimiento;
use App\Support\Plazos;
use Illuminate\Support\Carbon;

/** Reportes de transparencia pasiva y activa. No incluyen datos personales del solicitante (RUN, nombre ni correo). */
class TransparenciaReporte
{
    public function __construct(private readonly SeguimientoService $seg, private readonly TransparenciaService $svc) {}

    /** @return array{filas:array<int,array<string,mixed>>,totales:array<string,int|float|null>} */
    public function pasiva(?string $desde, ?string $hasta, ?int $unidadId = null): array
    {
        $q = Solicitud::query()->whereHas('tramite', fn ($t) => $t->where('es_transparencia', true))->orderBy('id');
        if ($desde) {
            $q->where('created_at', '>=', Carbon::parse($desde, Plazos::TZ)->startOfDay());
        }
        if ($hasta) {
            $q->where('created_at', '<=', Carbon::parse($hasta, Plazos::TZ)->endOfDay());
        }
        if ($unidadId) {
            $q->whereIn('id', TransparenciaRequerimiento::query()->where('unidad_id', $unidadId)->pluck('solicitud_id'));
        }
        $sols = $q->get();
        $filas = $this->seg->filas($sols);
        $ids = $sols->pluck('id');
        $deriv = TransparenciaDerivacion::query()->whereIn('solicitud_id', $ids)->pluck('organo', 'solicitud_id');
        $reqs = TransparenciaRequerimiento::query()->whereIn('solicitud_id', $ids)->get()->groupBy('solicitud_id');
        $ahora = now(Plazos::TZ);
        $out = [];
        foreach ($filas as $f) {
            $s = $f['s'];
            $rq = $reqs->get($s->id, collect());
            $fin = $f['termino'];
            $resultado = match (true) {
                $deriv->has($s->id) => 'Derivada a otro órgano',
                $f['plazo'] === 'cumplido' => 'Respondida en plazo',
                $f['plazo'] === 'fuera_de_plazo' => 'Respondida fuera de plazo',
                $f['plazo'] === 'suspendido' => 'Devuelta (plazo suspendido)',
                $f['plazo'] === 'vencido' => 'En curso, plazo vencido',
                $f['plazo'] === 'por_vencer' => 'En curso, por vencer',
                default => 'En curso, en plazo',
            };
            $out[] = ['folio' => $f['folio'], 'ingreso' => $f['ingreso']->setTimezone(Plazos::TZ)->format('Y-m-d'), 'tramite' => $f['tramite'], 'etapa' => $f['etapa_txt'],
                'responsable' => $f['responsable'] ?? '', 'vence' => $f['vence']->format('Y-m-d'), 'prorrogada' => $s->prorroga_dias ? 'Sí' : 'No',
                'termino' => $fin ? $fin->setTimezone(Plazos::TZ)->format('Y-m-d') : '', 'dias_habiles' => Plazos::diasHabiles($f['ingreso'], $fin ?? $ahora),
                'resultado' => $resultado, 'derivada_a' => $deriv->get($s->id, ''), 'requerimientos' => $rq->count(),
                'requerimientos_pendientes' => $rq->where('estado', 'pendiente')->count(),
                'requerimientos_vencidos' => $rq->where('estado', 'pendiente')->filter(fn ($r) => $ahora->greaterThan($r->vence_en))->count()];
        }
        $c = collect($out);
        $resp = $c->whereIn('resultado', ['Respondida en plazo', 'Respondida fuera de plazo']);

        return ['filas' => $out, 'totales' => [
            'total' => $c->count(), 'respondidas_en_plazo' => $c->where('resultado', 'Respondida en plazo')->count(), 'respondidas_fuera_de_plazo' => $c->where('resultado', 'Respondida fuera de plazo')->count(),
            'derivadas' => $c->where('resultado', 'Derivada a otro órgano')->count(), 'prorrogadas' => $c->where('prorrogada', 'Sí')->count(),
            'en_curso' => $c->filter(fn ($x) => str_starts_with($x['resultado'], 'En curso'))->count(), 'en_curso_vencidas' => $c->where('resultado', 'En curso, plazo vencido')->count(),
            'devueltas' => $c->where('resultado', 'Devuelta (plazo suspendido)')->count(), 'requerimientos_pendientes' => (int) $c->sum('requerimientos_pendientes'), 'requerimientos_vencidos' => (int) $c->sum('requerimientos_vencidos'),
            'dias_habiles_promedio' => $resp->isEmpty() ? null : round($resp->avg('dias_habiles'), 1),
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    public function activa(?Carbon $hoy = null): array
    {
        $out = [];
        foreach (TransparenciaActivaItem::query()->with('unidad:id,nombre')->orderBy('nombre')->get() as $i) {
            if (! $i->activo) {
                continue;
            }
            $e = $this->svc->estadoActiva($i, $hoy);
            $out[] = ['codigo' => $i->codigo, 'nombre' => $i->nombre, 'direccion' => $i->unidad?->nombre ?? '', 'periodo' => $e['periodo'], 'estado' => $e['estado'], 'limite' => $e['limite']->format('Y-m-d'),
                'publicado_en' => $e['registro']?->publicado_en ? substr((string) $e['registro']->publicado_en, 0, 10) : '', 'url' => $e['registro']?->url ?? $i->url_publicacion ?? ''];
        }

        return $out;
    }

    /** CSV para Excel en español (separador «;», UTF-8 con BOM) con protección contra inyección de fórmulas. */
    public static function csv(array $cabeceras, array $filas): string
    {
        $celda = function ($v): string {
            $v = (string) $v;
            if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $v = "'".$v;
            }

            return '"'.str_replace('"', '""', str_replace(["\r\n", "\r", "\n"], ' ', $v)).'"';
        };
        $l = [implode(';', array_map($celda, $cabeceras))];
        foreach ($filas as $f) {
            $l[] = implode(';', array_map($celda, array_values($f)));
        }

        return "\xEF\xBB\xBF".implode("\r\n", $l)."\r\n";
    }
}
