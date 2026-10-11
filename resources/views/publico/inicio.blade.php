@extends('layouts.app')
@section('titulo', 'Inicio')
@section('contenido')
<h1>Atención al vecino</h1>
<p class="lead">Presente solicitudes y trámites a la Municipalidad de Santo Domingo, haga seguimiento y reciba sus respuestas, todo en línea.</p>
<div class="row">
  <div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body"><h2 class="h5">Ingresar una solicitud</h2><p>Con ClaveÚnica.</p><a class="btn btn-primary" href="{{ route('ingreso.via','ciudadano') }}">Ingresar</a></div></div></div>
  <div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body"><h2 class="h5">Validar un documento</h2><p>Compruebe la autenticidad con el código SDD.</p><a class="btn btn-outline-primary" href="{{ route('validar.form') }}">Validar</a></div></div></div>
  <div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body"><h2 class="h5">Documentos públicos</h2><p>Decretos, ordenanzas y otros.</p><a class="btn btn-outline-primary" href="{{ route('publico.listado') }}">Ver listado</a></div></div></div>
</div>
<p class="mt-3"><a href="{{ route('ingreso.via','funcionario') }}">Acceso funcionarios</a></p>
@endsection
