@extends('layouts.app')
@section('titulo', 'Folios por tipo de documento')
@section('contenido')
<h1>Folios por tipo de documento</h1>
<p>El folio se asigna de forma <strong>atómica</strong> al completarse la última firma. Aquí define el formato, el relleno con ceros, el reinicio anual y el número inicial de cada tipo.</p>
<table class="table table-sm"><caption class="sr-only">Configuración de folios</caption>
<thead><tr><th scope="col">Tipo</th><th scope="col">Formato</th><th scope="col">Reinicio</th><th scope="col">Último emitido ({{ $anio }})</th><th scope="col">Próximo folio</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($filas as $f)<tr><td>{{ $f['tipo']->nombre }}@unless($f['tipo']->activo) <small>(inactivo)</small>@endunless</td><td><code>{{ $f['tipo']->folio_formato }}</code></td><td>{{ $f['tipo']->folio_reinicio_anual ? 'cada 1 de enero' : 'continuo' }}</td><td>{{ $f['ultimo'] }}</td><td><strong>{{ $f['proximo'] }}</strong></td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.folios.editar',$f['tipo']) }}">Configurar</a></td></tr>@endforeach</tbody></table>
@endsection
