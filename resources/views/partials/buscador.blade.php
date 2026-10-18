{{-- Buscador del portal (combobox accesible con sugerencias; sin JavaScript funciona como búsqueda normal) --}}
@php $id = $id ?? 'q-portal'; $valor = $valor ?? request('q'); @endphp
<form method="get" action="{{ route('catalogo') }}" role="search" class="buscador-portal{{ !empty($grande) ? ' buscador-grande' : '' }}">
  <label class="{{ !empty($grande) ? 'buscador-etiqueta' : 'sr-only' }}" for="{{ $id }}">{{ !empty($grande) ? '¿Qué trámite o servicio necesita?' : 'Buscar un trámite o servicio' }}</label>
  <div class="buscador-campo">
    <input id="{{ $id }}" name="q" type="search" value="{{ $valor }}" maxlength="100" autocomplete="off" placeholder="Busque por nombre del trámite o palabra clave"
      role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-lista" data-buscador data-url="{{ route('catalogo.sugerencias') }}">
    <button class="btn btn-primary buscador-boton"><svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-search"/></svg><span>Buscar</span></button>
    <ul id="{{ $id }}-lista" class="buscador-lista" role="listbox" aria-label="Trámites sugeridos" hidden></ul>
  </div>
  <p class="sr-only" role="status" id="{{ $id }}-estado"></p>
</form>
