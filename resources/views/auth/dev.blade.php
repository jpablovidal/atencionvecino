@extends('layouts.app')
@section('titulo', 'ClaveÚnica simulada')
@section('contenido')
<div class="alert alert-warning"><strong>SOLO DESARROLLO:</strong> simulación de ClaveÚnica. No existe en producción.</div>
<h1>ClaveÚnica (simulada)</h1>
<form method="post" action="{{ route('dev.login') }}" class="col-md-6 px-0">
@csrf
<input type="hidden" name="via" value="{{ $via }}"><input type="hidden" name="state" value="{{ $state }}">
<div class="form-group"><label for="run">RUN</label><input class="form-control" id="run" name="run" required></div>
<div class="form-group"><label for="nombres">Nombres</label><input class="form-control" id="nombres" name="nombres" required></div>
<div class="form-group"><label for="apellidos">Apellidos</label><input class="form-control" id="apellidos" name="apellidos" required></div>
<button class="btn btn-primary">Continuar</button>
</form>
@endsection
