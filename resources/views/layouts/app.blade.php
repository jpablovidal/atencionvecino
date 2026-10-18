<!doctype html>
<html lang="es-CL">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>@yield('titulo', 'Inicio') · SGD Santo Domingo</title>
<link rel="stylesheet" href="{{ asset('assets/css/sgd-fonts.css') }}?v={{ substr((string) @md5_file(public_path('assets/css/sgd-fonts.css')), 0, 8) }}">
<link rel="stylesheet" href="{{ asset('assets/css/gob.cl.css') }}?v={{ substr((string) @md5_file(public_path('assets/css/gob.cl.css')), 0, 8) }}">
<link rel="stylesheet" href="{{ asset('assets/css/sgd.css') }}?v={{ substr((string) @md5_file(public_path('assets/css/sgd.css')), 0, 8) }}">
<link rel="stylesheet" href="{{ route('marca.css', ['v' => $marca['version']]) }}">
<script src="{{ asset('assets/js/a11y-init.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/a11y-init.js')), 0, 8) }}"></script>
</head>
<body>
@if(\App\Support\Ambiente::esPruebas())<div class="banner-pruebas" role="note"><strong>AMBIENTE DE PRUEBAS</strong> — firmas y comunicaciones simuladas, sin validez legal.@if(\App\Support\Ambiente::pruebasHasta()) Acceso de pruebas hasta el {{ \App\Support\Ambiente::pruebasHasta()->format('d-m-Y') }}.@endif</div>@endif
@include('partials.iconos')
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<a class="skip-link skip-link-2" href="#navegacion">Saltar a la navegación</a>
<div id="a11y-anuncio" class="sr-anuncio" role="status" aria-live="polite" aria-atomic="true"></div>
@php
  $lateral = (bool) (session('ciudadano') || auth()->check()) && ! $__env->hasSection('sinlateral');
  $vecino = ($espacio ?? null) === 'ciudadano' || (! auth()->check() && session('ciudadano'));
  $esLogin = $__env->hasSection('login');
  $logoSrc = ($marca['header']['mostrar_logo'] && $marca['logo']) ? route('marca.logo', ['v' => substr($marca['logo']['sha256'], 0, 10)]) : null;
@endphp
@if($esLogin)
<div class="sgd-login-wrap">
  <header>
  <p class="sgd-login-titulo">{{ $marca['header']['subtitulo'] !== '' ? $marca['header']['subtitulo'] : $marca['header']['titulo'] }}</p>
  @include('partials.a11y')
  </header>
  <nav id="navegacion" class="sgd-login-nav" aria-label="Navegación principal" tabindex="-1"><a href="{{ route('inicio') }}">Inicio</a><a href="{{ route('catalogo') }}">Trámites y servicios</a><a href="{{ route('ayuda') }}">Ayuda</a></nav>
@else
<header class="sgd-header">
  <div class="sgd-topbar">
    @if($lateral)<button type="button" class="sgd-burger" id="sgd-burger" aria-controls="navegacion" aria-expanded="true" title="Mostrar u ocultar el menú"><span aria-hidden="true">☰</span><span class="sr-only">Mostrar u ocultar el menú</span></button>@endif
    <a href="{{ route('inicio') }}" class="titulo text-decoration-none d-inline-flex align-items-center">
      @if($logoSrc)<img class="sgd-logo" src="{{ $logoSrc }}" alt="{{ $marca['header']['logo_alt'] }}" width="{{ (int) round($marca['header']['logo_alto_px'] * $marca['logo']['ancho'] / max(1, $marca['logo']['alto'])) }}" height="{{ $marca['header']['logo_alto_px'] }}">
      @elseif($marca['header']['mostrar_logo'])<img class="sgd-logo-defecto" src="{{ asset('assets/img/logo-santo-domingo.png') }}" alt="{{ $marca['header']['logo_alt'] }}" width="28" height="44">@endif
      <span class="sgd-marca">{{ $marca['header']['titulo'] }}@if($marca['header']['subtitulo'] !== '')<small>{{ $marca['header']['subtitulo'] }}</small>@endif</span>
    </a>
    @include('partials.a11y')
    <div class="sgd-topbar-der">
      @if($vecino)
        <details class="sgd-user"><summary><span class="sgd-user-nombre">{{ session('ciudadano.nombres') }} {{ session('ciudadano.apellidos') }}</span></summary>
          <div class="sgd-user-panel"><p class="mb-1">RUN {{ session('ciudadano.run') }}</p><p class="small mb-2">Ciudadano(a)@if($dual ?? false) · espacio vecino(a)@endif</p>
            @if($dual ?? false)<p class="mb-2"><a class="btn btn-sm btn-primary btn-block" href="{{ route('app.bandeja') }}">Ir a mi trabajo (funcionario)</a></p>@endif
            <form method="post" action="{{ route('salir') }}">@csrf<button class="btn btn-sm btn-outline-secondary btn-block">Cerrar sesión</button></form></div></details>
      @elseif(auth()->check())
        <details class="sgd-user"><summary><span class="sgd-user-nombre">{{ auth()->user()->nombreCompleto() }}</span></summary>
          <div class="sgd-user-panel"><p class="mb-1">{{ auth()->user()->rol }}@if(auth()->user()->cargo) · {{ auth()->user()->cargo }}@endif</p>
            @if($dual ?? false)<p class="mb-1"><a class="btn btn-sm btn-primary btn-block" href="{{ route('ciudadano.index') }}">Ir a mis trámites (vecino/a)</a></p>@endif
            <form method="post" action="{{ route('salir') }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-secondary btn-block">Cerrar sesión</button></form></div></details>
      @else
        <a class="btn btn-sm btn-light" href="{{ route('ingreso') }}">Ingresar con ClaveÚnica</a>
      @endif
    </div>
  </div>
