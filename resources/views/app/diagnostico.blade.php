@extends('layouts.app')
@section('titulo', 'Diagnóstico')
@section('contenido')
<h1>Diagnóstico del servidor</h1>
<p class="small text-muted">Solo administradores. No muestra secretos.</p>
<table class="table table-sm"><thead><tr><th>Comprobación</th><th>Resultado</th></tr></thead><tbody>
@foreach($checks as [$n,$ok])<tr><td>{{ $n }}</td><td>@if($ok)<span class="estado estado-vigente">OK</span>@else<span class="estado estado-fallida">REVISAR</span>@endif</td></tr>@endforeach</tbody></table>
<h2 class="h5">Parámetros PHP</h2>
<dl class="row">@foreach($info as $k=>$v)<dt class="col-sm-3">{{ $k }}</dt><dd class="col-sm-9">{{ $v }}</dd>@endforeach</dl>
@endsection
