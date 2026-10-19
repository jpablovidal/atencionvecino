@extends('layouts.app')
@section('titulo', 'Trámites y servicios')
@section('contenido')
@php
  $ficha = fn ($t) => route('catalogo.ficha', [$t->id, \Illuminate\Support\Str::slug($t->nombre)]);
  $base = fn (array $cambios) => route('catalogo', array_filter(array_merge(['q' => $f['q'], 'direccion' => $f['direccion'], 'orden' => $f['orden'] !== 'nombre' ? $f['orden'] : null], $cambios), fn ($v) => $v !== null && $v !== ''));
@endphp
@php $dirSel = $f['direccion'] ? $direcciones->firstWhere('id', (int) $f['direccion']) : null; @endphp
<nav aria-label="Usted está en" class="portal-migas"><span class="portal-migas-et">Usted está en:</span><ol><li><a href="{{ route('inicio') }}">Inicio</a></li><li aria-current="page">Trámites y servicios</li></ol></nav>
<h1>{{ $dirSel ? 'Trámites de '.$dirSel->nombre : 'Trámites y servicios municipales' }}</h1>
<p class="lead-sub">Encuentre qué necesita para hacer su trámite y solicítelo en línea con ClaveÚnica.</p>
<div class="portal-catalogo">
  <aside class="portal-filtros" aria-label="Filtros">
    <h2 class="h6">Dirección municipal</h2>
    <ul class="list-unstyled">
      <li><a href="{{ $base(['direccion' => null]) }}" @if(!$f['direccion']) aria-current="true" class="activo" @endif>Todas <span class="n">{{ $total }}</span></a></li>
      @foreach($direcciones as $d)<li><a href="{{ $base(['direccion' => $d->id]) }}" @if((int)$f['direccion']===$d->id) aria-current="true" class="activo" @endif>{{ $d->nombre }} <span class="n">{{ $d->publicados_count }}</span></a></li>@endforeach
    </ul>
    <h2 class="h6 mt-3">Ordenar por</h2>
    <ul class="list-unstyled">
      @foreach(['nombre' => 'Nombre (A-Z)', 'populares' => 'Más solicitados', 'recientes' => 'Más recientes'] as $k => $v)<li><a href="{{ $base(['orden' => $k === 'nombre' ? null : $k]) }}" @if($f['orden']===$k) aria-current="true" class="activo" @endif>{{ $v }}</a></li>@endforeach
    </ul>
  </aside>
  <div class="portal-resultados">
    <p class="small text-muted" role="status">{{ $tramites->total() }} {{ $tramites->total() === 1 ? 'trámite encontrado' : 'trámites encontrados' }}@if($f['q']) para «{{ $f['q'] }}»@endif.@if($f['q'] || $f['direccion']) <a href="{{ route('catalogo') }}">Quitar filtros</a>@endif</p>
    @forelse($tramites as $t)
    <article class="portal-resultado">
      <h2 class="h5"><a href="{{ $ficha($t) }}">{{ $t->nombre }}</a></h2>
      <p class="chips"><span class="chip">{{ $t->propietaria?->nombre }}</span><span class="chip chip-claro">Respuesta en {{ $t->plazo_dias }} días{{ $t->plazo_habiles ? ' hábiles' : '' }}</span>@if($t->solicitudes_count > 0)<span class="chip chip-claro">{{ $t->solicitudes_count }} {{ $t->solicitudes_count === 1 ? 'solicitud' : 'solicitudes' }}</span>@endif</p>
      @if($t->descripcion)<p class="mb-2">{{ \Illuminate\Support\Str::limit($t->descripcion, 200) }}</p>@endif
      <a class="btn btn-sm btn-outline-primary" href="{{ $ficha($t) }}">Ver requisitos<span class="sr-only"> de {{ $t->nombre }}</span></a>
      <a class="btn btn-sm btn-primary" href="{{ route('catalogo.iniciar', $t->id) }}">Realizar trámite<span class="sr-only"> {{ $t->nombre }}</span></a>
    </article>
    @empty
    <div class="vacio"><svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-search"/></svg> No hay trámites que coincidan con su búsqueda. Pruebe con otras palabras o <a href="{{ route('catalogo') }}">vea todos los trámites</a>.</div>
    @endforelse
    {{ $tramites->links() }}
  </div>
</div>
@endsection
