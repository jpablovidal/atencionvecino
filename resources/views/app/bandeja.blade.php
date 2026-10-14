@extends('layouts.app')
@section('titulo', 'Inicio')
@section('contenido')
<h1>Inicio</h1>
<p class="lead-sub">Bienvenido(a), <strong>{{ $u->nombreCompleto() }}</strong>@if($u->cargo) · {{ $u->cargo }}@endif.</p>
@include('partials.kpis', ['kpis' => $kpis])

@if($pasos->isNotEmpty() || $entrantes->isNotEmpty() || $porAsignar->isNotEmpty())
<h2 class="seccion">Requieren su atención</h2>
<div class="dash-alertas">
@foreach($pasos as $p)
  <a class="alerta-card alerta-aviso" href="{{ route('app.documento',$p->documento_id) }}"><span class="alerta-chip">{{ $p->tipo==='firma' ? 'Firmar' : 'Visar' }}</span>
    <strong>{{ $p->documento->titulo }}</strong><small>Expediente {{ $p->documento->expediente->codigo }}</small></a>
@endforeach
@foreach($entrantes as $s)
  <a class="alerta-card alerta-info" href="{{ route('app.solicitud',$s->folio) }}"><span class="alerta-chip">Nueva solicitud</span>
    <strong>{{ $s->tramite->nombre }}</strong><small>Folio {{ $s->folio }} · ingresó el {{ $s->created_at->format('d-m-Y H:i') }}</small></a>
@endforeach
@if($porAsignar->isNotEmpty())<h3 class="grupo-alerta">Comunicaciones oficiales recibidas por gestionar (DocDigital)</h3>@endif
@foreach($porAsignar as $x)
  <a class="alerta-card alerta-info" href="{{ route('app.expediente',$x) }}"><span class="alerta-chip">DocDigital</span>
    <strong>{{ $x->metadatos['identificacion']['nombre'] ?? 'Comunicación oficial' }}</strong><small>{{ $x->codigo }} · origen {{ $x->metadatos['relaciones']['institucion_origen'] ?? '—' }}</small></a>
@endforeach
</div>
@elseif($u->operaExpedientes())
<div class="vacio"><svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-check-circle"/></svg> No tiene tareas pendientes. Las nuevas solicitudes de su dirección aparecerán aquí.</div>
@endif

@include('partials.accesos')

@if($u->operaExpedientes())
<h2 class="seccion">Mis expedientes</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Mis expedientes</caption><thead><tr><th scope="col">Expediente</th><th scope="col">Trámite</th><th scope="col">Estado</th></tr></thead><tbody>
@forelse($expedientes as $e)<tr><td><a href="{{ route('app.expediente',$e) }}">{{ $e->codigo }}</a></td><td>{{ $e->solicitud?->tramite->nombre ?? 'Comunicación oficial (DocDigital)' }}</td><td><span class="estado estado-{{ $e->estado }}">{{ $e->estado }}</span></td></tr>
@empty<tr><td colspan="3">Sin expedientes.</td></tr>@endforelse</tbody></table></div>
@endif
@endsection
