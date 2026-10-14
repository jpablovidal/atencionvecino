@if(!empty($kpis))
<section aria-label="Resumen" class="dash-kpis">
@foreach($kpis as $k)
  @php $tag = !empty($k['url']) ? 'a' : 'div'; @endphp
  <{{ $tag }} class="kpi kpi-{{ $k['nivel'] ?? 'ok' }}" @if(!empty($k['url'])) href="{{ $k['url'] }}" @endif>
    <span class="kpi-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-{{ $k['icono'] }}"/></svg></span>
    <span class="kpi-txt"><span class="kpi-valor">{{ $k['valor'] }}</span><span class="kpi-etiqueta">{{ $k['etiqueta'] }}</span></span>
  </{{ $tag }}>
@endforeach
</section>
@endif
