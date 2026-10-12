@extends('layouts.app')
@section('titulo', $p->nombre)
@section('contenido')
<p><a href="{{ route('app.formatos') }}">← Formatos y plantillas</a></p>
<h1>{{ $p->nombre }}</h1>
<p class="text-muted">{{ $p->tipo->nombre }} · versión {{ $p->version }} · @if($p->alcance === 'todas')Toda la municipalidad @else {{ $p->unidades->pluck('nombre')->implode(', ') }} @endif @unless($p->activo)· <strong>inactiva</strong>@endunless</p>
@if($p->descripcion)<p>{{ $p->descripcion }}</p>@endif
<p>La vista usa datos de ejemplo. @if($puedeGestionar)<a class="btn btn-sm btn-primary" href="{{ route('app.admin.plantillas.editar', $p) }}">Editar</a>@endif <a class="btn btn-sm btn-outline-secondary" href="{{ route('app.formato.pdf', $p) }}" target="_blank" rel="noopener noreferrer">PDF de ejemplo<span class="sr-only"> (se abre en una pestaña nueva)</span></a></p>
<h2 class="h5">Vista previa</h2>
<div class="formato-vista" tabindex="0" aria-label="Vista previa del documento">{!! $html !!}</div>
<details class="mt-3"><summary>Campos de combinación disponibles</summary>
<table class="table table-sm mt-2"><caption class="sr-only">Campos de combinación</caption><thead><tr><th scope="col">Campo</th><th scope="col">Se reemplaza por</th></tr></thead><tbody>@foreach($variables as $k => $v)<tr><td><code>@{{ {{ $k }} }}</code></td><td>{{ $v[0] }}</td></tr>@endforeach</tbody></table></details>
@endsection
