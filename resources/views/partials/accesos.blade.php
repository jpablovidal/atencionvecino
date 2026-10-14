@php $accesos = collect($menuItems)->filter(fn ($i) => ($i['descripcion'] ?? '') !== '' && ! in_array($i['clave'] ?? '', ['inicio', 'bandeja', 'ayuda'], true))->take(8); @endphp
@if($accesos->isNotEmpty())
<h2 class="seccion">Accesos rápidos</h2>
<div class="dash-accesos">
@foreach($accesos as $i)
  <a class="acceso" href="{{ $i['url'] }}"><span class="acceso-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-{{ $i['icono'] }}"/></svg></span>
    <span><strong>{{ $i['etiqueta'] }}</strong><small>{{ $i['descripcion'] }}</small></span></a>
@endforeach
</div>
@endif
