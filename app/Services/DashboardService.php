<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Incidente;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\TransparenciaRequerimiento;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Support\Plazos;

/**
 * Panel (dashboard) de cada persona según su rol: indicadores, gráficos de barras accesibles (progress) y tablas.
 * Los datos de seguimiento se calculan una vez (instantánea) y se filtran por el alcance de cada rol.
 */
class DashboardService
{
    public function __construct(private readonly SeguimientoService $seg) {}

    /** @return array{titulo:string,rol:string,kpis:array<int,array<string,mixed>>,bloques:array<int,array<string,mixed>>} */
    public function para(Usuario $u): array
    {
        $rol = $u->rolBase();
        $nombreRol = (string) (Rol::query()->where('clave', $u->rol)->value('nombre') ?? ucfirst($u->rol));
        $kpis = [];
        $bloques = [];
        $snap = null;
        $instantanea = function () use (&$snap) {
            return $snap ??= $this->seg->instantanea();
        };

        if ($u->tienePermiso('seguimiento.consultar')) {
            [$k, $b] = $this->autoridad($instantanea());
            $kpis = array_merge($kpis, $k);
            $bloques = array_merge($bloques, $b);
        }
        if ($u->tienePermiso('oirs.ver')) {
            [$k, $b] = $this->oirs($instantanea());
            $kpis = array_merge($kpis, $k);
            $bloques = array_merge($bloques, $b);
        }
        if ($u->tienePermiso('transparencia.ver')) {
            [$k, $b] = $this->transparencia();
            $kpis = array_merge($kpis, $k);
            $bloques = array_merge($bloques, $b);
        } elseif ($u->tienePermiso('transparencia.responder') && $u->unidad_id) {
            $n = TransparenciaRequerimiento::query()->where('unidad_id', $u->unidad_id)->where('estado', 'pendiente')->count();
            if ($n > 0) {
                $venc = TransparenciaRequerimiento::query()->where('unidad_id', $u->unidad_id)->where('estado', 'pendiente')->where('vence_en', '<', now())->count();
                $kpis[] = ['icono' => 'send', 'valor' => $n, 'etiqueta' => 'Requerimientos de transparencia por responder'.($venc ? " ($venc vencidos)" : ''), 'nivel' => $venc ? 'aviso' : 'info', 'url' => route('app.transparencia.requerimientos', ['estado' => 'pendiente'])];
            }
        }
        if ($u->operaExpedientes()) {
            $b = $this->operador($u, $instantanea());
            $bloques = array_merge($bloques, $b);
        }
        if ($rol === 'auditor') {
            $bloques[] = $this->actividad();
        }
        if ($rol === 'admin') {
            $bloques = array_merge($bloques, $this->administracion());
        }

        return ['titulo' => 'Mi panel · '.$nombreRol, 'rol' => $nombreRol, 'kpis' => $kpis, 'bloques' => $bloques];
    }

    /** @param array<int,array{0:string,1:int,2?:?string}> $filas */
    private function barras(string $titulo, array $filas, ?string $nota = null): array
    {
        return ['tipo' => 'barras', 'titulo' => $titulo, 'filas' => $filas, 'max' => max(1, ...array_map(fn ($f) => $f[1], $filas ?: [['', 0]])), 'nota' => $nota];
    }

    private function autoridad($snap): array
    {
        $activas = $snap->where('etapa', '!=', 'terminada');
        $hace30 = now(Plazos::TZ)->subDays(30);
        $term = $snap->where('etapa', 'terminada');
        $term90 = $term->filter(fn ($x) => $x['fin'] && $x['fin']->greaterThan(now()->subDays(90)));
        $dias = $term90->isEmpty() ? null : round($term90->avg(fn ($x) => $x['ingreso']->diffInDays($x['fin'])), 1);
        $kpis = [
            ['icono' => 'folder', 'valor' => $activas->count(), 'etiqueta' => 'Solicitudes activas', 'nivel' => 'ok', 'url' => route('app.seguimiento')],
            ['icono' => 'alert', 'valor' => $snap->where('plazo', 'vencido')->count(), 'etiqueta' => 'Con plazo vencido', 'nivel' => $snap->where('plazo', 'vencido')->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.seguimiento', ['plazo' => 'vencido'])],
            ['icono' => 'clock', 'valor' => $snap->where('plazo', 'por_vencer')->count(), 'etiqueta' => 'Por vencer', 'nivel' => 'info', 'url' => route('app.seguimiento', ['plazo' => 'por_vencer'])],
            ['icono' => 'check-circle', 'valor' => $term->filter(fn ($x) => $x['fin'] && $x['fin']->greaterThan($hace30))->count(), 'etiqueta' => 'Terminadas (30 días)', 'nivel' => 'ok', 'url' => route('app.seguimiento', ['etapa' => 'terminada', 'desde' => $hace30->format('Y-m-d')])],
        ];
        $porEtapa = [];
        foreach (SeguimientoService::ETAPAS as $k => $v) {
            $porEtapa[] = [$v, $snap->where('etapa', $k)->count(), route('app.seguimiento', ['etapa' => $k])];
        }
        $unidades = Unidad::query()->pluck('nombre', 'id');
        $porDir = $activas->groupBy('unidad_id')->map(fn ($g, $id) => [(string) ($unidades[$id] ?? 'Sin dirección'), $g->count(), $id ? route('app.seguimiento', ['unidad_id' => $id]) : null])->sortByDesc(1)->values()->take(10)->all();
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now(Plazos::TZ)->startOfMonth()->subMonths($i);
            $meses[] = [ucfirst($m->locale('es')->translatedFormat('F Y')), $snap->filter(fn ($x) => $x['ingreso']->setTimezone(Plazos::TZ)->isSameMonth($m))->count(), null];
        }

        return [$kpis, [
            $this->barras('Solicitudes por etapa', $porEtapa, 'Haga clic en una etapa para ver el detalle.'),
            $this->barras('Solicitudes activas por dirección', $porDir),
            $this->barras('Solicitudes ingresadas por mes', $meses, $dias !== null ? 'Tiempo medio de término (últimos 90 días): '.number_format($dias, 1, ',', '.').' días.' : null),
        ]];
    }

