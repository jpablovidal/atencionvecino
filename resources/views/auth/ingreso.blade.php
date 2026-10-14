@extends('layouts.app')
@section('titulo', 'Ingreso')
@section('login', '1')
@section('contenido')
<img class="sgd-login-logo" src="{{ asset('assets/img/logo-santo-domingo.png') }}" alt="Logo de la Municipalidad de Santo Domingo" width="57" height="92">
<h1>Iniciar sesión</h1>
@if($claveunica)
<p class="text-center small text-muted">El acceso se realiza únicamente con ClaveÚnica. Su RUN y nombre se obtienen de ClaveÚnica y no pueden modificarse.</p>
<a class="btn btn-primary btn-block mb-2" href="{{ route('ingreso.via','ciudadano') }}">Ingresar como vecino(a)</a>
<a class="btn btn-outline-primary btn-block mb-2" href="{{ route('ingreso.via','funcionario') }}">Ingresar como funcionario(a)</a>
@endif
@if($pruebas)
<div class="border-top pt-3 mt-3">
  <h2 class="h6">Ingreso de pruebas</h2>
  <p class="small text-muted">Ambiente de pruebas: ingrese con el usuario y la contraseña entregados por el equipo del proyecto.</p>
  <a class="btn btn-warning btn-block" href="{{ route('ingreso.pruebas') }}">Ingresar con usuario y contraseña</a>
</div>
@endif
<p class="text-center small mt-3 mb-0"><a href="{{ route('inicio') }}">Volver al inicio</a></p>
@endsection