</header>
<div class="sgd-layout{{ $lateral ? ' con-lateral' : '' }}">
  <nav id="navegacion" class="sgd-nav{{ $lateral ? ' sgd-sidebar' : '' }}" aria-label="Navegación principal" tabindex="-1"><div class="sgd-nav-inner">
    @if($lateral)<p class="sgd-nav-titulo">{{ $vecino ? 'Mis trámites' : 'Mi trabajo' }}</p>@endif
    @php $abiertos = []; @endphp
    @foreach($menuItems as $it)
      @if($it['grupo'])
        @unless(isset($abiertos[$it['grupo']]))
          @php $abiertos[$it['grupo']] = true; @endphp
          <details class="sgd-nav-grupo"><summary>{{ $it['grupo'] }}</summary>
            <ul class="list-unstyled">@foreach($menuItems as $g)@if($g['grupo'] === $it['grupo'])<li><a href="{{ $g['url'] }}" @if(request()->is($g['patron'])) aria-current="page" @endif>@if($lateral)<svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-{{ $g['icono'] }}"/></svg>@endif<span>{{ $g['etiqueta'] }}</span></a></li>@endif @endforeach</ul>
          </details>
        @endunless
      @else
        <a href="{{ $it['url'] }}" @if(request()->is($it['patron'])) aria-current="page" @endif @if($it['nueva']) target="_blank" rel="noopener noreferrer" @endif>@if($lateral)<svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-{{ $it['icono'] }}"/></svg>@endif<span>{{ $it['etiqueta'] }}</span>@if($it['nueva'])<span class="sr-only"> (se abre en una pestaña nueva)</span>@endif</a>
      @endif
    @endforeach
  </div></nav>
<div class="sgd-backdrop" id="sgd-backdrop" hidden></div>
<div class="sgd-main">
@endif
<main id="contenido" class="{{ $esLogin ? 'sgd-login-card' : 'sgd-content' }}" tabindex="-1">
  @foreach(['ok'=>'success','error'=>'danger','aviso'=>'warning'] as $k=>$cls)
    @if(session($k))<div class="alert alert-{{ $cls }}" role="{{ $k==='error'?'alert':'status' }}">{{ session($k) }}</div>@endif
  @endforeach
  @if(isset($errors) && $errors->any())<div class="alert alert-danger" role="alert" tabindex="-1" id="resumen-errores" data-campos="{{ implode(',', $errors->keys()) }}"><strong>Revise el formulario ({{ $errors->count() }} {{ $errors->count() === 1 ? 'error' : 'errores' }}):</strong><ul class="mb-0">@foreach($errors->keys() as $k)@foreach($errors->get($k) as $m)<li><a href="#{{ $k }}">{{ $m }}</a></li>@endforeach @endforeach</ul></div>@endif
  @yield('contenido')
</main>
@if($esLogin)</div>@endif
<footer class="sgd-footer"><div class="container">
  @if($marca['footer']['texto'] !== '')<p class="mb-2">{{ $marca['footer']['texto'] }}</p>@endif
  <ul class="list-inline mb-0">
    @forelse($marca['footer']['enlaces'] as $e)
      <li class="list-inline-item"><a href="{{ str_starts_with($e['url'], '/') ? url($e['url']) : $e['url'] }}" @unless(str_starts_with($e['url'], '/')) rel="noopener noreferrer" @endunless>{{ $e['etiqueta'] }}</a></li>
    @empty
      <li class="list-inline-item"><a href="{{ route('accesibilidad') }}">Accesibilidad</a></li>
      <li class="list-inline-item"><a href="{{ route('ayuda') }}">Ayuda y manuales</a></li>
      <li class="list-inline-item"><a href="{{ route('publico.listado') }}">Documentos públicos</a></li>
      <li class="list-inline-item"><a href="{{ route('validar.form') }}">Validar un documento</a></li>
    @endforelse
  </ul>
  @if($marca['footer']['texto_legal'] !== '')<p class="small mt-2 mb-0">{{ $marca['footer']['texto_legal'] }}</p>@endif
</div></footer>
@unless($esLogin)</div></div>@endunless
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
<script src="{{ asset('assets/js/sgd.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/sgd.js')), 0, 8) }}"></script>
<script src="{{ asset('assets/js/a11y.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/a11y.js')), 0, 8) }}"></script>
</body>
</html>
