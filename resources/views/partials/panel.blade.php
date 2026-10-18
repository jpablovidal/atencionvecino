@if(!empty($panel['bloques']) || !empty($panel['kpis']))
<section aria-labelledby="panel-t" class="mb-4">
<h2 id="panel-t" class="seccion">{{ $panel['titulo'] }}</h2>
@if(!empty($panel['kpis']))@include('partials.kpis', ['kpis' => $panel['kpis']])@endif
<div class="panel-bloques">
@foreach($panel['bloques'] as $i => $b)
<div class="panel-bloque">
  <h3 class="h6" id="pb-{{ $i }}">{{ $b['titulo'] }}</h3>
  @if($b['tipo'] === 'barras')
    @if(count($b['filas']) === 0)<p class="small text-muted mb-0">Sin datos por ahora.</p>@else
    <table class="panel-barras" aria-labelledby="pb-{{ $i }}"><tbody>
    @foreach($b['filas'] as $f)
      <tr><th scope="row">@if(!empty($f[2]))<a href="{{ $f[2] }}">{{ $f[0] }}</a>@else{{ $f[0] }}@endif</th><td><progress max="{{ $b['max'] }}" value="{{ $f[1] }}" aria-label="{{ $f[0] }}: {{ $f[1] }}">{{ $f[1] }}</progress></td><td class="panel-valor">{{ $f[1] }}</td></tr>
    @endforeach
    </tbody></table>@endif
    @if(!empty($b['nota']))<p class="small text-muted mt-2 mb-0">{{ $b['nota'] }}</p>@endif
  @else
    <div class="table-responsive"><table class="table table-sm mb-0"><caption class="sr-only">{{ $b['titulo'] }}</caption><thead><tr>@foreach($b['cabeceras'] as $h)<th scope="col">{{ $h }}</th>@endforeach</tr></thead><tbody>
    @forelse($b['filas'] as $fila)<tr>@foreach($fila as $c)<td>@if(is_array($c))<a href="{{ $c[1] }}">{{ $c[0] }}</a>@else{{ $c }}@endif</td>@endforeach</tr>
    @empty<tr><td colspan="{{ count($b['cabeceras']) }}">{{ $b['vacio'] }}</td></tr>@endforelse</tbody></table></div>
  @endif
</div>
@endforeach
</div>
</section>
@endif
