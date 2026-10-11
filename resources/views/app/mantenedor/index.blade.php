@extends('layouts.app')
@section('titulo', $def['titulo'])
@section('contenido')
<div class="d-flex justify-content-between"><h1>{{ $def['titulo'] }}</h1><a class="btn btn-primary align-self-center" href="{{ route('app.admin.crear',$entidad) }}">Nuevo</a></div>
<table class="table table-sm"><thead><tr>@foreach($def['cols'] as $c)<th>{{ $c }}</th>@endforeach<th>Estado</th><th></th></tr></thead><tbody>
@foreach($filas as $f)<tr>@foreach($def['cols'] as $c)<td>{{ $f->$c }}</td>@endforeach<td>{{ $f->activo ? 'activo' : 'inactivo' }}</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.editar',[$entidad,$f->id]) }}">Editar</a>
<form method="post" action="{{ route('app.admin.alternar',[$entidad,$f->id]) }}" class="d-inline" data-confirm="¿Cambiar estado?">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $f->activo ? 'Desactivar' : 'Activar' }}</button></form></td></tr>@endforeach</tbody></table>
@endsection
