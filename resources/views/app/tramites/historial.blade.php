@extends('layouts.app')
@section('titulo', 'Historial del trámite')
@section('contenido')
<h1>Historial: {{ $tramite->nombre }}</h1>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Versiones</caption><thead><tr><th scope="col">Versión</th><th scope="col">Evento</th><th scope="col">Estado</th><th scope="col">Fecha</th><th scope="col">Documentos requeridos</th></tr></thead><tbody>
@foreach($versiones as $v)@php $c = $v->contenido(); @endphp
<tr><td>{{ $v->version }}</td><td>{{ $v->evento }}</td><td>{{ $v->estado }}</td><td>{{ $v->created_at?->format('d-m-Y H:i') }}</td><td>{{ collect($c['anexos'] ?? [])->map(fn($a)=>$a['nombre'].($a['obligatorio']?' *':''))->implode(', ') ?: '—' }}</td></tr>@endforeach</tbody></table></div>
<p class="small text-muted">* obligatorio. Las versiones son inmutables.</p>
<a class="btn btn-outline-secondary" href="{{ route('app.tramites.editar',$tramite->id) }}">Volver</a>
@endsection
