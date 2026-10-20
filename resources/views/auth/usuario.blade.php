@extends('layouts.app')
@section('titulo', 'Ingreso con usuario y contraseña')
@section('login', '1')
@section('contenido')
<img class="sgd-login-logo" src="{{ asset('assets/img/logo-santo-domingo.png') }}" alt="Logo de la Municipalidad de Santo Domingo" width="57" height="92">
<h1>Ingreso con usuario y contraseña</h1>
<p class="small text-muted text-center">Para funcionarios(as) municipales: use el usuario y la contraseña del directorio institucional. Si prefiere, ingrese con ClaveÚnica.</p>
<form method="post" action="{{ route('login.usuario.entrar') }}" autocomplete="off">@csrf
  <div class="form-group"><label for="username">Usuario</label><input class="form-control" id="username" name="username" maxlength="30" required autofocus value="{{ old('username') }}" autocapitalize="off" spellcheck="false" autocomplete="username"></div>
  <div class="form-group"><label for="password">Contraseña</label><input class="form-control" id="password" name="password" type="password" maxlength="200" required autocomplete="current-password"></div>
  <button class="btn btn-primary btn-block">Ingresar</button>
  <p class="text-center small mt-3 mb-0"><a href="{{ route('ingreso') }}">Volver</a></p>
</form>
@endsection
