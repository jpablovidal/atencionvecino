@extends('layouts.app')
@section('titulo', 'Conexiones y mapa')
@php
  use App\Services\Sistema\ConexionesService as C;
  $nombreDir = ['todas' => 'Entrantes y salientes', 'entrante' => 'Entrantes (hacia el sistema)', 'saliente' => 'Salientes (desde el sistema)'];
  $pctErr = $r['total'] ? round(100 * $r['errores'] / $r['total'], 1) : 0;
  $serie = array_map(fn ($t, $v) => [$r['por_dia'] ? \Illuminate\Support\Carbon::parse($t)->format('d-m') : \Illuminate\Support\Carbon::parse($t)->format('d-m H:i'), (float) $v], array_keys($r['serie']), array_values($r['serie']));
  $maxSerie = max(1, (float) collect($r['serie'])->max());
@endphp
@section('contenido')
<h1>Conexiones y mapa</h1>
<p class="text-muted">De dónde llegan las conexiones al sistema (<strong>entrantes</strong>) y a qué servicios se conecta el sistema (<strong>salientes</strong>: ClaveÚnica, FirmaGob, DocDigital, Google, etc.). Se cuentan por hora y por IP; <strong>no se guarda la dirección visitada, el contenido ni el nombre de las personas</strong>. La IP es un dato personal: este panel es solo para administración y auditoría y los datos se eliminan a los {{ $retencion }} días.</p>
@unless($activo)<div class="alert alert-warning" role="status">El registro de conexiones está desactivado (<code>SGD_REGISTRAR_CONEXIONES=false</code>): solo se muestran datos históricos.</div>@endunless

<nav aria-label="Filtros" class="mb-3 d-flex flex-wrap">
  <div class="mr-4 mb-2">@foreach(['24h' => 'Últimas 24 horas', '7d' => '7 días', '30d' => '30 días'] as $k => $e)<a class="btn btn-sm {{ $r['rango'] === $k ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('app.sistema.conexiones', ['rango' => $k, 'direccion' => $r['direccion']]) }}" @if($r['rango'] === $k) aria-current="true" @endif>{{ $e }}</a> @endforeach</div>
  <div class="mb-2">@foreach($nombreDir as $k => $e)<a class="btn btn-sm {{ $r['direccion'] === $k ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('app.sistema.conexiones', ['rango' => $r['rango'], 'direccion' => $k]) }}" @if($r['direccion'] === $k) aria-current="true" @endif>{{ $e }}</a> @endforeach</div>
</nav>

<div class="row">
  @foreach([['Conexiones', number_format($r['total'], 0, ',', '.')], ['IP distintas (entrantes)', number_format($r['ips_unicas'], 0, ',', '.')], ['Países', $r['paises_n']], ['Con error (HTTP ≥ 400)', number_format($r['errores'], 0, ',', '.').' ('.$pctErr.' %)']] as [$t, $v])
  <div class="col-6 col-lg-3 mb-3"><div class="card h-100"><div class="card-body"><h2 class="h6">{{ $t }}</h2><p class="mb-0" style="font-size:1.8rem">{{ $v }}</p></div></div></div>
  @endforeach
</div>

<h2 class="h4 mt-3">Mapa por país — {{ $nombreDir[$r['direccion']] }}</h2>
@include('partials.mapa_paises', ['paises' => $r['paises'], 'max' => $r['maximo_pais']])
<details open class="mb-4"><summary>Conexiones por país (tabla)</summary>
<div class="table-responsive"><table class="table table-sm mt-2"><caption class="sr-only">Conexiones por país</caption><thead><tr><th scope="col">País</th><th scope="col">Conexiones</th><th scope="col">% del total</th></tr></thead><tbody>
@forelse(array_slice($r['paises'], 0, 25, true) as $cc => $n)<tr><th scope="row">{{ C::nombrePais($cc) }}@if($cc !== 'LAN' && $cc !== '??') <small class="text-muted font-weight-normal">({{ $cc }})</small>@endif</th><td>{{ number_format($n, 0, ',', '.') }}</td><td>{{ $r['total'] ? round(100 * $n / $r['total'], 1) : 0 }} %</td></tr>
@empty<tr><td colspan="3">Sin conexiones en el período.</td></tr>@endforelse</tbody></table></div></details>

