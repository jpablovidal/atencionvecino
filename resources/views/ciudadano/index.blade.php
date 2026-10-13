@extends('layouts.app')
@section('titulo', 'Mis solicitudes')
@section('contenido')
<h1>Mis solicitudes</h1>
<p>Bienvenido(a), <strong>{{ $c['nombres'] }} {{ $c['apellidos'] }}</strong> (RUN {{ $c['run'] }}).</p>
<h2 class="h4">Ingresar una nueva solicitud</h2>
@forelse($tramites as $direccion => $lista)<h3 class="h6 mt-3">{{ $direccion }}</h3><ul>@foreach($lista as $t)<li><a href="{{ route('ciudadano.nueva',$t) }}">{{ $t->nombre }}</a>@if($t->descripcion)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($t->descripcion, 140) }}</small>@endif</li>@endforeach</ul>@empty<p>No hay trámites disponibles por ahora.</p>@endforelse
<h2 class="h4 mt-4">Historial</h2>
<table class="table table-sm"><caption class="sr-only">Mis solicitudes</caption><thead><tr><th scope="col">Folio</th><th scope="col">Trámite</th><th scope="col">Fecha</th><th scope="col">Estado</th></tr></thead><tbody>
@forelse($solicitudes as $s)<tr><td><a href="{{ route('ciudadano.solicitud',$s->folio) }}">{{ $s->folio }}</a></td><td>{{ $s->tramite->nombre }}</td><td>{{ $s->created_at->format('d-m-Y') }}</td><td><span class="estado estado-{{ $s->estado }}">{{ str_replace('_',' ',$s->estado) }}</span></td></tr>
@empty<tr><td colspan="4">Aún no ha ingresado solicitudes.</td></tr>@endforelse</tbody></table>
@endsection
