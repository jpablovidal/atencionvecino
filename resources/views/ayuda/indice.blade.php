@extends('layouts.app')
@section('titulo', 'Ayuda y manuales')
@section('contenido')
<h1>Ayuda y manuales de usuario</h1>
<p class="lead">Elija el manual que corresponde a su perfil. Todos pueden imprimirse o guardarse como PDF desde el navegador y funcionan con teclado y lector de pantalla.</p>
@if($mio)<p><a class="btn btn-primary" href="{{ route('ayuda.manual',$mio) }}">Ir a mi manual: {{ $perfiles[$mio][0] }}</a></p>@endif
<ul class="list-unstyled row">
@foreach($perfiles as $slug => [$nombre, $desc])
  <li class="col-md-6 mb-3"><div class="card h-100"><div class="card-body">
    <h2 class="h5"><a href="{{ route('ayuda.manual',$slug) }}">{{ $nombre }}</a></h2><p class="mb-0">{{ $desc }}</p>
  </div></div></li>
@endforeach
</ul>
<h2 class="h4">Otras ayudas</h2>
<ul><li><a href="{{ route('accesibilidad') }}">Declaración de accesibilidad y atajos de teclado</a></li><li><a href="{{ route('validar.form') }}">Validar un documento con su código</a></li></ul>
@endsection
