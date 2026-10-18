@extends('layouts.app')
@section('titulo', $m ? 'Editar microforma' : 'Registrar microforma')
@section('contenido')
<h1>{{ $m ? 'Editar '.$m->codigo : 'Registrar microforma' }}</h1>
<form method="post" action="{{ $m ? route('app.archivo.actualizar',$m->id) : route('app.archivo.guardar') }}" class="col-lg-9 px-0" data-pasos>@csrf
  @include('app.archivo._campos', ['m' => $m])
  <button class="btn btn-primary">Guardar</button> <a class="btn btn-link" href="{{ $m ? route('app.archivo.ver',$m->id) : route('app.archivo.index') }}">Volver</a>
</form>
@endsection
