@extends('layouts.app')
@section('titulo', 'Roles y permisos')
@section('contenido')
<div class="d-flex justify-content-between"><h1>Roles y permisos</h1><a class="btn btn-primary align-self-center" href="{{ route('app.admin.roles.crear') }}">Nuevo rol</a></div>
<p class="text-muted">Los 5 roles del sistema son fijos. Un rol personalizado parte de un <strong>rol base</strong> y solo puede recibir permisos que esa base admite (principio de mínimo privilegio y separación de funciones). La asignación a personas se hace en <a href="{{ route('app.admin.index', 'usuarios') }}">Usuarios</a>.</p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Roles</caption><thead><tr><th scope="col">Rol</th><th scope="col">Clave</th><th scope="col">Base</th><th scope="col">Tipo</th><th scope="col">Permisos</th><th scope="col">Usuarios</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($roles as $r)@php $n = $usuarios[$r->clave] ?? 0; $np = $r->sistema ? count(\App\Support\Permisos::deBase($r->rol_base)) : count($r->permisos()); @endphp
<tr><td>{{ $r->nombre }}<br><small class="text-muted">{{ $r->descripcion }}</small></td><td><code>{{ $r->clave }}</code></td><td>{{ $r->rol_base }}</td><td>{{ $r->sistema ? 'Sistema' : 'Personalizado' }}</td><td>{{ $np }}</td><td>{{ $n }}</td><td>{{ $r->activo ? 'activo' : 'inactivo' }}</td>
<td>@if($r->sistema)<a class="btn btn-sm btn-outline-secondary" href="{{ route('app.admin.roles.editar', $r) }}">Ver<span class="sr-only"> permisos de {{ $r->nombre }}</span></a>
@else<a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.roles.editar', $r) }}">Editar<span class="sr-only"> {{ $r->nombre }}</span></a>
<form method="post" action="{{ route('app.admin.roles.alternar', $r) }}" class="d-inline" data-confirm="¿Cambiar estado del rol?">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $r->activo ? 'Desactivar' : 'Activar' }}<span class="sr-only"> {{ $r->nombre }}</span></button></form>
@if($n === 0)<form method="post" action="{{ route('app.admin.roles.eliminar', $r) }}" class="d-inline" data-confirm="¿Eliminar el rol «{{ $r->nombre }}»?">@csrf<button class="btn btn-sm btn-outline-danger">Eliminar<span class="sr-only"> {{ $r->nombre }}</span></button></form>@endif @endif</td></tr>@endforeach</tbody></table></div>
@endsection
