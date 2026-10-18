@extends('layouts.app')
@section('titulo', 'Documentos vigentes')
@section('contenido')
<h1>Documentos vigentes</h1>
<table class="table table-sm"><caption class="sr-only">Documentos vigentes</caption><thead><tr><th scope="col">Documento</th><th scope="col">Título</th><th scope="col">Fecha firma</th><th scope="col">Expediente</th><th scope="col">Acceso</th><th scope="col">Enlace público</th></tr></thead><tbody>
@forelse($docs as $d)@if(in_array($d->id, $ocultos ?? [], true))<tr><td>—</td><td><em>Documento restringido</em></td><td>—</td><td>—</td><td>restringido</td><td>—</td></tr>@continue @endif<tr><td>{{ $d->numeroFormateado() }}</td><td><a href="{{ route('app.documento',$d) }}">{{ $d->titulo }}</a></td><td>{{ $d->fecha_firma?->format('d-m-Y') }}</td><td>{{ $d->expediente->codigo }}</td><td>{{ $d->nivel_acceso }}{{ $d->sensible?' · sensible':'' }}</td>
<td><a href="{{ route('validar',$d->codigo_validacion) }}">{{ $d->codigo_validacion }}</a></td></tr>@empty<tr><td colspan="6">Sin documentos vigentes.</td></tr>@endforelse</tbody></table>
{{ $docs->links() }}
@endsection
