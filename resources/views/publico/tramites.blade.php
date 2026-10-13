@extends('layouts.app')
@section('titulo', 'Trámites y servicios')
@section('contenido')
<h1>Trámites y servicios municipales</h1>
<p class="lead">Encuentre qué necesita para hacer su trámite en la Municipalidad de Santo Domingo y solicítelo en línea con ClaveÚnica.</p>
<form method="get" class="mb-3" role="search"><div class="form-row align-items-end">
  <div class="col-md-6 mb-2"><label for="q">Buscar un trámite</label><input class="form-control" id="q" name="q" value="{{ $f['q'] }}" maxlength="100"></div>
  <div class="col-md-4 mb-2"><label for="direccion">Dirección municipal</label><select class="form-control" id="direccion" name="direccion"><option value="">Todas</option>@foreach($direcciones as $d)<option value="{{ $d->id }}" @selected((int)$f['direccion']===$d->id)>{{ $d->nombre }} ({{ $d->publicados_count }})</option>@endforeach</select></div>
  <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block">Buscar</button></div></div></form>
<p class="small text-muted" role="status">{{ $tramites->total() }} {{ $tramites->total() === 1 ? 'trámite encontrado' : 'trámites encontrados' }}.@if($f['q'] || $f['direccion']) <a href="{{ route('catalogo') }}">Quitar filtros</a>@endif</p>
<div class="row">
@forelse($tramites as $t)
<div class="col-md-6 mb-3"><article class="card h-100"><div class="card-body">
  <h2 class="h5"><a href="{{ route('catalogo.ficha', [$t->id, \Illuminate\Support\Str::slug($t->nombre)]) }}">{{ $t->nombre }}</a></h2>
  <p class="small text-muted mb-2">{{ $t->propietaria?->nombre }} · Respuesta en hasta {{ $t->plazo_dias }} días</p>
  @if($t->descripcion)<p class="mb-0">{{ \Illuminate\Support\Str::limit($t->descripcion, 180) }}</p>@endif
</div></article></div>
@empty
<div class="col-12"><p>No hay trámites que coincidan con su búsqueda.</p></div>
@endforelse
</div>
{{ $tramites->links() }}
@endsection
