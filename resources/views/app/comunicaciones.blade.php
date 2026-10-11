@extends('layouts.app')
@section('titulo', 'Comunicaciones oficiales')
@section('contenido')
<h1>Comunicaciones oficiales recibidas (DocDigital)</h1>
<p class="small text-muted">Se descargan cada 10 minutos cuando DocDigital está en modo API. Solo la unidad receptora y el auditor las ven.</p>
<table class="table table-sm"><caption class="sr-only">Comunicaciones recibidas</caption><thead><tr><th scope="col">Recibida</th><th scope="col">Origen</th><th scope="col">Asunto</th><th scope="col">ID DocDigital</th><th scope="col">Acuse</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($filas as $c)<tr><td>{{ $c->evidencia_en }}</td><td>{{ $c->institucion_nombre ?? $c->institucion_codigo ?? '—' }}</td><td>{{ $c->asunto }}</td><td class="hash">{{ $c->evidencia }}</td><td>{{ $c->acuse_estado }}</td>
<td>@if($c->expediente_id)<a class="btn btn-sm btn-primary" href="{{ route('app.expediente',$c->expediente_id) }}">Abrir expediente</a>@endif <a class="btn btn-sm btn-outline-primary" href="{{ route('app.comunicacion.archivo',$c) }}">Descargar PDF</a></td></tr>@empty<tr><td colspan="6">Sin comunicaciones recibidas.</td></tr>@endforelse</tbody></table>
@endsection
