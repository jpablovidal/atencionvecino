{{-- Botón ClaveÚnica. Usa el botón OFICIAL si está en public/assets/img/claveunica/ (ver LEEME.txt); si no, un botón de texto provisional.
     Parámetros: $href (obligatorio), $oscuro (bool, fondo oscuro), $bloque (bool), $contexto (texto para lectores de pantalla). --}}
@php
  $oficial = is_file(public_path('assets/img/claveunica/boton-claveunica.svg'));
  $oficialBlanco = is_file(public_path('assets/img/claveunica/boton-claveunica-blanco.svg'));
  $archivo = ($oscuro ?? false) && $oficialBlanco ? 'boton-claveunica-blanco.svg' : 'boton-claveunica.svg';
@endphp
<a class="btn-claveunica{{ ($bloque ?? false) ? ' btn-claveunica-bloque' : '' }}{{ $oficial ? ' btn-claveunica-oficial' : '' }}" href="{{ $href }}" data-claveunica>
  @if($oficial)<img src="{{ asset('assets/img/claveunica/'.$archivo) }}" alt="Ingresa con ClaveÚnica" height="44">@else<span>Ingresa con ClaveÚnica</span>@endif
  @isset($contexto)<span class="sr-only"> ({{ $contexto }})</span>@endisset
</a>
