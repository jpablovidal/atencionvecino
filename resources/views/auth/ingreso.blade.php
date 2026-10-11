@extends('layouts.app')
@section('titulo', 'Ingreso')
@section('contenido')
<h1>Ingreso con ClaveÚnica</h1>
<p>El acceso al sistema se realiza únicamente con ClaveÚnica. Su RUN y nombre se obtienen de ClaveÚnica y no pueden modificarse.</p>
<div class="row">
  <div class="col-md-6 mb-3"><div class="card h-100"><div class="card-body">
    <h2 class="h4">Soy vecino(a)</h2><p>Ingrese solicitudes y revise su estado.</p>
    <a class="btn btn-primary" href="{{ route('ingreso.via','ciudadano') }}">Ingresar como ciudadano(a)</a>
  </div></div></div>
  <div class="col-md-6 mb-3"><div class="card h-100"><div class="card-body">
    <h2 class="h4">Soy funcionario(a)</h2><p>Su RUN debe estar habilitado en el sistema.</p>
    <a class="btn btn-outline-primary" href="{{ route('ingreso.via','funcionario') }}">Ingresar como funcionario(a)</a>
  </div></div></div>
</div>
@endsection
