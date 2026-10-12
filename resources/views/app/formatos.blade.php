@extends('layouts.app')
@section('titulo', 'Formatos y plantillas')
@section('contenido')
<div class="d-flex justify-content-between"><h1>Formatos y plantillas</h1>@if($puedeGestionar)<a class="btn btn-outline-primary align-self-center" href="{{ route('app.admin.plantillas') }}">Administrar plantillas</a>@endif</div>
<p class="text-muted">Formatos oficiales disponibles para su unidad. Al crear un documento en un expediente puede partir desde uno de ellos.</p>
<form method="get" class="form-inline mb-3" aria-label="Buscar formatos">
  <label class="mr-2" for="q">Buscar</label><input class="form-control mr-3" id="q" name="q" maxlength="80" value="{{ $f['q'] }}">
  <label class="mr-2" for="tipo">Tipo</label><select class="form-control mr-3" id="tipo" name="tipo"><option value="">Todos</option>@foreach($tipos as $t)<option value="{{ $t->id }}" @selected((string) $f['tipo'] === (string) $t->id)>{{ $t->nombre }}</option>@endforeach</select>
  <label class="mr-2" for="unidad">Dirección o unidad</label><select class="form-control mr-3" id="unidad" name="unidad"><option value="">Todas</option>@foreach($unidades as $u)<option value="{{ $u->id }}" @selected((string) $f['unidad'] === (string) $u->id)>{{ $u->nombre }}</option>@endforeach</select>
  <button class="btn btn-outline-primary">Filtrar</button>
</form>
@forelse($filas as $tipo => $items)
<h2 class="h4 mt-4">{{ $tipo }}</h2>
<ul class="list-unstyled">
@foreach($items as $p)
  <li class="card card-body mb-2"><div class="d-flex justify-content-between flex-wrap">
    <div><strong><a href="{{ route('app.formato', $p) }}">{{ $p->nombre }}</a></strong>@if($p->descripcion)<br><span class="text-muted">{{ $p->descripcion }}</span>@endif
      <br><small>Disponible para: @if($p->alcance === 'todas')<span class="badge badge-secondary">Toda la municipalidad</span>@else @foreach($p->unidades as $u)<span class="badge badge-secondary">{{ $u->nombre }}</span> @endforeach @endif · versión {{ $p->version }}</small></div>
    <div class="align-self-center"><a class="btn btn-sm btn-outline-primary" href="{{ route('app.formato', $p) }}">Ver<span class="sr-only"> {{ $p->nombre }}</span></a> <a class="btn btn-sm btn-outline-secondary" href="{{ route('app.formato.pdf', $p) }}" target="_blank" rel="noopener noreferrer">PDF de ejemplo<span class="sr-only"> de {{ $p->nombre }} (se abre en una pestaña nueva)</span></a></div>
  </div></li>
@endforeach
</ul>
@empty
<p role="status">No hay formatos disponibles con esos criterios.</p>
@endforelse
@endsection
