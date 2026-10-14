@extends('layouts.app')
@section('titulo', 'Ingreso de pruebas')
@section('login', '1')
@section('contenido')
<img class="sgd-login-logo" src="{{ asset('assets/img/logo-santo-domingo.png') }}" alt="Logo de la Municipalidad de Santo Domingo" width="57" height="92">
<h1>Ingreso de pruebas</h1>
<p class="small text-muted text-center">Solo para el ambiente de pruebas. Use el RUN (con guion y dígito verificador) y la contraseña entregados.</p>
<form method="post" action="{{ route('ingreso.pruebas.entrar') }}" autocomplete="off">@csrf
  <div class="form-group"><label for="run">RUN (con guion)</label><input class="form-control" id="run" name="run" maxlength="15" required autofocus value="{{ old('run') }}" inputmode="text" autocapitalize="off" spellcheck="false"></div>
  <div class="form-group"><label for="password">Contraseña</label><input class="form-control" id="password" name="password" type="password" maxlength="200" required autocomplete="current-password"></div>
  <button class="btn btn-primary btn-block">Ingresar</button>
  <p class="text-center small mt-3 mb-0"><a href="{{ route('ingreso') }}">Volver</a></p>
</form>
@endsection
