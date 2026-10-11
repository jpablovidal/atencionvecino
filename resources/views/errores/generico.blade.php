@extends('layouts.app')
@section('titulo', 'Error '.$codigo)
@section('contenido')
<h1>Error {{ $codigo }}</h1>
<p class="lead">{{ $mensaje }}</p>
<a class="btn btn-primary" href="{{ route('inicio') }}">Volver al inicio</a>
@endsection
