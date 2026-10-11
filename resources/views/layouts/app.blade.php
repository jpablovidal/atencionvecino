<!doctype html>
<html lang="es-CL">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>@yield('titulo', 'Inicio') · SGD Santo Domingo</title>
<link rel="stylesheet" href="{{ asset('assets/css/sgd-fonts.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/gob.cl.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/sgd.css') }}">
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="sgd-header">
  <div class="container d-flex flex-wrap justify-content-between align-items-center py-3">
    <a href="{{ route('inicio') }}" class="titulo text-decoration-none">I. Municipalidad de Santo Domingo<br><small>Sistema de Gestión Documental</small></a>
    <div class="text-right">
      @if(session('ciudadano'))
        <span>{{ session('ciudadano.nombres') }} {{ session('ciudadano.apellidos') }} · {{ session('ciudadano.run') }}</span>
      @elseif(auth()->check())
        <span>{{ auth()->user()->nombreCompleto() }} · {{ auth()->user()->rol }}</span>
      @endif
      @if(session('ciudadano') || auth()->check())
        <form method="post" action="{{ route('salir') }}" class="d-inline ml-2">@csrf<button class="btn btn-sm btn-outline-light">Cerrar sesión</button></form>
      @else
        <a class="btn btn-sm btn-light" href="{{ route('ingreso') }}">Ingresar con ClaveÚnica</a>
      @endif
    </div>
  </div>
  <nav class="sgd-nav" aria-label="Navegación principal"><div class="container">
    @auth
      <a href="{{ route('app.bandeja') }}">Bandeja</a>
      @if(auth()->user()->operaExpedientes() || auth()->user()->rol==='auditor')<a href="{{ route('app.vigentes') }}">Documentos vigentes</a>@endif
      @if(auth()->user()->operaExpedientes() || auth()->user()->rol==='auditor')<a href="{{ route('app.comunicaciones') }}">Comunicaciones oficiales</a>@endif
      @if(auth()->user()->esRol('alcalde','admin','auditor'))<a href="{{ route('app.delegaciones') }}">Delegaciones de firma</a>@endif
      @if(auth()->user()->rol==='admin')
        @foreach(['unidades'=>'Unidades','usuarios'=>'Usuarios','tramites'=>'Trámites','tipos'=>'Tipos de documento','plantillas'=>'Plantillas'] as $k=>$v)<a href="{{ route('app.admin.index',$k) }}">{{ $v }}</a>@endforeach
        <a href="{{ route('app.admin.codificador') }}">Codificador</a><a href="{{ route('app.admin.index','pisee') }}">Servicios PISEE</a>
        <a href="{{ route('app.diagnostico') }}">Diagnóstico</a>
      @endif
      @if(auth()->user()->esRol('admin','auditor'))<a href="{{ route('app.seguridad') }}">Seguridad</a>@endif
    @else
      <a href="{{ route('inicio') }}">Inicio</a><a href="{{ route('publico.listado') }}">Documentos públicos</a><a href="{{ route('validar.form') }}">Validar documento</a>
      @if(session('ciudadano'))<a href="{{ route('ciudadano.index') }}">Mis solicitudes</a>@endif
    @endauth
  </div></nav>
</header>
<main id="contenido" class="container py-4" tabindex="-1">
  @foreach(['ok'=>'success','error'=>'danger','aviso'=>'warning'] as $k=>$cls)
    @if(session($k))<div class="alert alert-{{ $cls }}" role="{{ $k==='error'?'alert':'status' }}">{{ session($k) }}</div>@endif
  @endforeach
  @if(isset($errors) && $errors->any())<div class="alert alert-danger" role="alert"><strong>Revise el formulario:</strong><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  @yield('contenido')
</main>
<footer class="sgd-footer"><div class="container">
  <span>I. Municipalidad de Santo Domingo · Ley 21.180 de Transformación Digital del Estado</span><a href="{{ route('publico.listado') }}">Documentos públicos</a><a href="{{ route('validar.form') }}">Validar un documento</a>
</div></footer>
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
<script src="{{ asset('assets/js/sgd.js') }}"></script>
</body>
</html>
