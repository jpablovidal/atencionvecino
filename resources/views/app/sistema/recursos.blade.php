@extends('layouts.app')
@section('titulo', 'Recursos del servidor')
@php
  use App\Services\Sistema\RecursosService as R;
  $niv = ['ok' => 'Normal', 'aviso' => 'Atención', 'critico' => 'Crítico', 'sin_dato' => 'Sin dato'];
  $cpuPct = $s['cpu']['pct']; $memPct = $s['memoria']['efectiva_pct']; $disPct = $s['disco']['pct']; $phpPct = $s['php']['memoria_pico_pct'];
  $col = fn ($campo) => array_map(fn ($f) => [\Illuminate\Support\Carbon::parse($f->ts)->timezone('America/Santiago')->format('d-m H:i'), $f->$campo === null ? null : (float) $f->$campo], $historico);
  $memSerie = array_map(fn ($f) => [\Illuminate\Support\Carbon::parse($f->ts)->timezone('America/Santiago')->format('d-m H:i'), $f->mem_total ? round(100 * $f->mem_usada / $f->mem_total, 1) : null], $historico);
  $disSerie = array_map(fn ($f) => [\Illuminate\Support\Carbon::parse($f->ts)->timezone('America/Santiago')->format('d-m H:i'), $f->disco_total ? round(100 * $f->disco_usado / $f->disco_total, 1) : null], $historico);
  $bdSerie = array_map(fn ($f) => [\Illuminate\Support\Carbon::parse($f->ts)->timezone('America/Santiago')->format('d-m H:i'), $f->bd_bytes === null ? null : round($f->bd_bytes / 1048576, 1)], $historico);
  $maxCarga = max(1, (float) collect($historico)->max('carga1'), (float) ($s['cpu']['nucleos'] ?? 1));
  $maxBd = max(1, (float) collect($bdSerie)->max(1));
@endphp
@section('contenido')
<h1>Recursos del servidor</h1>
<p class="text-muted">Medición al {{ \Illuminate\Support\Carbon::parse($s['tomada_en'])->timezone('America/Santiago')->format('d-m-Y H:i:s') }}. Los valores provienen del propio servidor (sin programas externos); lo que el hosting no permite leer se muestra como «sin dato». En hosting compartido, la CPU y la memoria del <em>equipo</em> pueden diferir de lo asignado a su plan: cuando el hosting informa límites (cgroup), se usan esos. <a href="{{ route('app.sistema.recursos', ['rango' => $rango]) }}">Actualizar</a>.</p>

<div class="row">
  @foreach([
    ['CPU del servidor', $cpuPct, $cpuPct === null ? '—' : $cpuPct.' %', 'Carga 1/5/15 min: '.implode(' / ', array_map(fn ($v) => $v ?? '—', $s['cpu']['carga'])).' · núcleos: '.($s['cpu']['nucleos'] ?? '—').($s['cpu']['limite_cgroup'] ? ' · límite del plan: '.$s['cpu']['limite_cgroup'].' CPU' : '')],
    ['Memoria (RAM)', $memPct, $memPct === null ? '—' : $memPct.' %', $s['memoria']['cgroup_limite'] ? 'Plan: '.R::humano($s['memoria']['cgroup_usada']).' de '.R::humano($s['memoria']['cgroup_limite']).' · equipo: '.R::humano($s['memoria']['usada']).' de '.R::humano($s['memoria']['total']) : R::humano($s['memoria']['usada']).' de '.R::humano($s['memoria']['total']).' · swap usada: '.R::humano($s['memoria']['swap_usada'])],
    ['Disco del almacenamiento', $disPct, $disPct === null ? '—' : $disPct.' %', R::humano($s['disco']['usado']).' de '.R::humano($s['disco']['total']).' · libre '.R::humano($s['disco']['libre'])],
    ['Memoria PHP (pico por petición)', $phpPct, $phpPct === null ? R::humano($s['php']['memoria_pico']) : $phpPct.' %', R::humano($s['php']['memoria_pico']).' de memory_limit '.($s['php']['memory_limit'] === '-1' ? '(sin límite)' : $s['php']['memory_limit'])],
  ] as [$tit, $pct, $valor, $det])
  @php $n = R::nivel($pct); @endphp
  <div class="col-md-6 col-xl-3 mb-3"><div class="card h-100 nivel-{{ $n }}"><div class="card-body">
    <h2 class="h6">{{ $tit }}</h2>
    <p class="display-4 mb-1" style="font-size:2rem">{{ $valor }}</p>
    <div class="progress mb-1" aria-hidden="true"><div class="progress-bar barra-{{ $n }}" style="width: {{ min(100, max(0, $pct ?? 0)) }}%"></div></div>
    <p class="mb-1"><strong>Estado: {{ $niv[$n] }}</strong></p>
    <p class="small text-muted mb-0">{{ $det }}</p>
  </div></div></div>
  @endforeach
