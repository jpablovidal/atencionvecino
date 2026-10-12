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
<link rel="stylesheet" href="{{ route('marca.css', ['v' => $marca['version']]) }}">
<script src="{{ asset('assets/js/a11y-init.js') }}"></script>
</head>
<body>
@if(\App\Support\Ambiente::esPruebas())<div class="banner-pruebas" role="note"><strong>AMBIENTE DE PRUEBAS</strong> — firmas y comunicaciones simuladas, sin validez legal.@if(\App\Support\Ambiente::pruebasHasta()) Acceso de pruebas hasta el {{ \App\Support\Ambiente::pruebasHasta()->format('d-m-Y') }}.@endif</div>@endif
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<a class="skip-link skip-link-2" href="#navegacion">Saltar a la navegación</a>
<div id="a11y-anuncio" class="sr-anuncio" role="status" aria-live="polite" aria-atomic="true"></div>
<div class="a11y-bar" role="region" aria-label="Opciones de accesibilidad"><div class="container">
  <span class="grupo" role="group" aria-label="Tamaño del texto">Texto:
    <button type="button" data-a11y-size="down">Reducir texto</button>
    <button type="button" data-a11y-size="reset">Texto normal</button>
    <button type="button" data-a11y-size="up">Aumentar texto</button>
    <span id="a11y-tamano">100%</span></span>
  <span class="grupo" role="group" aria-label="Visualización">
    <button type="button" data-a11y-toggle="contrast" data-nombre="Alto contraste" aria-pressed="false">Alto contraste</button>
    <button type="button" data-a11y-toggle="legible" data-nombre="Fuente y espaciado legibles" aria-pressed="false">Texto legible</button>
    <button type="button" data-a11y-toggle="links" data-nombre="Enlaces subrayados" aria-pressed="false">Subrayar enlaces</button>
    <button type="button" data-a11y-toggle="motion" data-nombre="Sin animaciones" aria-pressed="false">Sin animaciones</button>
    <button type="button" id="a11y-reset">Restablecer</button></span>
  <span class="grupo"><a href="{{ route('accesibilidad') }}">Declaración de accesibilidad</a><a href="{{ route('ayuda') }}">Ayuda y manuales</a></span>
</div></div>
<header class="sgd-header">
  <div class="container d-flex flex-wrap justify-content-between align-items-center py-3">
    <a href="{{ route('inicio') }}" class="titulo text-decoration-none d-inline-flex align-items-center">
      @if($marca['header']['mostrar_logo'] && $marca['logo'])<img class="sgd-logo" src="{{ route('marca.logo', ['v' => substr($marca['logo']['sha256'], 0, 10)]) }}" alt="{{ $marca['header']['logo_alt'] }}" width="{{ (int) round($marca['header']['logo_alto_px'] * $marca['logo']['ancho'] / max(1, $marca['logo']['alto'])) }}" height="{{ $marca['header']['logo_alto_px'] }}">@endif
      <span>{{ $marca['header']['titulo'] }}@if($marca['header']['subtitulo'] !== '')<br><small>{{ $marca['header']['subtitulo'] }}</small>@endif</span>
    </a>
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
  <nav id="navegacion" class="sgd-nav" aria-label="Navegación principal" tabindex="-1"><div class="container">
    @php $abiertos = []; @endphp
    @foreach($menuItems as $it)
      @if($it['grupo'])
        @unless(isset($abiertos[$it['grupo']]))
          @php $abiertos[$it['grupo']] = true; @endphp
          <details class="sgd-nav-grupo"><summary>{{ $it['grupo'] }}</summary>
            <ul class="list-unstyled">@foreach($menuItems as $g)@if($g['grupo'] === $it['grupo'])<li><a href="{{ $g['url'] }}" @if(request()->is($g['patron'])) aria-current="page" @endif>{{ $g['etiqueta'] }}</a></li>@endif @endforeach</ul>
          </details>
        @endunless
      @else
        <a href="{{ $it['url'] }}" @if(request()->is($it['patron'])) aria-current="page" @endif @if($it['nueva']) target="_blank" rel="noopener noreferrer" @endif>{{ $it['etiqueta'] }}@if($it['nueva'])<span class="sr-only"> (se abre en una pestaña nueva)</span>@endif</a>
      @endif
    @endforeach
  </div></nav>
</header>
<main id="contenido" class="container py-4" tabindex="-1">
  @foreach(['ok'=>'success','error'=>'danger','aviso'=>'warning'] as $k=>$cls)
    @if(session($k))<div class="alert alert-{{ $cls }}" role="{{ $k==='error'?'alert':'status' }}">{{ session($k) }}</div>@endif
  @endforeach
  @if(isset($errors) && $errors->any())<div class="alert alert-danger" role="alert" tabindex="-1" id="resumen-errores" data-campos="{{ implode(',', $errors->keys()) }}"><strong>Revise el formulario ({{ $errors->count() }} {{ $errors->count() === 1 ? 'error' : 'errores' }}):</strong><ul class="mb-0">@foreach($errors->keys() as $k)@foreach($errors->get($k) as $m)<li><a href="#{{ $k }}">{{ $m }}</a></li>@endforeach @endforeach</ul></div>@endif
  @yield('contenido')
</main>
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
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
<script src="{{ asset('assets/js/sgd.js') }}"></script>
<script src="{{ asset('assets/js/a11y.js') }}"></script>
</body>
</html>
