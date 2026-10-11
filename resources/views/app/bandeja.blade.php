@extends('layouts.app')
@section('titulo', 'Bandeja')
@section('contenido')
<h1>Bandeja</h1>
@if($pasos->isNotEmpty())
<h2 class="h4">Mis visaciones y firmas pendientes</h2>
<table class="table table-sm"><thead><tr><th scope="col">Documento</th><th scope="col">Acción</th><th scope="col">Expediente</th></tr></thead><tbody>
@foreach($pasos as $p)<tr><td><a href="{{ route('app.documento',$p->documento_id) }}">{{ $p->documento->titulo }}</a></td><td>{{ $p->tipo==='firma'?'Firmar':'Visar' }}</td><td>{{ $p->documento->expediente->codigo }}</td></tr>@endforeach</tbody></table>
@endif
<h2 class="h4">Solicitudes de mi unidad por asignar</h2>
<table class="table table-sm"><caption class="sr-only">Solicitudes por asignar</caption><thead><tr><th scope="col">Folio</th><th scope="col">Trámite</th><th scope="col">Ingreso</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($entrantes as $s)<tr><td>{{ $s->folio }}</td><td>{{ $s->tramite->nombre }}</td><td>{{ $s->created_at->format('d-m-Y H:i') }}</td><td><a class="btn btn-sm btn-primary" href="{{ route('app.solicitud',$s->folio) }}">Revisar</a></td></tr>
@empty<tr><td colspan="4">No hay solicitudes pendientes.</td></tr>@endforelse</tbody></table>
@if($porAsignar->isNotEmpty())
<h2 class="h4 mt-4">Comunicaciones oficiales recibidas por gestionar (DocDigital)</h2>
<table class="table table-sm"><thead><tr><th scope="col">Expediente</th><th scope="col">Asunto</th><th scope="col">Origen</th></tr></thead><tbody>
@foreach($porAsignar as $x)<tr><td><a href="{{ route('app.expediente',$x) }}">{{ $x->codigo }}</a></td><td>{{ $x->metadatos['identificacion']['nombre'] ?? '' }}</td><td>{{ $x->metadatos['relaciones']['institucion_origen'] ?? '—' }}</td></tr>@endforeach</tbody></table>
@endif
<h2 class="h4 mt-4">Mis expedientes</h2>
<table class="table table-sm"><thead><tr><th scope="col">Expediente</th><th scope="col">Trámite</th><th scope="col">Estado</th></tr></thead><tbody>
@forelse($expedientes as $e)<tr><td><a href="{{ route('app.expediente',$e) }}">{{ $e->codigo }}</a></td><td>{{ $e->solicitud?->tramite->nombre ?? 'Comunicación oficial (DocDigital)' }}</td><td><span class="estado estado-{{ $e->estado }}">{{ $e->estado }}</span></td></tr>
@empty<tr><td colspan="3">Sin expedientes.</td></tr>@endforelse</tbody></table>
@endsection