</div>

<h2 class="h4 mt-3">Aplicación y base de datos</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Indicadores de la aplicación</caption><thead><tr><th scope="col">Indicador</th><th scope="col">Valor</th></tr></thead><tbody>
<tr><th scope="row">Base de datos ({{ $s['bd']['driver'] }})</th><td>{{ R::humano($s['bd']['bytes']) }}</td></tr>
<tr><th scope="row">Archivos en el repositorio</th><td>{{ $s['app']['archivos_n'] ?? '—' }} archivos · {{ R::humano($s['app']['archivos_bytes']) }} (cifrados)</td></tr>
<tr><th scope="row">Sesiones activas</th><td>{{ $s['app']['sesiones'] ?? '—' }}</td></tr>
<tr><th scope="row">Cola de trabajos</th><td>{{ $s['app']['cola_pendiente'] ?? '—' }} pendientes · <span @if(($s['app']['cola_fallida'] ?? 0) > 0) class="text-danger font-weight-bold" @endif>{{ $s['app']['cola_fallida'] ?? '—' }} fallidos</span></td></tr>
<tr><th scope="row">Logs de la aplicación</th><td>{{ R::humano($s['app']['logs_bytes']) }}</td></tr>
<tr><th scope="row">Usuarios activos / expedientes</th><td>{{ $s['app']['usuarios_activos'] ?? '—' }} / {{ $s['app']['expedientes'] ?? '—' }}</td></tr>
<tr><th scope="row">PHP</th><td>{{ $s['php']['version'] }} ({{ $s['php']['sapi'] }}) · memoria de esta petición {{ R::humano($s['php']['memoria_peticion']) }} · tiempo máx. {{ $s['php']['max_execution_time'] }} s</td></tr>
<tr><th scope="row">OPcache</th><td>@if($s['php']['opcache']){{ $s['php']['opcache']['hit_rate'] ?? '—' }} % de aciertos · {{ R::humano($s['php']['opcache']['usada']) }} usados · {{ $s['php']['opcache']['scripts'] }} scripts @else desactivado o no disponible @endif</td></tr>
<tr><th scope="row">Sistema</th><td>{{ $s['sistema']['so'] }} ({{ $s['sistema']['arquitectura'] }}) · en marcha hace {{ R::duracion($s['sistema']['uptime_s']) }}</td></tr>
</tbody></table></div>
@if($s['bd']['tablas'])
<details class="mb-3"><summary>Tablas más grandes</summary><table class="table table-sm mt-2"><caption class="sr-only">Tablas más grandes</caption><thead><tr><th scope="col">Tabla</th><th scope="col">Tamaño</th><th scope="col">Filas (aprox.)</th></tr></thead><tbody>@foreach($s['bd']['tablas'] as $t)<tr><td>{{ $t['tabla'] }}</td><td>{{ R::humano($t['bytes']) }}</td><td>{{ $t['filas'] }}</td></tr>@endforeach</tbody></table></details>
@endif

<h2 class="h4 mt-4">Histórico</h2>
<nav aria-label="Rango del histórico" class="mb-3">@foreach(['24h' => 'Últimas 24 horas', '7d' => '7 días', '30d' => '30 días'] as $k => $e)<a class="btn btn-sm {{ $rango === $k ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('app.sistema.recursos', ['rango' => $k]) }}" @if($rango === $k) aria-current="true" @endif>{{ $e }}</a> @endforeach</nav>
<div class="row"><div class="col-lg-6">@include('partials.grafico_linea', ['titulo' => 'CPU del servidor (%)', 'puntos' => $col('cpu_pct'), 'max' => 100, 'unidad' => '%'])</div>
<div class="col-lg-6">@include('partials.grafico_linea', ['titulo' => 'Memoria usada (%)', 'puntos' => $memSerie, 'max' => 100, 'unidad' => '%'])</div>
<div class="col-lg-6">@include('partials.grafico_linea', ['titulo' => 'Carga del sistema (1 min)', 'puntos' => $col('carga1'), 'max' => $maxCarga, 'unidad' => ''])</div>
<div class="col-lg-6">@include('partials.grafico_linea', ['titulo' => 'Disco usado (%)', 'puntos' => $disSerie, 'max' => 100, 'unidad' => '%'])</div>
<div class="col-lg-6">@include('partials.grafico_linea', ['titulo' => 'Tamaño de la base de datos (MB)', 'puntos' => $bdSerie, 'max' => $maxBd, 'unidad' => ' MB'])</div></div>
<p class="small text-muted">Umbrales: atención desde {{ R::AVISO }} %, crítico desde {{ R::CRITICO }} %. El estado se indica siempre con texto, no solo con color.</p>
@endsection
