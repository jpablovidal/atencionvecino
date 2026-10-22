@extends('layouts.app')
@section('titulo', 'Listados de opciones')
@section('contenido')
<nav aria-label="Ruta de navegación"><a href="{{ route('app.tramites.index') }}">&larr; Volver a trámites</a></nav>
<div class="d-flex justify-content-between"><h1>Listados de opciones</h1><a class="btn btn-primary align-self-center" href="{{ route('app.tramites.listas.crear') }}">Nuevo listado</a></div>
<p class="text-muted">Listados que se reutilizan en los campos de lista de los trámites (por ejemplo «Tipo de vivienda», «Sector» o «Tarifa»). Los crea una vez y los elige al armar el formulario de cada trámite nuevo. Si una opción lleva un <strong>valor numérico</strong> (p. ej. <code>0.5;Media tarifa</code>), puede usarse en los cálculos.</p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Listados de opciones</caption><thead><tr><th scope="col">Listado</th><th scope="col">Opciones</th><th scope="col">Dirección</th><th scope="col">Se usa en</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($listas as $l)<tr><td>{{ $l->nombre }}@if($l->descripcion)<br><small class="text-muted">{{ $l->descripcion }}</small>@endif</td><td>{{ $l->items_count }}</td><td>{{ $l->unidad?->nombre ?? 'Administración' }}</td><td>{{ $usos[$l->id] ?? 0 }} campo(s)</td>
<td><span class="estado estado-{{ $l->activa ? 'vigente' : 'pendiente' }}">{{ $l->activa ? 'activo' : 'desactivado' }}</span></td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.tramites.listas.editar', $l) }}">{{ \App\Services\ListasOpcionesService::puedeEditar($u, $l) ? 'Editar' : 'Ver' }}<span class="sr-only"> {{ $l->nombre }}</span></a></td></tr>
@empty<tr><td colspan="6">Aún no hay listados. Cree el primero con «Nuevo listado».</td></tr>@endforelse</tbody></table></div>
@endsection
