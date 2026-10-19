@extends('layouts.app')
@section('titulo', 'Historial del documento')
@section('contenido')
<nav aria-label="Ruta de navegación"><a href="{{ route('app.expediente', $e) }}">&larr; Volver al expediente {{ $e->codigo }}</a> · <a href="{{ route('app.documento', $d) }}">Abrir el documento</a></nav>
<h1>Historial: {{ $d->titulo }}</h1>
<p class="lead-sub">Cada edición del texto crea una <strong>versión nueva</strong> y reinicia la visación y la firma. Aquí se ve qué cambió respecto de la versión anterior y quién la editó, visó, rechazó o firmó.</p>
<ol class="list-unstyled">
@foreach($historial as $h) @php $v = $h['v']; $c = $h['cambios']; @endphp
<li class="card mb-3"><div class="card-body">
 <h2 class="h5 mb-1">Versión {{ $v->version }} @if($h['vigente'])<span class="chip">vigente</span>@endif</h2>
 <p class="small text-muted mb-2">{{ \Illuminate\Support\Carbon::parse($v->created_at)->timezone('America/Santiago')->format('d-m-Y H:i') }} · <code title="Huella SHA-256 del texto">{{ substr($v->html_sha256, 0, 12) }}…</code></p>
 <p class="mb-1"><strong>Qué cambió:</strong> {{ $c['resumen'] }}</p>
 @if(! empty($c['agregados']))<p class="small mb-1">Agregado o modificado (ejemplos): @foreach($c['agregados'] as $x)<q>{{ $x }}</q>@unless($loop->last) · @endunless @endforeach</p>@endif
 @if(! empty($c['quitados']))<p class="small mb-1">Quitado o reemplazado (ejemplos): @foreach($c['quitados'] as $x)<q>{{ $x }}</q>@unless($loop->last) · @endunless @endforeach</p>@endif
 <h3 class="h6 mt-2">Quién participó</h3>
 <ul class="mb-0">@forelse($h['eventos'] as $ev)<li>{{ ['editado' => 'Editó', 'visado' => 'Visó', 'rechazado' => 'Rechazó', 'firmado' => 'Firmó'][$ev->evento] ?? $ev->evento }}: <strong>{{ $ev->usuario?->nombreCompleto() ?? '—' }}</strong>@if($ev->usuario?->cargo), {{ $ev->usuario->cargo }}@endif <span class="small text-muted">({{ $ev->created_at?->timezone('America/Santiago')->format('d-m-Y H:i') }}@if($ev->detalle) · {{ $ev->detalle }}@endif)</span></li>@empty<li>Sin registros.</li>@endforelse</ul>
</div></li>
@endforeach
</ol>
@endsection
