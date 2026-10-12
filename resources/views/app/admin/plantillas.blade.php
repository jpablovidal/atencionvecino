@extends('layouts.app')
@section('titulo', 'Plantillas')
@section('contenido')
<div class="d-flex justify-content-between"><h1>Plantillas y formatos</h1><a class="btn btn-primary align-self-center" href="{{ route('app.admin.plantillas.crear') }}">Nueva plantilla</a></div>
<form method="get" class="form-inline mb-3" aria-label="Filtrar plantillas">
  <label class="mr-2" for="q">Buscar</label><input class="form-control mr-3" id="q" name="q" maxlength="80" value="{{ $f['q'] }}">
  <label class="mr-2" for="tipo">Tipo</label><select class="form-control mr-3" id="tipo" name="tipo"><option value="">Todos</option>@foreach($tipos as $t)<option value="{{ $t->id }}" @selected((string) $f['tipo'] === (string) $t->id)>{{ $t->nombre }}</option>@endforeach</select>
  <label class="mr-2" for="alcance">Alcance</label><select class="form-control mr-3" id="alcance" name="alcance"><option value="">Todos</option><option value="todas" @selected($f['alcance']==='todas')>Toda la municipalidad</option><option value="unidades" @selected($f['alcance']==='unidades')>Unidades específicas</option></select>
  <label class="mr-2" for="estado">Estado</label><select class="form-control mr-3" id="estado" name="estado"><option value="">Todos</option><option value="activo" @selected($f['estado']==='activo')>Activas</option><option value="inactivo" @selected($f['estado']==='inactivo')>Inactivas</option></select>
  <button class="btn btn-outline-primary">Filtrar</button>
</form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Plantillas</caption><thead><tr><th scope="col">Nombre</th><th scope="col">Tipo</th><th scope="col">Disponible para</th><th scope="col">Versión</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($filas as $p)<tr><td>{{ $p->nombre }}</td><td>{{ $p->tipo->nombre }}</td><td>@if($p->alcance === 'todas')Toda la municipalidad @else {{ $p->unidades->pluck('nombre')->implode(', ') }} @endif</td><td>{{ $p->version }}</td><td>{{ $p->activo ? 'activa' : 'inactiva' }}</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.plantillas.editar', $p) }}">Editar<span class="sr-only"> {{ $p->nombre }}</span></a>
<a class="btn btn-sm btn-outline-secondary" href="{{ route('app.formato', $p) }}">Ver<span class="sr-only"> {{ $p->nombre }}</span></a>
<form method="post" action="{{ route('app.admin.plantillas.duplicar', $p) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">Duplicar<span class="sr-only"> {{ $p->nombre }}</span></button></form>
<form method="post" action="{{ route('app.admin.plantillas.alternar', $p) }}" class="d-inline" data-confirm="¿Cambiar estado de «{{ $p->nombre }}»?">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $p->activo ? 'Desactivar' : 'Activar' }}<span class="sr-only"> {{ $p->nombre }}</span></button></form></td></tr>
@empty<tr><td colspan="6">No hay plantillas con esos criterios.</td></tr>@endforelse</tbody></table></div>
{{ $filas->links() }}
@endsection
