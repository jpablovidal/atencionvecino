@extends('layouts.app')
@section('titulo', 'Comunicaciones oficiales')
@section('contenido')
<h1>Comunicaciones oficiales recibidas (DocDigital)</h1>
<p class="small text-muted">Se descargan cada 10 minutos cuando DocDigital está en modo API. Solo la unidad receptora y el auditor las ven.</p>
<table class="table table-sm"><caption class="sr-only">Comunicaciones recibidas</caption><thead><tr><th>Recibida</th><th>Origen</th><th>Asunto</th><th>ID DocDigital</th><th></th></tr></thead><tbody>
@forelse($filas as $c)<tr><td>{{ $c->evidencia_en }}</td><td>{{ $c->institucion_nombre ?? $c->institucion_codigo ?? '—' }}</td><td>{{ $c->asunto }}</td><td class="hash">{{ $c->evidencia }}</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.comunicacion.archivo',$c) }}">Descargar PDF</a></td></tr>@empty<tr><td colspan="5">Sin comunicaciones recibidas.</td></tr>@endforelse</tbody></table>
@endsection
