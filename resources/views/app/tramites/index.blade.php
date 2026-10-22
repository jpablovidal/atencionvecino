@extends('layouts.app')
@section('titulo', 'Trámites y servicios')
@section('contenido')
<div class="d-flex justify-content-between"><h1>Trámites y servicios</h1><span class="align-self-center"><a class="btn btn-outline-secondary mr-2" href="{{ route('app.tramites.listas') }}">Listados de opciones</a><a class="btn btn-primary" href="{{ route('app.tramites.crear') }}">Nuevo trámite</a></span></div>
<p class="text-muted">Trámites de {{ \App\Services\TramiteService::veTodas($u) ? 'todas las direcciones' : 'su dirección municipal' }}. Un trámite en <strong>borrador</strong> no es visible para la ciudadanía; al <strong>publicarlo</strong> los vecinos pueden solicitarlo adjuntando los documentos requeridos, lo que genera un expediente.</p>
<form method="get" class="form-inline mb-3"><label class="mr-2" for="estado">Estado</label><select class="form-control mr-2" id="estado" name="estado"><option value="">Todos</option>@foreach(['borrador','publicado','deshabilitado'] as $e)<option value="{{ $e }}" @selected($filtro===$e)>{{ ucfirst($e) }}</option>@endforeach</select><button class="btn btn-outline-secondary">Filtrar</button></form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Trámites</caption><thead><tr><th scope="col">Trámite</th><th scope="col">Dirección</th><th scope="col">Estado</th><th scope="col">Versión</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($tramites as $t)
<tr><td>{{ $t->nombre }}<br><small class="text-muted">{{ $t->codigo_interno }}</small></td><td>{{ $t->propietaria?->nombre }}</td><td><span class="estado estado-{{ $t->estado }}">{{ $t->estado }}</span></td><td>{{ $t->version }}</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.tramites.editar',$t->id) }}">Editar<span class="sr-only"> {{ $t->nombre }}</span></a>
<a class="btn btn-sm btn-outline-secondary" href="{{ route('app.tramites.vista',$t->id) }}">Vista previa<span class="sr-only"> de {{ $t->nombre }}</span></a></td></tr>
@empty<tr><td colspan="5">No hay trámites. Cree el primero con «Nuevo trámite».</td></tr>@endforelse</tbody></table></div>
@endsection
