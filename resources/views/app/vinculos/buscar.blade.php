@extends('layouts.app')
@section('titulo', 'Asociar al expediente '.$e->codigo)
@section('contenido')
<p><a href="{{ route('app.expediente', $e) }}">← Expediente {{ $e->codigo }}</a></p>
<h1>Asociar otros expedientes y microformas</h1>
<p class="lead-sub">Busque un expediente o una microforma para <strong>reutilizar su proceso y sus antecedentes</strong> en el expediente {{ $e->codigo }}. Primero se asocia (queda registrado quién y por qué); luego puede <strong>incorporar copias</strong> de sus documentos y archivos. Solo aparece lo que usted ya puede ver; lo restringido exige autorización.</p>
<form method="get" class="form-inline mb-3" role="search" aria-label="Buscar para asociar">
 <label class="mr-2" for="aq">Buscar</label><input class="form-control mr-2" id="aq" name="q" value="{{ $q }}" maxlength="80" required placeholder="Código, folio, trámite, título…">
 <label class="mr-2" for="at">En</label><select class="form-control mr-2" id="at" name="tipo"><option value="expedientes" @selected($tipo==='expedientes')>Expedientes</option>@if($puedeMicro)<option value="microformas" @selected($tipo==='microformas')>Microformas (archivo municipal)</option>@endif</select>
 <button class="btn btn-primary">Buscar</button></form>
@if($q !== '' && mb_strlen($q) < 3)<p class="text-danger" role="alert">Escriba al menos 3 caracteres.</p>@endif
@if($q !== '')
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Resultados de la búsqueda</caption>
<thead><tr><th scope="col">{{ $tipo==='microformas' ? 'Microforma' : 'Expediente' }}</th><th scope="col">Detalle</th><th scope="col">Asociar</th></tr></thead><tbody>
@forelse($resultados as $x)<tr>
 @if($tipo==='microformas')<td>{{ $x->codigo ?: '#'.$x->id }}<br><small>{{ $x->estado }}</small></td><td>{{ $x->titulo }}<br><small>{{ $x->unidad?->nombre }} · {{ $x->serie }}</small></td>
 @else<td>{{ $x->codigo }}@if($x->restringido) <span class="chip chip-claro">restringido</span>@endif<br><small>{{ $x->estado }}</small></td><td>{{ $x->solicitud?->tramite?->nombre ?? 'Comunicación recibida' }}<br><small>{{ $x->unidad?->nombre }}@if($x->solicitud) · folio {{ $x->solicitud->folio }}@endif</small></td>@endif
 <td><form method="post" action="{{ route('app.vinculos.crear', $e) }}" class="form-inline">@csrf<input type="hidden" name="tipo" value="{{ $tipo==='microformas' ? 'microforma' : 'expediente' }}"><input type="hidden" name="id" value="{{ $x->id }}">
  <label class="sr-only" for="m{{ $x->id }}">Motivo de la asociación</label><input class="form-control form-control-sm mr-1" id="m{{ $x->id }}" name="motivo" required minlength="10" maxlength="300" placeholder="Motivo (mín. 10 caracteres)"><button class="btn btn-sm btn-outline-primary">Asociar<span class="sr-only"> {{ $tipo==='microformas' ? ($x->codigo ?: $x->titulo) : $x->codigo }}</span></button></form></td></tr>
@empty<tr><td colspan="3">Sin resultados que usted pueda asociar.</td></tr>@endforelse</tbody></table></div>
@endif
@endsection
