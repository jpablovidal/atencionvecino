@extends('layouts.app')
@section('titulo', 'Usuarios')
@section('contenido')
<div class="d-flex justify-content-between"><h1>Usuarios</h1><a class="btn btn-primary align-self-center" href="{{ route('app.admin.usuarios.crear') }}">Nuevo usuario</a></div>
<form method="get" class="form-inline mb-3" aria-label="Buscar usuarios">
  <label class="mr-2" for="q">Buscar</label><input class="form-control mr-3" id="q" name="q" maxlength="80" value="{{ $f['q'] }}" placeholder="Nombre, correo o RUN">
  <label class="mr-2" for="rol">Rol</label><select class="form-control mr-3" id="rol" name="rol"><option value="">Todos</option>@foreach($roles as $c => $r)<option value="{{ $c }}" @selected($f['rol']===$c)>{{ $r->nombre }}</option>@endforeach</select>
  <label class="mr-2" for="estado">Estado</label><select class="form-control mr-3" id="estado" name="estado"><option value="">Todos</option><option value="activo" @selected($f['estado']==='activo')>Activos</option><option value="inactivo" @selected($f['estado']==='inactivo')>Inactivos</option></select>
  <button class="btn btn-outline-primary">Filtrar</button>
</form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Usuarios del sistema</caption><thead><tr><th scope="col">RUN</th><th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th><th scope="col">Unidad</th><th scope="col">Último acceso</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($filas as $u)<tr><td>{{ $u->run }}</td><td>{{ $u->nombres }} {{ $u->apellidos }}@if($u->puede_firmar) <span class="badge badge-secondary">firma</span>@endif</td><td>{{ $u->correo }}</td><td>{{ $roles[$u->rol]->nombre ?? $u->rol }}</td><td>{{ $u->unidad?->nombre ?? '—' }}</td><td>{{ $u->ultimo_acceso ? \Illuminate\Support\Carbon::parse($u->ultimo_acceso)->format('d-m-Y H:i') : 'Nunca' }}</td><td>{{ $u->activo ? 'activo' : 'inactivo' }}</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.usuarios.editar', $u) }}">Editar<span class="sr-only"> a {{ $u->nombres }} {{ $u->apellidos }}</span></a>
<form method="post" action="{{ route('app.admin.usuarios.alternar', $u) }}" class="d-inline" data-confirm="¿Cambiar estado de {{ $u->nombres }} {{ $u->apellidos }}?">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $u->activo ? 'Desactivar' : 'Activar' }}<span class="sr-only"> a {{ $u->nombres }} {{ $u->apellidos }}</span></button></form></td></tr>@endforeach</tbody></table></div>
{{ $filas->links() }}
@endsection
