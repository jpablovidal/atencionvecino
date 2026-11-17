{{-- Pie de página con la estructura del Framework kit de Gobierno (footer-1): franja decorativa y columnas de secciones, enlaces internos y atención. --}}
@php
  $mb = (array) config('sgd.membrete');
  $enlacesCfg = collect($marca['footer']['enlaces'] ?? []);
  $logoPie = ($marca['header']['mostrar_logo'] && $marca['logo']) ? route('marca.logo', ['v' => substr($marca['logo']['sha256'], 0, 10)]) : asset('assets/img/logo-santo-domingo.png');
@endphp
@if($esLogin)
<footer class="sgd-footer"><div class="container"><p class="mb-0 small">{{ $marca['footer']['texto'] !== '' ? $marca['footer']['texto'] : ($mb['institucion'] ?? '') }}</p></div></footer>
@else
<footer class="sgd-footer content-always-on-display"><div class="container">
  <div class="line"></div>
  <div class="row">
    <div class="col-md-3 a11y-fonts-col-12 mb-3">
      <img class="mw-100 mb-3 sgd-pie-logo" src="{{ $logoPie }}" alt="{{ $marca['header']['logo_alt'] ?: 'Logo de la Municipalidad de Santo Domingo' }}" height="64">
      <p>{{ $marca['footer']['texto'] !== '' ? $marca['footer']['texto'] : 'Sistema de Gestión Documental y trámites en línea de la I. Municipalidad de Santo Domingo.' }}</p>
    </div>
    <div class="col-md-3 a11y-fonts-col-12 mb-3">
      <div class="text-uppercase mb-3">Secciones</div>
      <a href="{{ route('catalogo') }}">Trámites y servicios</a>
      <a href="{{ route('login') }}">Mis solicitudes</a>
      <a href="{{ route('publico.listado') }}">Documentos públicos</a>
      <a href="{{ route('validar.form') }}">Validar un documento</a>
      <a href="{{ \App\Support\ManualRenderer::ayudaUrl() }}">{{ \App\Support\ManualRenderer::ayudaEtiqueta() }}</a>
    </div>
    <div class="col-md-3 a11y-fonts-col-12 mb-3">
      <div class="text-uppercase mb-3">Enlaces internos</div>
      <a href="{{ route('accesibilidad') }}">Accesibilidad</a>
      <a href="{{ route('privacidad') }}">Privacidad y datos personales</a>
      @foreach($enlacesCfg as $e)
        <a href="{{ str_starts_with($e['url'], '/') ? url($e['url']) : $e['url'] }}" @unless(str_starts_with($e['url'], '/')) rel="noopener noreferrer" @endunless>{{ $e['etiqueta'] }}</a>
      @endforeach
    </div>
    <div class="col-md-3 a11y-fonts-col-12 mb-3">
      <div class="text-uppercase mb-3">Atención</div>
      <b class="d-block">{{ $mb['pie'] ?? 'Municipalidad de Santo Domingo' }}</b>
      <p class="mb-1">{{ $mb['direccion'] ?? '' }}</p>
      @if(! empty($mb['web']))<span class="d-block">{{ $mb['web'] }}</span>@endif
    </div>
  </div>
  @if($marca['footer']['texto_legal'] !== '')<p class="small mt-2 mb-0">{{ $marca['footer']['texto_legal'] }}</p>@endif
</div></footer>
@endif
