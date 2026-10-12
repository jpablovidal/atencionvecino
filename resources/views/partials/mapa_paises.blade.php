@php
  // $paises = [cc => conexiones], $max
  $mapa = \App\Support\MapaMundo::datos();
  $titulo = 'Mapa del mundo con las conexiones por país';
  $top = collect($paises)->except(['LAN', '??'])->take(5);
  $resumen = $top->isEmpty() ? 'sin conexiones geolocalizadas' : 'países con más conexiones: '.$top->map(fn ($n, $cc) => \App\Services\Sistema\ConexionesService::nombrePais($cc).' '.$n)->implode(', ');
@endphp
<figure class="mapa-wrap">
  <svg viewBox="0 0 1000 500" role="img" aria-labelledby="mapa-t mapa-d" class="mapa-svg">
    <title id="mapa-t">{{ $titulo }}</title>
    <desc id="mapa-d">{{ ucfirst($resumen) }}. Los datos completos están en la tabla siguiente.</desc>
    <rect width="1000" height="500" class="mapa-mar"/>
    @foreach($mapa as $cc => $p)
      @php $h = (int) ($paises[$cc] ?? 0); @endphp
      <path d="{{ $p['d'] }}" class="mp-{{ \App\Services\Sistema\ConexionesService::clase($h, $max) }}"><title>{{ $p['n'] }}: {{ number_format($h, 0, ',', '.') }} {{ $h === 1 ? 'conexión' : 'conexiones' }}</title></path>
    @endforeach
  </svg>
  <figcaption class="small mt-1">
    <span class="leyenda-mapa"><span class="mp-0 cuadro"></span> sin conexiones</span>
    @foreach([1, 2, 3, 4, 5] as $c)<span class="leyenda-mapa"><span class="mp-{{ $c }} cuadro"></span> nivel {{ $c }}</span>@endforeach
    <span class="text-muted">(escala logarítmica; nivel 5 = país con más conexiones)</span>
  </figcaption>
</figure>
