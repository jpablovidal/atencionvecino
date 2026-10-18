@extends('layouts.app')
@section('titulo', 'Feriados')
@section('contenido')
<h1>Administrador de feriados</h1>
<p class="lead-sub">Los feriados y días no laborables registrados <strong>no cuentan como días administrativos</strong> en los plazos de los trámites y servicios (además de sábados y domingos). Al agregar o quitar un feriado, los plazos de las <strong>{{ $enCurso }}</strong> solicitudes en curso se recalculan automáticamente. Los feriados no vienen precargados: ingréselos según el calendario oficial de cada año (<strong>A VALIDAR</strong>).</p>

<nav aria-label="Años" class="mb-3"><ul class="nav nav-pills">@foreach($anios as $a)<li class="nav-item"><a class="nav-link {{ $a === $anio ? 'active' : '' }}" @if($a === $anio) aria-current="page" @endif href="{{ route('app.feriados', ['anio' => $a]) }}">{{ $a }}</a></li>@endforeach</ul></nav>

<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Feriados de {{ $anio }}</caption>
<thead><tr><th scope="col">Fecha</th><th scope="col">Día</th><th scope="col">Nombre</th><th scope="col">Tipo</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($feriados as $x) @php $c = \Illuminate\Support\Carbon::parse($x->fecha)->locale('es'); @endphp
<tr><td class="text-nowrap">{{ $c->format('d-m-Y') }}</td><td>{{ ucfirst($c->translatedFormat('l')) }}@if($c->isWeekend()) <span class="small text-muted">(ya es fin de semana)</span>@endif</td>
<td colspan="3"><form method="post" action="{{ route('app.feriados.actualizar', $x->id) }}" class="form-inline">@csrf
  <label class="sr-only" for="n{{ $x->id }}">Nombre del feriado {{ $c->format('d-m-Y') }}</label><input class="form-control form-control-sm mr-2" id="n{{ $x->id }}" name="nombre" value="{{ $x->nombre }}" maxlength="120" required>
  <label class="sr-only" for="t{{ $x->id }}">Tipo</label><select class="form-control form-control-sm mr-2" id="t{{ $x->id }}" name="tipo">@foreach($tipos as $k=>$v)<option value="{{ $k }}" @selected($x->tipo===$k)>{{ $v }}</option>@endforeach</select>
  <button class="btn btn-sm btn-outline-primary mr-2">Guardar<span class="sr-only"> {{ $x->nombre }}</span></button></form>
  <form method="post" action="{{ route('app.feriados.eliminar', $x->id) }}" class="d-inline" data-confirm="¿Eliminar el feriado {{ $c->format('d-m-Y') }}? Los plazos en curso se recalcularán.">@csrf<button class="btn btn-sm btn-outline-secondary">Eliminar<span class="sr-only"> {{ $x->nombre }}</span></button></form></td></tr>
@empty<tr><td colspan="5">No hay feriados registrados para {{ $anio }}. Mientras tanto, los días hábiles se cuentan de lunes a viernes.</td></tr>@endforelse</tbody></table></div>

<div class="row">
<section class="col-lg-6 mb-4" aria-labelledby="fe-add"><div class="card h-100"><div class="card-body"><h2 class="h5" id="fe-add">Agregar un feriado</h2>
<form method="post" action="{{ route('app.feriados.guardar') }}">@csrf
 <div class="form-group"><label for="fe-f">Fecha</label><input class="form-control" type="date" id="fe-f" name="fecha" required></div>
 <div class="form-group"><label for="fe-n">Nombre</label><input class="form-control" id="fe-n" name="nombre" maxlength="120" required placeholder="Ej.: Fiestas Patrias"></div>
 <div class="form-group"><label for="fe-t">Tipo</label><select class="form-control" id="fe-t" name="tipo">@foreach($tipos as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
 <button class="btn btn-primary">Agregar feriado</button></form></div></div></section>
<section class="col-lg-6 mb-4" aria-labelledby="fe-imp"><div class="card h-100"><div class="card-body"><h2 class="h5" id="fe-imp">Cargar varios a la vez</h2>
<form method="post" action="{{ route('app.feriados.importar') }}">@csrf
 <div class="form-group"><label for="fe-l">Una línea por feriado: <code>AAAA-MM-DD;Nombre;tipo</code> (el tipo es opcional: nacional, regional, comunal o administrativo)</label><textarea class="form-control" id="fe-l" name="lista" rows="6" maxlength="20000" required placeholder="2026-09-18;Fiestas Patrias;nacional&#10;2026-09-19;Día de las Glorias del Ejército;nacional"></textarea></div>
 <p class="small text-muted">Si alguna línea tiene un error no se guarda ninguna. Las fechas ya registradas se omiten.</p>
 <button class="btn btn-primary">Cargar feriados</button></form></div></div></section>
</div>

<section class="card mb-4" aria-labelledby="fe-sim"><div class="card-body"><h2 class="h5" id="fe-sim">Simulador de plazos</h2>
<form method="get" action="{{ route('app.feriados') }}" class="form-inline">
 <input type="hidden" name="anio" value="{{ $anio }}">
 <label class="mr-2" for="sf">Fecha de inicio</label><input class="form-control mr-3" type="date" id="sf" name="sim_fecha" value="{{ $f['sim_fecha'] ?? '' }}" required>
 <label class="mr-2" for="sd">Plazo (días)</label><input class="form-control mr-3" type="number" id="sd" name="sim_dias" min="1" max="365" value="{{ $f['sim_dias'] ?? 20 }}" required>
 <button class="btn btn-outline-primary">Calcular</button></form>
@if($errorSim)<p class="text-danger mt-2" role="alert">{{ $errorSim }}</p>@endif
@if($sim)<div class="mt-3" role="status"><p class="mb-1">Vence el <strong>{{ $sim['vence']->format('d-m-Y') }}</strong> en días administrativos (hábiles); en días corridos vencería el {{ $sim['corridos']->format('d-m-Y') }}.</p>
@if($sim['excluidos'])<p class="mb-0 small">Feriados excluidos: @foreach($sim['excluidos'] as $e){{ $e['fecha'] }} ({{ $e['motivo'] }})@unless($loop->last), @endunless @endforeach.</p>@else<p class="mb-0 small">No hay feriados registrados dentro de ese plazo.</p>@endif</div>@endif
</div></section>
<p class="small text-muted">Cada alta, cambio o eliminación queda en la bitácora de auditoría.</p>
@endsection
