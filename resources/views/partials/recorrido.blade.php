{{-- Recorrido guiado por perfil: se muestra sobre la pantalla real (resalta cada elemento con una tarjeta de instrucciones), sin ventana nueva. recorrido.js lo dibuja. --}}
@php
  $recPerfil = \App\Support\ManualRenderer::principalPara(auth()->user(), (bool) ($vecino ?? false));
  $recPasos = \App\Support\Recorrido::listos($recPerfil, auth()->user());
  $recAuto = request()->routeIs('app.bandeja', 'ciudadano.index', 'espacio') ? '1' : '0';
@endphp
<div id="sgd-recorrido" hidden data-perfil="{{ $recPerfil }}" data-auto="{{ $recAuto }}" data-total="{{ count($recPasos) }}" data-nombre="{{ \App\Support\ManualRenderer::PERFILES[$recPerfil][0] }}" data-pasos="{{ json_encode($recPasos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
@push('scripts')<script src="{{ asset('assets/js/recorrido.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/recorrido.js')), 0, 8) }}" defer></script>@endpush
