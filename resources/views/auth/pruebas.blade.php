@extends('layouts.app')
@section('titulo', 'Ingreso de pruebas')
@section('contenido')
<h1>Ingreso de pruebas</h1>
<p>Solo para el ambiente de pruebas. Use el RUN (con guion y dígito verificador) y la contraseña entregados.</p>
<form method="post" action="{{ route('ingreso.pruebas.entrar') }}" class="col-md-6 px-0" autocomplete="off">@csrf
  <div class="form-group"><label for="run">RUN (con guion)</label><input class="form-control" id="run" name="run" maxlength="15" required autofocus value="{{ old('run') }}" inputmode="text" autocapitalize="off" spellcheck="false"></div>
  <div class="form-group"><label for="password">Contraseña</label><input class="form-control" id="password" name="password" type="password" maxlength="200" required autocomplete="current-password"></div>
  <button class="btn btn-primary">Ingresar</button> <a class="btn btn-link" href="{{ route('ingreso') }}">Volver</a>
</form>
@endsection
