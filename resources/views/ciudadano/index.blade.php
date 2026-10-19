@extends('layouts.app')
@section('titulo', 'Mis solicitudes')
@section('hero')
<h1 id="hero-titulo">Mis solicitudes</h1>
<p class="hero-sub">Bienvenido(a), <strong>{{ $c['nombres'] }} {{ $c['apellidos'] }}</strong> (RUN {{ $c['run'] }}). Siga sus trámites o inicie uno nuevo.</p>
@include('partials.buscador', ['id' => 'q-ciu', 'grande' => true, 'valor' => ''])
@endsection
@section('contenido')
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

<nav aria-label="Usted está en" class="portal-migas"><span class="portal-migas-et">Usted está en:</span><ol><li><a href="{{ route('inicio') }}">Inicio</a></li><li aria-current="page">Mis solicitudes</li></ol></nav>
<h2 class="seccion" id="mas-buscado">Lo más buscado</h2>
@php $listado = $tramites->flatten(1)->take(6); $icoDir = ['DOM' => 'building', 'DIDECO' => 'users', 'ALC' => 'home', 'SECMUN' => 'file-text', 'ARCH' => 'archive', 'TRANSP' => 'book']; @endphp
<ul class="portal-masbuscado" aria-labelledby="mas-buscado">
@forelse($listado as $t)
  <li><a href="{{ route('ciudadano.nueva',$t) }}"><span class="portal-ico portal-ico-g" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-{{ $icoDir[$t->propietaria?->codigo] ?? 'clipboard' }}"/></svg></span><span>{{ $t->nombre }}<small>{{ $t->propietaria?->nombre }} · Respuesta en hasta {{ $t->plazo_dias }} días · Realizar trámite</small></span></a></li>
@empty<li>No hay trámites disponibles por ahora.</li>@endforelse
</ul>
<p class="portal-todos"><a href="{{ route('catalogo') }}">Conoce todos los servicios municipales por temas</a></p>

<h2 class="seccion">Historial de solicitudes</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Mis solicitudes</caption><thead><tr><th scope="col">Solicitud N°</th><th scope="col">Expediente N°</th><th scope="col">Trámite</th><th scope="col">Fecha</th><th scope="col">Estado</th></tr></thead><tbody>
@forelse($solicitudes as $s)<tr><td><a href="{{ route('ciudadano.solicitud',$s->folio) }}">{{ $s->folio }}</a></td><td>{{ $s->expediente?->codigo ?? '—' }}</td><td>{{ $s->tramite->nombre }}</td><td>{{ $s->created_at->format('d-m-Y') }}</td><td><span class="estado estado-{{ $s->estado }}">{{ str_replace('_',' ',$s->estado) }}</span></td></tr>
@empty<tr><td colspan="5">Aún no ha ingresado solicitudes.</td></tr>@endforelse</tbody></table></div>
@endsection
