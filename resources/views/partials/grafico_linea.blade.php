@php
  // $titulo, $puntos = [[etiqueta, valor|null], ...], $max (eje Y), $unidad
  $w = 640; $h = 150; $ml = 38; $mb = 18; $mt = 8;
  $vals = array_values(array_filter(array_column($puntos, 1), fn ($v) => $v !== null));
  $n = count($puntos);
  $coords = [];
  foreach ($puntos as $i => [$et, $v]) {
      if ($v === null) { continue; }
      $x = $ml + ($n > 1 ? ($w - $ml - 6) * $i / ($n - 1) : 0);
      $y = $mt + ($h - $mt - $mb) * (1 - min(max($v, 0), $max) / $max);
      $coords[] = round($x, 1).','.round($y, 1);
  }
  $resumen = $vals ? 'mínimo '.round(min($vals), 1).$unidad.', máximo '.round(max($vals), 1).$unidad.', último '.round(end($vals), 1).$unidad : 'sin datos';
@endphp
<figure class="grafico mb-4">
  <figcaption class="h6">{{ $titulo }}</figcaption>
  @if(count($coords) > 1)
  <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="{{ $titulo }}: {{ $resumen }}" class="grafico-svg" preserveAspectRatio="none">
    @foreach([0, .5, 1] as $g)@php $yy = $mt + ($h - $mt - $mb) * (1 - $g); @endphp
      <line x1="{{ $ml }}" x2="{{ $w - 6 }}" y1="{{ $yy }}" y2="{{ $yy }}" class="grafico-eje"/><text x="2" y="{{ $yy + 4 }}" class="grafico-txt">{{ round($max * $g, $max < 10 ? 1 : 0) }}{{ $unidad }}</text>
    @endforeach
    <polyline points="{{ implode(' ', $coords) }}" class="grafico-linea" fill="none"/>
    <text x="{{ $ml }}" y="{{ $h - 3 }}" class="grafico-txt">{{ $puntos[0][0] }}</text><text x="{{ $w - 6 }}" y="{{ $h - 3 }}" text-anchor="end" class="grafico-txt">{{ $puntos[$n - 1][0] }}</text>
  </svg>
  <details class="small"><summary>Ver datos de «{{ $titulo }}» en tabla</summary>
    <table class="table table-sm"><caption class="sr-only">{{ $titulo }}</caption><thead><tr><th scope="col">Fecha y hora</th><th scope="col">Valor</th></tr></thead><tbody>
    @foreach(array_slice(array_reverse($puntos, true), 0, 24, true) as [$et, $v])<tr><td>{{ $et }}</td><td>{{ $v === null ? '—' : round($v, 1).$unidad }}</td></tr>@endforeach</tbody></table>
  </details>
  @else
  <p class="text-muted small">Aún no hay suficientes muestras. El histórico se alimenta cada 5 minutos con la tarea programada (<code>recursos:muestrear</code>).</p>
  @endif
</figure>
