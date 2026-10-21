{{-- Recorrido guiado por perfil: usa el componente «onboarding» del framework gob.cl (pantalla completa). Sin scripts en línea; recorrido.js lo abre. --}}
@php
  $recPerfil = \App\Support\ManualRenderer::principalPara(auth()->user(), (bool) ($vecino ?? false));
  $recPasos = \App\Support\Recorrido::listos($recPerfil);
  $recAuto = request()->routeIs('app.bandeja', 'ciudadano.index', 'espacio') ? '1' : '0';
@endphp
<section class="onboarding" id="sgd-recorrido" role="dialog" aria-modal="true" aria-labelledby="rec-titulo" data-perfil="{{ $recPerfil }}" data-auto="{{ $recAuto }}" data-total="{{ count($recPasos) }}">
  <div class="onboarding-body"><div class="container py-5" style="max-width:46rem">
    <p class="small text-uppercase font-weight-bold mb-1" aria-hidden="true">Recorrido guiado · {{ \App\Support\ManualRenderer::PERFILES[$recPerfil][0] }}</p>
    <p class="small text-muted mb-1" id="rec-contador" aria-live="polite">Paso 1 de {{ count($recPasos) }}</p>
    <progress class="d-block w-100 mb-4" id="rec-barra" max="{{ count($recPasos) }}" value="1" aria-label="Avance del recorrido"></progress>
    <p class="sr-only" id="rec-titulo">Recorrido guiado: {{ \App\Support\ManualRenderer::PERFILES[$recPerfil][0] }}</p>
    @foreach($recPasos as $i => $p)
    <div class="rec-paso" data-rec-paso="{{ $i }}" @if($i > 0) hidden @endif>
      <h2 class="h3" tabindex="-1">{{ $p['titulo'] }}</h2>
      <p class="lead">{{ $p['texto'] }}</p>
      <p class="mb-0">@if($p['ir'])<a class="btn btn-outline-primary mr-2 mb-2" href="{{ $p['ir'][0] }}">{{ $p['ir'][1] }}</a>@endif<a class="btn btn-link mb-2" href="{{ $p['manual'] }}">Leer esto en el manual de usuario</a></p>
    </div>
    @endforeach
    <div class="mt-4 d-flex flex-wrap align-items-center">
      <button type="button" class="btn btn-outline-secondary mr-2" id="rec-ant">Anterior</button>
      <button type="button" class="btn btn-primary mr-2" id="rec-sig">Siguiente</button>
      <a class="btn btn-link" href="{{ route('ayuda.manual', $recPerfil) }}" id="rec-manual">Abrir el manual completo</a>
    </div>
  </div></div>
  <div class="onboarding-footer border-top"><div class="container d-flex align-items-center justify-content-between">
    <span class="small">No es obligatorio: puede repetirlo desde su menú, en «Recorrido guiado».</span>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="rec-cerrar">Cerrar el recorrido</button>
  </div></div>
</section>
@push('scripts')<script src="{{ asset('assets/js/recorrido.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/recorrido.js')), 0, 8) }}" defer></script>@endpush
