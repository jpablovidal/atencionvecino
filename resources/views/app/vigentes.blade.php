@extends('layouts.app')
@section('titulo', 'Documentos vigentes')
@section('contenido')
<h1>Documentos vigentes</h1>
<table class="table table-sm"><caption class="sr-only">Documentos vigentes</caption><thead><tr><th>Documento</th><th>Título</th><th>Fecha firma</th><th>Expediente</th><th>Acceso</th><th>Enlace público</th></tr></thead><tbody>
@forelse($docs as $d)<tr><td>{{ $d->numeroFormateado() }}</td><td><a href="{{ route('app.documento',$d) }}">{{ $d->titulo }}</a></td><td>{{ $d->fecha_firma?->format('d-m-Y') }}</td><td>{{ $d->expediente->codigo }}</td><td>{{ $d->nivel_acceso }}{{ $d->sensible?' · sensible':'' }}</td>
<td><a href="{{ route('validar',$d->codigo_validacion) }}">{{ $d->codigo_validacion }}</a></td></tr>@empty<tr><td colspan="6">Sin documentos vigentes.</td></tr>@endforelse</tbody></table>
{{ $docs->links() }}
@endsection
