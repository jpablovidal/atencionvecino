@extends('layouts.app')
@section('titulo', 'Documentos públicos')
@section('contenido')
<h1>Documentos públicos</h1>
<form method="get" class="form-inline mb-3">
  <label class="mr-2" for="q">Buscar</label><input class="form-control mr-3" id="q" name="q" value="{{ $f['q'] ?? '' }}" maxlength="100">
  <label class="mr-2" for="tipo">Tipo</label><select class="form-control mr-3" id="tipo" name="tipo"><option value="">Todos</option>@foreach($tipos as $t)<option value="{{ $t->id }}" @selected(($f['tipo'] ?? '')==$t->id)>{{ $t->nombre }}</option>@endforeach</select>
  <label class="mr-2" for="anio">Año</label><input class="form-control mr-3" id="anio" name="anio" type="number" value="{{ $f['anio'] ?? '' }}">
  <button class="btn btn-primary">Filtrar</button>
</form>
<table class="table table-sm table-striped"><caption class="sr-only">Documentos públicos vigentes</caption>
<thead><tr><th>Documento</th><th>Título</th><th>Fecha de firma</th><th>Acceso</th></tr></thead><tbody>
@forelse($docs as $d)
<tr><td>{{ $d->numeroFormateado() }}</td><td>{{ $d->titulo }}</td><td>{{ $d->fecha_firma?->format('d-m-Y') }}</td>
<td><a href="{{ route('validar',$d->codigo_validacion) }}">Ver y validar</a> · <a href="{{ route('validar.copia',$d->codigo_validacion) }}">PDF</a></td></tr>
@empty<tr><td colspan="4">No hay documentos.</td></tr>@endforelse
</tbody></table>
{{ $docs->links() }}
@endsection
