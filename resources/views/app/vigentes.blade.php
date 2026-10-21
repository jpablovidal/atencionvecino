@extends('layouts.app')
@section('titulo', 'Documentos vigentes')
@section('contenido')
@php
  $q = fn (array $x) => route('app.vigentes', array_filter(array_merge($f, ['page' => null], $x), fn ($v) => $v !== null && $v !== ''));
  $col = [['numero', 'N°'], ['fecha', 'Fecha'], ['tipo', 'Tipo'], ['direccion', 'Dirección'], ['materia', 'Materia'], ['tipo_materia', 'Tipo de materia'], ['macrotema', 'Macrotema'], ['expediente', 'Expediente']];
@endphp
<h1>Documentos vigentes</h1>
<p class="lead-sub">Decretos, oficios y demás documentos vigentes. Se muestra por defecto el año {{ now('America/Santiago')->year }}; cambie los filtros para buscar en otros años o por tipo. El contenido de cada documento sigue sujeto a sus permisos de acceso.</p>
<form method="get" action="{{ route('app.vigentes') }}" class="sgd-filtros card card-body mb-3" aria-label="Filtros de documentos vigentes">
 <div class="form-row">
  <div class="form-group col-md-4"><label for="fq">Buscar</label><input class="form-control" id="fq" name="q" value="{{ $f['q'] ?? '' }}" maxlength="100" placeholder="Materia, N°, expediente, palabra clave…"></div>
  <div class="form-group col-md-2"><label for="fa">Año</label><select class="form-control" id="fa" name="anio"><option value="todos" @selected($f['anio']==='todos')>Todos</option>@foreach($anios as $a)<option value="{{ $a }}" @selected($f['anio']===$a)>{{ $a }}</option>@endforeach</select></div>
  <div class="form-group col-md-3"><label for="ft">Tipo de documento</label><select class="form-control" id="ft" name="tipo"><option value="">Todos</option><option value="decretos" @selected($f['tipo']==='decretos')>Decretos (todos)</option><option value="oficios" @selected($f['tipo']==='oficios')>Oficios</option>
   <optgroup label="Un tipo en particular">@foreach($tipos as $t)<option value="{{ $t->id }}" @selected($f['tipo']===(string) $t->id)>{{ $t->nombre }}</option>@endforeach</optgroup></select></div>
  <div class="form-group col-md-3"><label for="fu">Dirección</label><select class="form-control" id="fu" name="unidad"><option value="">Todas</option>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected((string) ($f['unidad'] ?? '')===(string) $un->id)>{{ $un->nombre }}</option>@endforeach</select></div>
 </div>
 <div class="form-row">
  <div class="form-group col-md-3"><label for="fm">Tipo de materia</label><select class="form-control" id="fm" name="tipo_materia"><option value="">Todos</option>@foreach($tiposMateria as $v)<option value="{{ $v }}" @selected(($f['tipo_materia'] ?? '')===$v)>{{ $v }}</option>@endforeach</select></div>
  <div class="form-group col-md-3"><label for="fmt">Macrotema</label><select class="form-control" id="fmt" name="macrotema"><option value="">Todos</option>@foreach($macrotemas as $v)<option value="{{ $v }}" @selected(($f['macrotema'] ?? '')===$v)>{{ $v }}</option>@endforeach</select></div>
  <div class="form-group col-md-2"><label for="fd">Firmado desde</label><input class="form-control" type="date" id="fd" name="desde" value="{{ $f['desde'] ?? '' }}"></div>
  <div class="form-group col-md-2"><label for="fh">Hasta</label><input class="form-control" type="date" id="fh" name="hasta" value="{{ $f['hasta'] ?? '' }}"></div>
  <div class="form-group col-md-2"><label for="fp">Por página</label><select class="form-control" id="fp" name="por_pagina">@foreach([25,50,100] as $n)<option value="{{ $n }}" @selected((int) ($f['por_pagina'] ?? 25)===$n)>{{ $n }}</option>@endforeach</select></div>
 </div>
 <div><button class="btn btn-primary">Buscar</button> <a class="btn btn-link" href="{{ route('app.vigentes') }}">Quitar filtros (año actual)</a></div>
</form>
<p class="small text-muted" role="status">{{ number_format($docs->total(), 0, ',', '.') }} documento(s) @if($f['anio']!=='todos')del año {{ $f['anio'] }}@else de todos los años @endif</p>
<div class="table-responsive"><table class="table table-sm sgd-tabla-azul"><caption class="sr-only">Documentos vigentes</caption>
<thead><tr>@foreach($col as [$k, $et])@php $activo = $f['orden']===$k; $sig = $activo && $f['dir']==='asc' ? 'desc' : 'asc'; @endphp<th scope="col" @if($activo) aria-sort="{{ $f['dir']==='asc' ? 'ascending' : 'descending' }}" @endif><a href="{{ $q(['orden' => $k, 'dir' => $sig]) }}">{{ $et }}<span aria-hidden="true"> {{ $activo ? ($f['dir']==='asc' ? '▲' : '▼') : '⇅' }}</span><span class="sr-only"> (ordenar {{ $sig==='asc' ? 'ascendente' : 'descendente' }})</span></a></th>@endforeach<th scope="col">Documento</th></tr></thead><tbody>
@forelse($docs as $d)
@if(in_array($d->id, $ocultos, true))<tr><td>—</td><td>{{ $d->fecha_firma?->timezone('America/Santiago')->format('d-m-Y') }}</td><td colspan="6"><em>Documento restringido</em></td><td>—</td></tr>@continue @endif
@php $puede = in_array($d->id, $abrir, true); $velar = $d->sensible && ! $puede; @endphp
<tr>
 <td>{{ $d->numero ?? '—' }}</td>
 <td>{{ $d->fecha_firma?->timezone('America/Santiago')->format('d-m-Y') ?? '—' }}</td>
 <td>{{ $d->tipo?->nombre }}</td>
 <td>{{ $d->expediente?->unidad?->nombre ?? '—' }}</td>
 <td>@if($velar)<em>Contiene datos sensibles</em>@else{{ $d->titulo }}@endif</td>
 <td>{{ $d->tipo_materia ?? '' }}</td>
 <td>{{ $d->macrotema ?? '' }}</td>
 <td>{{ $d->expediente?->codigo ?? '—' }}</td>
 <td class="text-nowrap">@if($puede)<a class="btn btn-primary btn-sm" href="{{ route('app.documento', $d) }}">Ver aquí<span class="sr-only"> {{ $d->numeroFormateado() }}</span></a>@else<span class="text-muted small">Sin acceso a su contenido</span>@endif
  @if($d->codigo_validacion && ($puede || $d->esPublico()))<br><a class="small" href="{{ route('validar', $d->codigo_validacion) }}">{{ $d->codigo_validacion }}</a>@endif</td>
</tr>
@empty<tr><td colspan="9" class="text-muted">No hay documentos vigentes con estos filtros. Pruebe con «Año: Todos».</td></tr>@endforelse</tbody></table></div>
{{ $docs->links() }}
@endsection
