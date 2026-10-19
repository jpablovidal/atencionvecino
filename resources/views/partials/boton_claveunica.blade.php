{{-- Botón OFICIAL de ClaveÚnica (kit de Gobierno Digital: cu.css + cu-blanco.svg, sin modificar el diseño).
     Parámetros: $href (obligatorio), $oscuro (bool, fondo oscuro → variante de alto contraste), $bloque (bool, ancho flexible),
     $contexto (texto para lectores de pantalla), $texto ("Iniciar sesión" por defecto, o "ClaveÚnica"). --}}
@once<link rel="stylesheet" href="{{ asset('assets/css/claveunica.css') }}">@endonce
@php
  $textoBoton = ($texto ?? 'Iniciar sesión') === 'ClaveÚnica' ? 'ClaveÚnica' : 'Iniciar sesión';
  $accion = $textoBoton === 'ClaveÚnica' ? 'Continuar con ClaveÚnica' : 'Iniciar sesión con ClaveÚnica';
@endphp
<a class="btn-cu btn-m {{ ($oscuro ?? false) ? 'btn-color-highContrast' : 'btn-color-estandar' }}{{ ($bloque ?? false) ? ' btn-fw' : '' }}" href="{{ $href }}" data-claveunica aria-label="{{ $accion }}{{ isset($contexto) ? ' ('.$contexto.')' : '' }}">
  <span class="cl-claveunica" aria-hidden="true"></span>
  <span class="texto" aria-hidden="true">{{ $textoBoton }}</span>
</a>
