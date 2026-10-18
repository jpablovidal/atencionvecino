@extends('layouts.app')
@section('titulo', 'Panel de Alcaldía')
@section('contenido')
<h1>Panel de Alcaldía</h1>
<p class="lead-sub">Sus <strong>firmas pendientes</strong>, los <strong>decretos de pago</strong> en firma y el estado de las <strong>solicitudes</strong> de toda la municipalidad. Se actualiza cada vez que entra. El plazo de 30 días para pagar facturas (Ley 21.131) es referencial (A VALIDAR).</p>
<p><a class="btn btn-outline-primary mr-2" href="{{ route('app.bandeja') }}">Ir a mis documentos por firmar</a>@if(auth()->user()->tienePermiso('seguimiento.consultar'))<a class="btn btn-outline-secondary mr-2" href="{{ route('app.seguimiento') }}">Seguimiento de solicitudes</a>@endif @if(auth()->user()->tienePermiso('decretopago.ver'))<a class="btn btn-outline-secondary" href="{{ route('app.pagos.tablero') }}">Tablero de pagos</a>@endif</p>
@include('partials.panel', ['panel' => $panel])
@endsection
