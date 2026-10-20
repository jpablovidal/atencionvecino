<?php

namespace App\Services\Interop;

use App\Models\ComunicacionOficial;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Support\Plazos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard de DocDigital: volumen de comunicaciones recibidas y enviadas, estado, acuses, tiempos de acuse y de respuesta,
 * instituciones y direcciones con más movimiento. Un auditor ve todo el municipio; el resto, solo su dirección.
 */
class DocDigitalDashboardService
{
    /** Acuses recibidos sin registrar que se consideran atrasados (días corridos). */
    public const ACUSE_ATRASADO_DIAS = 3;

    /** @return array{titulo:string,kpis:list<array<string,mixed>>,bloques:list<array<string,mixed>>} */
    public function para(Usuario $u): array
    {
        $q = ComunicacionOficial::query();
        if (! $u->veTodo()) {
            $q->where('unidad_id', $u->unidad_id);
        }
        $todas = $q->get();
        $ent = $todas->where('direccion', 'entrante');
        $sal = $todas->where('direccion', 'saliente');
        $ahora = now(Plazos::TZ);
        $fecha = fn (ComunicacionOficial $c) => Carbon::parse($c->evidencia_en ?? $c->created_at);

        $acusados = $ent->filter(fn ($c) => $c->acuse_estado === 'registrado' && $c->acuse_en);
        $horasAcuse = $acusados->isEmpty() ? null : round($acusados->avg(fn ($c) => max(0, $fecha($c)->diffInMinutes(Carbon::parse($c->acuse_en))) / 60), 1);
        $respondidas = $ent->filter(fn ($c) => $sal->contains('responde_a_id', $c->id));
        $diasRespuesta = $respondidas->isEmpty() ? null : round($respondidas->avg(function ($c) use ($sal, $fecha) {
            $r = $sal->where('responde_a_id', $c->id)->sortBy('id')->first();

            return max(0, $fecha($c)->diffInHours(Carbon::parse($r->created_at))) / 24;
        }), 1);
        $atrasados = $ent->filter(fn ($c) => $c->acuse_estado === 'pendiente' && $fecha($c)->lt($ahora->copy()->subDays(self::ACUSE_ATRASADO_DIAS)))->count();
        $sinResponder = $ent->filter(fn ($c) => ! $sal->contains('responde_a_id', $c->id))->count();
        $fallidas = $sal->where('estado', 'fallida')->count();
        $sinDespachar = $sal->where('dd_estado', 'OPS_PENDIENTE_DESPACHO')->count();

        $kpis = [
            ['icono' => 'inbox', 'valor' => $ent->count(), 'etiqueta' => 'Comunicaciones recibidas', 'nivel' => 'ok'],
            ['icono' => 'send', 'valor' => $sal->count(), 'etiqueta' => 'Comunicaciones enviadas', 'nivel' => 'ok'],
            ['icono' => 'alert', 'valor' => $atrasados, 'etiqueta' => 'Acuses atrasados (más de '.self::ACUSE_ATRASADO_DIAS.' días)', 'nivel' => $atrasados ? 'aviso' : 'ok'],
            ['icono' => 'alert', 'valor' => $fallidas, 'etiqueta' => 'Envíos fallidos', 'nivel' => $fallidas ? 'aviso' : 'ok'],
            ['icono' => 'clock', 'valor' => $sinDespachar, 'etiqueta' => 'Cargadas en DocDigital, pendientes de despacho', 'nivel' => $sinDespachar ? 'aviso' : 'ok'],
            ['icono' => 'clock', 'valor' => $horasAcuse === null ? '—' : number_format($horasAcuse, 1, ',', '.').' h', 'etiqueta' => 'Tiempo medio para registrar el acuse', 'nivel' => 'info'],
            ['icono' => 'clock', 'valor' => $diasRespuesta === null ? '—' : number_format($diasRespuesta, 1, ',', '.').' días', 'etiqueta' => 'Tiempo medio en responder lo recibido', 'nivel' => 'info'],
        ];

        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $ahora->copy()->startOfMonth()->subMonths($i);
            $en = fn (Collection $c) => $c->filter(fn ($x) => $fecha($x)->setTimezone(Plazos::TZ)->isSameMonth($m))->count();
            $meses[] = [ucfirst($m->locale('es')->translatedFormat('F Y')), $en($ent), $en($sal), $en($sal->where('estado', 'fallida'))];
        }
        $estados = [
            ['Recibidas con acuse pendiente', $ent->where('acuse_estado', 'pendiente')->count(), null],
            ['Recibidas con acuse registrado', $ent->where('acuse_estado', 'registrado')->count(), null],
            ['Recibidas sin respuesta enviada', $sinResponder, null],
            ['Envíos pendientes', $sal->where('estado', 'pendiente')->count(), null],
            ['Envíos realizados', $sal->where('estado', 'enviada')->count(), null],
            ['Envíos fallidos', $fallidas, null],
            ['Cargadas, pendientes de despacho en DocDigital', $sinDespachar, null],
            ['Despachadas, pendientes de acuse del destinatario', $sal->where('dd_estado', 'OPS_RESUELTO_ENVIADO')->count(), null],
            ['Recibidas por el destinatario', $sal->where('dd_estado', 'OPE_RECEPCION_TOTAL')->count(), null],
        ];
        $inst = fn (Collection $c) => $c->groupBy(fn ($x) => (string) ($x->institucion_nombre ?? $x->institucion_codigo ?? 'Sin institución'))->map->count()->sortDesc()->take(8)
            ->map(fn ($n, $k) => [$k, $n, null])->values()->all();
        $unidades = Unidad::query()->pluck('nombre', 'id');
        $porDir = $todas->groupBy(fn ($x) => (string) ($unidades[$x->unidad_id] ?? 'Sin dirección'))->map->count()->sortDesc()->take(10)->map(fn ($n, $k) => [$k, $n, null])->values()->all();
        $barras = fn (string $t, array $f) => ['tipo' => 'barras', 'titulo' => $t, 'filas' => $f, 'max' => max(1, ...array_map(fn ($x) => $x[1], $f ?: [['', 0]])), 'nota' => null];

        return ['titulo' => 'Panel de DocDigital', 'kpis' => $kpis, 'bloques' => [
            ['tipo' => 'tabla', 'titulo' => 'Movimiento de los últimos 6 meses', 'cabeceras' => ['Mes', 'Recibidas', 'Enviadas', 'Fallidas'], 'vacio' => 'Sin movimiento.', 'filas' => $meses],
            $barras('Estado de las comunicaciones', $estados),
            $barras('Instituciones que más nos escriben', $inst($ent)),
            $barras('Instituciones a las que más escribimos', $inst($sal)),
            $barras('Comunicaciones por dirección municipal', $porDir),
        ]];
    }
}
