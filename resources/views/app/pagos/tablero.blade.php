@extends('layouts.app')
@section('titulo', 'Tablero de pagos')
@section('contenido')
<h1>Tablero de pagos</h1>
<p class="lead-sub">Estado de los decretos de pago y de los egresos de Tesorería. Los montos son en pesos chilenos. El plazo de 30 días para pagar una factura (Ley 21.131) es referencial (<strong>A VALIDAR</strong>).</p>
<form method="get" class="form-inline mb-3" aria-label="Elegir el mes">
 <label class="mr-2" for="mes">Mes de los egresos</label><input class="form-control mr-2" type="month" id="mes" name="mes" value="{{ $mes }}" max="{{ now('America/Santiago')->format('Y-m') }}"><button class="btn btn-outline-primary mr-3">Ver</button>
 <a class="btn btn-outline-secondary mr-2" href="{{ route('app.pagos') }}">Lista de decretos</a><a class="btn btn-outline-secondary" href="{{ route('app.pagos.tablero.csv') }}">Exportar por egresar (CSV)</a></form>
@include('partials.panel', ['panel' => $panel])
@endsection
