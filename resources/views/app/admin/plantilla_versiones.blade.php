@extends('layouts.app')
@section('titulo', 'Historial de versiones')
@section('contenido')
<p><a href="{{ route('app.admin.plantillas.editar', $p) }}">← Volver a «{{ $p->nombre }}»</a></p>
<h1>Historial de versiones</h1>
<p class="text-muted">Cada guardado con cambios crea una versión inmutable. Restaurar una versión crea una versión nueva con ese contenido.</p>
<table class="table table-sm"><caption class="sr-only">Versiones</caption><thead><tr><th scope="col">Versión</th><th scope="col">Nombre</th><th scope="col">Fecha</th><th scope="col">Huella (SHA-256)</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($versiones as $v)<tr><td>{{ $v->version }}@if($v->version === $p->version) <span class="badge badge-secondary">actual</span>@endif</td><td>{{ $v->nombre }}</td><td>{{ $v->created_at?->timezone('America/Santiago')->format('d-m-Y H:i') }}</td><td><small>{{ substr($v->sha256, 0, 16) }}…</small></td>
<td><a class="btn btn-sm btn-outline-secondary" href="{{ route('app.admin.plantillas.version', [$p, $v->version]) }}">Ver<span class="sr-only"> versión {{ $v->version }}</span></a>
@if($v->version !== $p->version)<form method="post" action="{{ route('app.admin.plantillas.restaurar', [$p, $v->version]) }}" class="d-inline" data-confirm="¿Restaurar la versión {{ $v->version }}?">@csrf<button class="btn btn-sm btn-outline-primary">Restaurar<span class="sr-only"> versión {{ $v->version }}</span></button></form>@endif</td></tr>@endforeach</tbody></table>
@endsection
