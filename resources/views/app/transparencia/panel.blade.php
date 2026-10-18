@extends('layouts.app')
@section('titulo', 'Transparencia')
@section('contenido')
<h1>Transparencia activa y pasiva</h1>
<p class="lead-sub">Consultas de acceso a la información pública (Ley 20.285): gestión, plazos, requerimientos a las direcciones y derivaciones.</p>
@include('app.transparencia._nav')
@include('partials.kpis', ['kpis' => $kpis])
@include('app.seguimiento._filtros', ['accion' => route('app.transparencia')])
@include('app.seguimiento._tabla', ['filas' => $filas, 'pag' => $pag, 'modo' => 'transparencia'])
<p class="small text-muted mt-3">Solo se listan los trámites marcados como solicitud de acceso a la información. Abra el folio para gestionar el expediente, prorrogar el plazo, requerir a una dirección o derivar a otro órgano. Los plazos {{ config('sgd.plazos.habiles') ? '' : 'de cada trámite se cuentan en días hábiles si así está configurado (ver ' }}<a href="{{ route('app.transparencia.plazos') }}">Plazos y feriados</a>{{ config('sgd.plazos.habiles') ? '' : ')' }}.</p>
@endsection
