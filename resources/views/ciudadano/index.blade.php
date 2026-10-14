@extends('layouts.app')
@section('titulo', 'Mis solicitudes')
@section('contenido')
<h1>Mis solicitudes</h1>
<p class="lead-sub">Bienvenido(a), <strong>{{ $c['nombres'] }} {{ $c['apellidos'] }}</strong> (RUN {{ $c['run'] }}).</p>
@include('partials.kpis', ['kpis' => [
  ['icono' => 'clock', 'valor' => $resumen['curso'], 'etiqueta' => 'Solicitudes en trámite', 'nivel' => 'info'],
  ['icono' => 'alert', 'valor' => $resumen['devuelta'], 'etiqueta' => 'Devueltas: requieren su respuesta', 'nivel' => $resumen['devuelta'] > 0 ? 'aviso' : 'ok'],
  ['icono' => 'check-circle', 'valor' => $resumen['finalizada'], 'etiqueta' => 'Finalizadas', 'nivel' => 'ok'],
]])
@if($resumen['devuelta'] > 0)
<div class="dash-alertas">
@foreach($solicitudes->where('estado','devuelta') as $s)
  <a class="alerta-card alerta-aviso" href="{{ route('ciudadano.solicitud',$s->folio) }}"><span class="alerta-chip">Devuelta</span><strong>{{ $s->tramite->nombre }}</strong><small>Folio {{ $s->folio }} · responda para continuar</small></a>
@endforeach
</div>
@endif

<h2 class="seccion">Trámites rápidos</h2>
<form method="get" action="{{ route('catalogo') }}" class="buscador-tramites" role="search"><label class="sr-only" for="q-ciu">Buscar un trámite</label>
  <div class="input-group"><input class="form-control" id="q-ciu" name="q" maxlength="100" placeholder="Buscar un trámite o servicio"><div class="input-group-append"><button class="btn btn-primary">Buscar</button></div></div></form>
@php $listado = $tramites->flatten(1)->take(6); @endphp
@forelse($listado as $t)
<div class="tramite-rapido"><div><strong>{{ $t->nombre }}</strong><small>{{ $t->propietaria?->nombre }} · Respuesta en hasta {{ $t->plazo_dias }} días</small>@if($t->descripcion)<span>{{ \Illuminate\Support\Str::limit($t->descripcion, 140) }}</span>@endif</div>
  <a class="btn btn-primary" href="{{ route('ciudadano.nueva',$t) }}">Realizar trámite<span class="sr-only"> {{ $t->nombre }}</span></a></div>
@empty<p>No hay trámites disponibles por ahora.</p>@endforelse
<p class="small mt-2"><a href="{{ route('catalogo') }}">Ver todos los trámites y servicios</a></p>

<h2 class="seccion">Historial de solicitudes</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Mis solicitudes</caption><thead><tr><th scope="col">Folio</th><th scope="col">Trámite</th><th scope="col">Fecha</th><th scope="col">Estado</th></tr></thead><tbody>
@forelse($solicitudes as $s)<tr><td><a href="{{ route('ciudadano.solicitud',$s->folio) }}">{{ $s->folio }}</a></td><td>{{ $s->tramite->nombre }}</td><td>{{ $s->created_at->format('d-m-Y') }}</td><td><span class="estado estado-{{ $s->estado }}">{{ str_replace('_',' ',$s->estado) }}</span></td></tr>
@empty<tr><td colspan="4">Aún no ha ingresado solicitudes.</td></tr>@endforelse</tbody></table></div>
@endsection