    private function oirs($snap): array
    {
        $kpis = [
            ['icono' => 'alert', 'valor' => $snap->where('plazo', 'vencido')->count(), 'etiqueta' => 'Plazos vencidos', 'nivel' => $snap->where('plazo', 'vencido')->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.oirs', ['vista' => 'vencidas'])],
            ['icono' => 'clock', 'valor' => $snap->where('plazo', 'por_vencer')->count(), 'etiqueta' => 'Por vencer (≤ '.config('sgd.plazos.aviso_dias').' días)', 'nivel' => $snap->where('plazo', 'por_vencer')->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.oirs', ['vista' => 'por_vencer'])],
            ['icono' => 'inbox', 'valor' => $snap->where('etapa', 'por_asignar')->count(), 'etiqueta' => 'Sin asignar', 'nivel' => 'info', 'url' => route('app.oirs', ['vista' => 'por_asignar'])],
            ['icono' => 'mail', 'valor' => $snap->where('etapa', 'devuelta')->count(), 'etiqueta' => 'Devueltas al solicitante', 'nivel' => 'info', 'url' => route('app.oirs', ['vista' => 'devueltas'])],
        ];
        $unidades = Unidad::query()->pluck('nombre', 'id');
        $urg = $snap->whereIn('plazo', ['vencido', 'por_vencer']);
        $porDir = $urg->groupBy('unidad_id')->map(fn ($g, $id) => [(string) ($unidades[$id] ?? 'Sin dirección'), $g->count(), null])->sortByDesc(1)->values()->take(10)->all();
        $ids = $urg->sortBy(fn ($x) => $x['vence']->timestamp)->take(10)->pluck('id')->all();
        $filas = $ids ? $this->seg->filas(Solicitud::query()->whereIn('id', $ids)->get())->sortBy(fn ($f) => $f['vence']->timestamp)->values() : collect();

        return [$kpis, [
            $this->barras('Vencidas y por vencer, por dirección', $porDir),
            ['tipo' => 'tabla', 'titulo' => 'Las 10 solicitudes más urgentes', 'cabeceras' => ['Folio', 'Trámite', 'Responsable', 'Vence', 'Plazo'], 'vacio' => 'No hay plazos vencidos ni por vencer.',
                'filas' => $filas->map(fn ($f) => [[$f['folio'], route('app.seguimiento.detalle', $f['folio'])], $f['tramite'], $f['tiene'] ?? $f['responsable'] ?? 'Sin asignar', $f['vence']->format('d-m-Y'), $f['plazo'] === 'vencido' ? 'Vencido' : 'Por vencer'])->all()],
        ]];
    }

    /** @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>} */
    private function transparencia(): array
    {
        $ids = Tramite::query()->where('es_transparencia', true)->pluck('id');
        $snap = $this->seg->instantanea()->whereIn('tramite_id', $ids);
        $activas = $snap->where('etapa', '!=', 'terminada');
        $req = TransparenciaRequerimiento::query()->with('unidad:id,nombre')->where('estado', 'pendiente')->get();
        $activa = collect(app(TransparenciaReporte::class)->activa());
        $kpis = [
            ['icono' => 'folder', 'valor' => $activas->count(), 'etiqueta' => 'Consultas abiertas', 'nivel' => 'ok', 'url' => route('app.transparencia')],
            ['icono' => 'alert', 'valor' => $snap->where('plazo', 'vencido')->count(), 'etiqueta' => 'Consultas con plazo vencido', 'nivel' => $snap->where('plazo', 'vencido')->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.transparencia', ['plazo' => 'vencido'])],
            ['icono' => 'clock', 'valor' => $snap->where('plazo', 'por_vencer')->count(), 'etiqueta' => 'Por vencer', 'nivel' => 'info', 'url' => route('app.transparencia', ['plazo' => 'por_vencer'])],
            ['icono' => 'send', 'valor' => $req->count(), 'etiqueta' => 'Requerimientos pendientes', 'nivel' => $req->isNotEmpty() ? 'info' : 'ok', 'url' => route('app.transparencia.requerimientos', ['estado' => 'pendiente'])],
        ];
        $porEtapa = [];
        foreach (SeguimientoService::ETAPAS as $k => $v) {
            $porEtapa[] = [$v, $snap->where('etapa', $k)->count(), route('app.transparencia', ['etapa' => $k])];
        }
        $plazos = [['Plazo vencido', $snap->where('plazo', 'vencido')->count(), null], ['Por vencer', $snap->where('plazo', 'por_vencer')->count(), null], ['En plazo', $snap->where('plazo', 'en_plazo')->count(), null],
            ['Suspendidas (devueltas)', $snap->where('plazo', 'suspendido')->count(), null], ['Terminadas en plazo', $snap->where('plazo', 'cumplido')->count(), null], ['Terminadas fuera de plazo', $snap->where('plazo', 'fuera_de_plazo')->count(), null]];
        $porDir = $req->groupBy('unidad_id')->map(fn ($g) => [(string) $g->first()->unidad?->nombre, $g->count(), null])->sortByDesc(1)->values()->take(10)->all();
        $estadosActiva = [['Al día', $activa->where('estado', 'al_dia')->count(), route('app.transparencia.activa')], ['Pendientes', $activa->whereIn('estado', ['pendiente', 'por_vencer'])->count(), route('app.transparencia.activa')], ['Vencidas', $activa->where('estado', 'vencida')->count(), route('app.transparencia.activa')]];

        return [$kpis, [
            $this->barras('Consultas por etapa', $porEtapa),
            $this->barras('Plazos de las consultas', $plazos),
            $this->barras('Requerimientos pendientes por dirección', $porDir),
            $this->barras('Transparencia activa (periodo vigente)', $estadosActiva),
        ]];
    }

    private function operador(Usuario $u, $snap): array
    {
        $mias = $snap->where('responsable_id', $u->id)->where('etapa', '!=', 'terminada');
        $miDir = $snap->where('unidad_id', $u->unidad_id);
        $porEtapa = [];
        foreach (SeguimientoService::ETAPAS as $k => $v) {
            if ($k !== 'terminada') {
                $porEtapa[] = [$v, $mias->where('etapa', $k)->count(), null];
            }
        }
        $plazos = [
            ['Vencidas en mi dirección', $miDir->where('plazo', 'vencido')->count(), null],
            ['Por vencer en mi dirección', $miDir->where('plazo', 'por_vencer')->count(), null],
            ['En plazo en mi dirección', $miDir->where('plazo', 'en_plazo')->count(), null],
        ];

        return [
            $this->barras('Mis solicitudes a cargo, por etapa', $porEtapa, 'Solicitudes cuyo expediente tengo asignado.'),
            $this->barras('Plazos de mi dirección', $plazos),
        ];
    }

    private function actividad(): array
    {
        $filas = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now(Plazos::TZ)->subDays($i);
            $filas[] = [$d->format('d-m'), AuditLog::query()->whereBetween('ts', [$d->copy()->startOfDay(), $d->copy()->endOfDay()])->count(), null];
        }

        return $this->barras('Eventos de auditoría (últimos 7 días)', $filas, 'Incidentes abiertos: '.Incidente::query()->where('estado', 'abierto')->count().'.');
    }

    private function administracion(): array
    {
        $roles = Rol::query()->orderBy('nombre')->get();
        $filas = $roles->map(fn ($r) => [$r->nombre, Usuario::query()->where('rol', $r->clave)->where('activo', true)->count(), null])->filter(fn ($f) => $f[1] > 0)->values()->all();
        $c = fn ($k) => (string) config("sgd.$k.mode");
        $integr = [['ClaveÚnica', $c('claveunica')], ['FirmaGob', $c('firmagob')], ['DocDigital', $c('docdigital')], ['Casilla Única', $c('casilla')], ['PISEE', $c('pisee')]];

        return [
            $this->barras('Usuarios activos por rol', $filas),
            ['tipo' => 'tabla', 'titulo' => 'Modo de las integraciones', 'cabeceras' => ['Servicio', 'Modo'], 'vacio' => '', 'filas' => array_map(fn ($i) => [$i[0], $i[1] ?: '—'], $integr)],
        ];
    }
}
