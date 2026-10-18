@php
  $bd = in_array($e->borde_estilo, ['solid','dashed','dotted'], true) ? $e->borde_estilo : 'solid';
  $col = fn ($c, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c) ? $c : $d;
@endphp
<div style="width:{{ (int) $e->ancho_mm }}mm;min-height:{{ (int) $e->alto_mm }}mm;padding:2mm 3mm;box-sizing:border-box;{{ $e->borde_estilo === 'none' ? 'border:0;' : 'border:0.4mm '.$bd.' '.$col($e->color_borde,'#006fb3').';' }}background:{{ $col($e->color_fondo,'#ffffff') }};color:{{ $col($e->color_texto,'#111111') }};font-size:{{ (int) $e->tamano_fuente }}pt;line-height:1.35;text-align:{{ $e->alineacion_texto === 'center' ? 'center' : 'left' }}">
  <div style="font-weight:bold">{{ $e->texto_encabezado }}</div>
  @if($e->mostrar_nombre)<div>{{ $f['nombre'] }}</div>@endif
  @if($e->mostrar_cargo && ! empty($f['cargo']))<div>{{ $f['cargo'] }}</div>@endif
  @if($e->mostrar_run)<div>RUN {{ \App\Support\Run::enmascarar((string) $f['run']) }}</div>@endif
  @if($e->mostrar_fecha && ! empty($f['fecha']))<div>{{ $f['fecha'] }} (hora de Chile)</div>@endif
  @if($e->mostrar_codigo && ! empty($f['codigo']))<div>Cód. validación: {{ $f['codigo'] }}</div>@endif
  @if(! empty($f['simple']))<div>Firma electrónica simple</div>@endif
  @if(! empty($f['simulada']))<div style="color:#b00020;font-weight:bold">FIRMA SIMULADA – sin validez legal</div>@endif
</div>
