@extends('layouts.app')
@section('titulo', 'Seguimiento de solicitudes')
@section('contenido')
<h1>Seguimiento de solicitudes</h1>
<p class="lead-sub">Consulte en qué etapa está una solicitud, su estado, quién la tiene y si ya terminó. Solo lectura: no se muestran los documentos.</p>
@include('app.seguimiento._filtros', ['accion' => route('app.seguimiento')])
@if($buscando)
@include('app.seguimiento._tabla', ['filas' => $filas, 'pag' => $pag])
@else
<div class="vacio"><svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-search"/></svg> Elija uno o más filtros (RUN, fechas, dirección, etapa o trámite) y presione <strong>Buscar</strong>.</div>
@endif
<p class="small text-muted mt-3">Cada consulta queda registrada en la bitácora de auditoría. Los plazos se calculan desde el ingreso según el plazo de cada trámite ({{ config('sgd.plazos.habiles') ? 'días hábiles, sin feriados' : 'días corridos' }}).</p>
@endsection
