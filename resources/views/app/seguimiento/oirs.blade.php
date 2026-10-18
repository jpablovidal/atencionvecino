@extends('layouts.app')
@section('titulo', 'Panel OIRS')
@section('contenido')
<h1>Panel OIRS</h1>
<p class="lead-sub">Solicitudes, estado, responsables y plazos. Solo lectura: no se muestran los documentos.</p>
@include('partials.kpis', ['kpis' => $kpis])
<nav aria-label="Vistas" class="mb-3"><ul class="nav nav-pills">
@foreach(['vencidas'=>'Plazos vencidos','por_vencer'=>'Por vencer','por_asignar'=>'Sin asignar','devueltas'=>'Devueltas','todas'=>'Todas'] as $k=>$v)
<li class="nav-item"><a class="nav-link {{ $vista===$k ? 'active' : '' }}" @if($vista===$k) aria-current="page" @endif href="{{ route('app.oirs', ['vista'=>$k]) }}">{{ $v }}</a></li>
@endforeach</ul></nav>
@include('app.seguimiento._filtros', ['accion' => route('app.oirs'), 'vista' => $vista])
@include('app.seguimiento._tabla', ['filas' => $filas, 'pag' => $pag])
<p class="small text-muted mt-3">«Por vencer»: faltan {{ config('sgd.plazos.aviso_dias') }} días o menos. Los plazos se cuentan desde el ingreso ({{ config('sgd.plazos.habiles') ? 'días hábiles, sin feriados' : 'días corridos' }}). Una solicitud devuelta al solicitante queda <em>suspendida</em> mientras espera su respuesta.</p>
@endsection
