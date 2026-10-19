{{-- Botón ClaveÚnica. Usa el botón OFICIAL si está en public/assets/img/claveunica/ (ver LEEME.txt); si no, un botón de texto provisional.
     Parámetros: $href (obligatorio), $oscuro (bool, fondo oscuro), $bloque (bool), $contexto (texto para lectores de pantalla). --}}
@php
  $oficial = is_file(public_path('assets/img/claveunica/boton-claveunica.svg'));
  $oficialBlanco = is_file(public_path('assets/img/claveunica/boton-claveunica-blanco.svg'));
  $archivo = ($oscuro ?? false) && $oficialBlanco ? 'boton-claveunica-blanco.svg' : 'boton-claveunica.svg';
@endphp
<a class="btn-claveunica{{ ($bloque ?? false) ? ' btn-claveunica-bloque' : '' }}{{ $oficial ? ' btn-claveunica-oficial' : '' }}" href="{{ $href }}" data-claveunica>
  @if($oficial)<img src="{{ asset('assets/img/claveunica/'.$archivo) }}" alt="Ingresa con ClaveÚnica" height="44">@else<svg class="btn-claveunica-ico" viewBox="0 0 32 32" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.1553 2.34071L21.1563 2.33883C24.2153 2.51697 31 7.77949 31 16.0197C31 24.2796 24.2706 31 16 31C12.0053 31 8.23656 29.4212 5.3875 26.5541C2.55813 23.7067 1 19.9659 1 16.0197C1 12.4242 2.34812 8.90643 4.79594 6.11344C6.69719 3.94487 9.06813 2.45322 10.8091 2.30321V6.6291C9.23781 7.30883 5.23187 10.7178 5.23187 16.0206C5.23187 21.8504 10.1631 26.7754 16 26.7754C21.9372 26.7754 26.7681 21.9507 26.7681 16.0206C26.7681 10.5387 22.6141 7.32008 21.1553 6.66379V2.34071ZM18.1244 10.7403C20.3144 11.5775 21.8688 13.6617 21.8688 16.1078C21.8688 19.2908 19.2409 21.871 15.9991 21.871C12.7572 21.871 10.1294 19.2908 10.1294 16.1078C10.1294 13.6805 11.6603 11.6066 13.825 10.7581V1H18.1244V10.7403ZM15.9991 18.9889C17.62 18.9889 18.9334 17.6989 18.9334 16.1069C18.9334 14.5149 17.62 13.2248 15.9991 13.2248C14.3791 13.2248 13.0647 14.5149 13.0647 16.1069C13.0647 17.6989 14.3781 18.9889 15.9991 18.9889Z"/></svg><span>Ingresa con ClaveÚnica</span>@endif
  @isset($contexto)<span class="sr-only"> ({{ $contexto }})</span>@endisset
</a>