@if($r['direccion'] !== 'saliente')
<h2 class="h4">Ranking de IP con más conexiones (entrantes)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Las 20 IP con más conexiones</caption><thead><tr><th scope="col">#</th><th scope="col">IP</th><th scope="col">País</th><th scope="col">Conexiones</th><th scope="col">Errores</th><th scope="col">Tipo de uso</th><th scope="col">Última</th></tr></thead><tbody>
@forelse($r['ranking_ips'] as $i => $f)<tr><td>{{ $i + 1 }}</td><td><code>{{ $f->clave }}</code></td><td>{{ C::nombrePais($f->pais) }}</td><td>{{ number_format($f->hits, 0, ',', '.') }}</td><td @if($f->hits > 20 && $f->errores / $f->hits > .5) class="font-weight-bold text-danger" @endif>{{ number_format($f->errores, 0, ',', '.') }} ({{ round(100 * $f->errores / max(1, $f->hits)) }} %)@if($f->hits > 20 && $f->errores / $f->hits > .5) <span class="sr-only">— proporción alta de errores: posible escaneo</span>@endif</td><td><small>{{ $f->grupos }}</small></td><td><small>{{ \Illuminate\Support\Carbon::parse($f->ultimo)->timezone('America/Santiago')->format('d-m H:i') }}</small></td></tr>
@empty<tr><td colspan="7">Sin conexiones en el período.</td></tr>@endforelse</tbody></table></div>
<p class="small text-muted">Una proporción alta de errores (404/403/429) con muchas conexiones suele indicar escaneo automatizado. Para bloquear una IP use el firewall del hosting (cPanel → <em>IP Blocker</em>).</p>
@endif

@if($r['direccion'] !== 'entrante')
<h2 class="h4 mt-4">Destinos con más conexiones (salientes)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Destinos salientes</caption><thead><tr><th scope="col">#</th><th scope="col">Destino</th><th scope="col">País</th><th scope="col">Conexiones</th><th scope="col">Con error</th><th scope="col">Última</th></tr></thead><tbody>
@forelse($r['ranking_destinos'] as $i => $f)<tr><td>{{ $i + 1 }}</td><td><code>{{ $f->clave }}</code></td><td>{{ C::nombrePais($f->pais) }}</td><td>{{ number_format($f->hits, 0, ',', '.') }}</td><td>{{ number_format($f->errores, 0, ',', '.') }}</td><td><small>{{ \Illuminate\Support\Carbon::parse($f->ultimo)->timezone('America/Santiago')->format('d-m H:i') }}</small></td></tr>
@empty<tr><td colspan="6">Sin conexiones salientes en el período.</td></tr>@endforelse</tbody></table></div>
@endif

<h2 class="h4 mt-4">Evolución</h2>
@include('partials.grafico_linea', ['titulo' => 'Conexiones '.($r['por_dia'] ? 'por día' : 'por hora'), 'puntos' => $serie, 'max' => $maxSerie, 'unidad' => ''])
@if($r['grupos'])
<h2 class="h4">Por tipo de ruta</h2>
<ul class="list-inline">@foreach($r['grupos'] as $g => $n)<li class="list-inline-item"><span class="badge badge-secondary">{{ ['publico' => 'Público', 'ingreso' => 'Ingreso', 'ciudadanos' => 'Ciudadanos', 'funcionarios' => 'Funcionarios', 'integracion' => 'Integraciones'][$g] ?? $g }}: {{ number_format($n, 0, ',', '.') }}</span></li>@endforeach</ul>
@endif
<p class="small text-muted">Geolocalización por país: base «IP to Country Lite» de <a href="https://db-ip.com" rel="noopener noreferrer">DB-IP.com</a> (licencia CC BY 4.0) incluida en el sistema, sin consultas externas; mapa: Natural Earth (dominio público). La ubicación por IP es aproximada: las conexiones por VPN, proxy o móviles pueden aparecer en otro país. «Red interna / local» agrupa direcciones privadas.</p>
@endsection
