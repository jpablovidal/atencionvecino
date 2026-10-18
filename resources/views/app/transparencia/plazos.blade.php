@extends('layouts.app')
@section('titulo', 'Plazos y feriados')
@section('contenido')
@php $puede = auth()->user()->tienePermiso('transparencia.gestionar'); @endphp
<h1>Plazos y feriados</h1>
<p class="lead-sub">Administre los parámetros de plazo y los feriados que no se cuentan como días hábiles. Los valores legales por defecto son <strong>A VALIDAR</strong> con la asesoría jurídica (ver <code>docs/NORMATIVA.md</code> §9).</p>
@include('app.transparencia._nav')
<section class="card mb-4"><div class="card-body"><h2 class="h5">Trámites de acceso a la información</h2>
<ul class="mb-0">@forelse($tramites as $t)<li>{{ $t->nombre }}: {{ $t->plazo_dias }} días {{ $t->plazo_habiles ? 'hábiles' : 'corridos' }} <span class="small text-muted">(se cambia en <em>Administración → Trámites y servicios</em>)</span></li>@empty<li>No hay trámites marcados como solicitud de acceso a la información.</li>@endforelse</ul></div></section>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Parámetros</h2>
<form method="post" action="{{ route('app.transparencia.plazos.guardar') }}">@csrf
@foreach($params as $k=>$p)<div class="form-group row"><label class="col-md-7 col-form-label" for="p-{{ $k }}">{{ $p['etiqueta'] }}</label><div class="col-md-2"><input class="form-control" type="number" id="p-{{ $k }}" name="{{ $k }}" min="{{ $p['min'] }}" max="{{ $p['max'] }}" value="{{ $p['valor'] }}" @disabled(!$puede) required></div></div>@endforeach
@if($puede)<button class="btn btn-primary">Guardar parámetros</button>@endif</form>
<p class="small text-muted mt-2 mb-0">«Por vencer» (alerta previa) y el cómputo en días corridos u hábiles de los demás trámites se configuran en el servidor (<code>SGD_PLAZOS_AVISO_DIAS</code>, <code>SGD_PLAZOS_HABILES</code>).</p></div></section>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Feriados (no cuentan como días hábiles)</h2>
<p class="small text-muted">No vienen precargados: ingrese los feriados oficiales del año. Los plazos en días hábiles se recalculan al guardar.</p>
@if($puede)<form method="post" action="{{ route('app.transparencia.feriados.agregar') }}" class="form-inline mb-3">@csrf
<label class="mr-2" for="fe-f">Fecha</label><input class="form-control mr-2" type="date" id="fe-f" name="fecha" required>
<label class="mr-2" for="fe-n">Nombre</label><input class="form-control mr-2" id="fe-n" name="nombre" maxlength="120" required placeholder="Ej.: Fiestas Patrias">
<button class="btn btn-primary">Agregar feriado</button></form>@endif
<div class="table-responsive"><table class="table table-sm mb-0"><caption class="sr-only">Feriados registrados</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Nombre</th>@if($puede)<th scope="col"><span class="sr-only">Acciones</span></th>@endif</tr></thead><tbody>
@forelse($feriados as $f)<tr><td>{{ \Illuminate\Support\Carbon::parse($f->fecha)->format('d-m-Y') }}</td><td>{{ $f->nombre }}</td>@if($puede)<td><form method="post" action="{{ route('app.transparencia.feriados.quitar', $f->id) }}" data-confirm="¿Quitar el feriado?">@csrf<button class="btn btn-sm btn-outline-secondary">Quitar<span class="sr-only"> {{ $f->nombre }}</span></button></form></td>@endif</tr>
@empty<tr><td colspan="3">Sin feriados registrados: los días hábiles se cuentan de lunes a viernes.</td></tr>@endforelse</tbody></table></div></div></section>
@endsection
