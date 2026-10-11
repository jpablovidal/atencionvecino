@extends('layouts.app')
@section('titulo', 'Validar documento')
@section('contenido')
<h1>Validar un documento</h1>
<form method="post" action="{{ route('validar.buscar') }}" class="col-md-6 px-0">@csrf
<div class="form-group"><label for="codigo">Código de validación (formato SDD-XXXX-XXXX-XXXX)</label><input class="form-control" id="codigo" name="codigo" required maxlength="30" autocomplete="off"></div>
<button class="btn btn-primary">Validar</button></form>
@endsection
