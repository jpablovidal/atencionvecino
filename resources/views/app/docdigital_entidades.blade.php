@extends('layouts.app')
@section('titulo', 'Entidades de DocDigital')
@section('contenido')
<nav aria-label="Ruta de navegación"><a href="{{ route('app.docdigital') }}">&larr; Volver a DocDigital</a></nav>
<h1>Entidades de DocDigital</h1>
<p class="lead-sub">Para enviar un documento, DocDigital identifica a la entidad destinataria por su <strong>id</strong>. Busque la entidad por nombre y copie su id en la institución correspondiente (<em>Administración › Instituciones</em>, campo «Id de la entidad en DocDigital»).</p>
<form method="get" class="form-inline mb-3"><label class="mr-2" for="q">Nombre de la entidad (mínimo 3 letras)</label><input class="form-control mr-2" id="q" name="q" value="{{ $q }}" minlength="3" maxlength="100" required><button class="btn btn-primary">Buscar</button></form>
@if($error)<div class="alert alert-info" role="status">{{ $error }}</div>@endif
@if($filas !== null)
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Entidades encontradas</caption><thead><tr><th scope="col">Id en DocDigital</th><th scope="col">Entidad</th><th scope="col">Id de la entidad padre</th></tr></thead><tbody>
@forelse($filas as $f)<tr><td><code>{{ $f['id'] }}</code></td><td>{{ $f['nombre'] }}</td><td>{{ $f['padre'] ?? '—' }}</td></tr>@empty<tr><td colspan="3">No se encontraron entidades con ese nombre.</td></tr>@endforelse</tbody></table></div>
@endif
@endsection
