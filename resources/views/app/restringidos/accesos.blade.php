@extends('layouts.app')
@section('titulo', 'Accesos a restringidos')
@section('contenido')
<h1>Accesos a expedientes, documentos y archivos restringidos</h1>
<p class="lead-sub">Quién vio —o intentó ver— contenido restringido. Por protección de datos personales solo se muestran nombre, cargo y RUN parcialmente enmascarado; no se muestra el contenido ni la dirección IP. Esta consulta queda registrada.</p>
<div class="row mb-3">@foreach($kpis as $k)<div class="col-sm-6 col-lg-3 mb-2"><div class="card h-100"><div class="card-body"><div class="h3 mb-0">{{ $k['valor'] }}</div><div class="small">{{ $k['etiqueta'] }}</div></div></div></div>@endforeach</div>

<form method="get" class="form-inline mb-3" aria-label="Filtrar accesos">
 <label class="mr-2" for="fp">Persona</label><input class="form-control mr-3" id="fp" name="persona" value="{{ $f['persona'] ?? '' }}" maxlength="80">
 <label class="mr-2" for="fx">Expediente</label><input class="form-control mr-3" id="fx" name="expediente" value="{{ $f['expediente'] ?? '' }}" maxlength="40">
 <label class="mr-2" for="fr">Resultado</label><select class="form-control mr-3" id="fr" name="resultado"><option value="">Todos</option><option value="permitido" @selected(($f['resultado'] ?? '')==='permitido')>Permitido</option><option value="denegado" @selected(($f['resultado'] ?? '')==='denegado')>Denegado</option></select>
 <label class="mr-2" for="fd">Desde</label><input class="form-control mr-3" type="date" id="fd" name="desde" value="{{ $f['desde'] ?? '' }}">
 <label class="mr-2" for="fh">Hasta</label><input class="form-control mr-3" type="date" id="fh" name="hasta" value="{{ $f['hasta'] ?? '' }}">
 <button class="btn btn-outline-primary">Filtrar</button></form>

<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Accesos e intentos de acceso a contenido restringido</caption>
<thead><tr><th scope="col">Fecha y hora</th><th scope="col">Persona</th><th scope="col">Cargo</th><th scope="col">RUN</th><th scope="col">Resultado</th><th scope="col">Expediente</th><th scope="col">Elemento</th></tr></thead><tbody>
@forelse($filas as $x)<tr><td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($x['ts'])->format('d-m-Y H:i') }}</td><td>{{ $x['persona'] }}</td><td>{{ $x['cargo'] ?: '—' }}</td><td>{{ $x['run'] }}</td>
<td>@if($x['resultado']==='permitido')<span class="chip chip-claro">Permitido</span>@else<span class="chip chip-claro">Denegado</span>@endif</td><td>{{ $x['expediente'] }}</td><td>{{ $x['entidad'] }} {{ $x['entidad_id'] }} {{ $x['accion'] }}</td></tr>
@empty<tr><td colspan="7">Sin registros para los filtros indicados.</td></tr>@endforelse</tbody></table></div>
{{ $pag->links() }}

<section aria-labelledby="rs-lista" class="mt-4"><h2 class="h5" id="rs-lista">Expedientes con restricciones</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Expedientes con contenido restringido</caption>
<thead><tr><th scope="col">Expediente</th><th scope="col">Alcance</th><th scope="col">Marcado por</th><th scope="col">Personas autorizadas</th></tr></thead><tbody>
@forelse($restringidos as $e)<tr><td>{{ $e->codigo }}</td><td>{{ $e->restringido ? 'Todo el expediente' : 'Documentos o archivos puntuales' }}</td>
<td>@php $m = $marcadores->get($e->restringido_por); @endphp{{ $m ? trim($m->nombres.' '.$m->apellidos) : '—' }}</td>
<td>@forelse(($auth[$e->id] ?? collect()) as $a){{ trim(($a->usuario->nombres ?? '').' '.($a->usuario->apellidos ?? '')) }}@unless($loop->last), @endunless @empty — @endforelse</td></tr>
@empty<tr><td colspan="4">No hay expedientes restringidos.</td></tr>@endforelse</tbody></table></div></section>
@endsection
