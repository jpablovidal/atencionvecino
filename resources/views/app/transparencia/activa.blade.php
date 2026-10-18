@extends('layouts.app')
@section('titulo', 'Transparencia activa')
@section('contenido')
@php $puede = auth()->user()->tienePermiso('transparencia.gestionar'); $txt = ['al_dia'=>['Al día','cumplido'],'por_vencer'=>['Por vencer','por_vencer'],'pendiente'=>['Pendiente','en_plazo'],'vencida'=>['Vencida','vencido']]; @endphp
<h1>Transparencia activa</h1>
<p class="lead-sub">Información que debe mantenerse publicada y actualizada cada mes. Periodo vigente a informar: <strong>{{ $periodo }}</strong>. El catálogo es <strong>referencial (A VALIDAR)</strong> con la normativa del Consejo para la Transparencia.</p>
@include('app.transparencia._nav')
@include('partials.kpis', ['kpis' => [
  ['icono'=>'check-circle','valor'=>$resumen['al_dia'],'etiqueta'=>'Al día','nivel'=>'ok'],
  ['icono'=>'clock','valor'=>$resumen['pendiente'],'etiqueta'=>'Pendientes','nivel'=>'info'],
  ['icono'=>'alert','valor'=>$resumen['vencida'],'etiqueta'=>'Vencidas','nivel'=>$resumen['vencida']>0?'aviso':'ok'],
]])
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Calendario de transparencia activa</caption>
<thead><tr><th scope="col">Información</th><th scope="col">Dirección</th><th scope="col">Límite</th><th scope="col">Estado ({{ $periodo }})</th><th scope="col">Última publicación</th>@if($puede)<th scope="col">Registrar publicación</th>@endif</tr></thead><tbody>
@foreach($filas as $x) @php $i = $x['i']; $e = $x['e']; @endphp
<tr class="{{ $i->activo ? '' : 'text-muted' }}">
  <td>{{ $i->nombre }}@unless($i->activo) <em>(inactivo)</em>@endunless<br><small class="text-muted">{{ $i->codigo }}@if($i->url_publicacion) · <a href="{{ $i->url_publicacion }}" rel="noopener noreferrer" target="_blank">enlace<span class="sr-only"> (se abre en una pestaña nueva)</span></a>@endif</small></td>
  <td>{{ $i->unidad?->nombre ?? '—' }}</td><td class="text-nowrap">{{ $e['limite']->format('d-m-Y') }}</td>
  <td>@if($i->activo)<span class="plazo plazo-{{ $txt[$e['estado']][1] }}">{{ $txt[$e['estado']][0] }}</span>@else—@endif</td>
  <td>{{ $i->registros->first() ? 'Periodo '.$i->registros->first()->periodo.' · '.substr((string) $i->registros->first()->publicado_en, 0, 10) : 'Sin registros' }}</td>
  @if($puede)<td>@if($i->activo)<form method="post" action="{{ route('app.transparencia.activa.registrar', $i->id) }}" class="form-inline">@csrf
    <label class="sr-only" for="pe-{{ $i->id }}">Periodo</label><input class="form-control form-control-sm mr-1" id="pe-{{ $i->id }}" name="periodo" value="{{ $periodo }}" pattern="\d{4}-\d{2}" maxlength="7" required style="width:6rem">
    <label class="sr-only" for="pf-{{ $i->id }}">Fecha de publicación</label><input class="form-control form-control-sm mr-1" type="date" id="pf-{{ $i->id }}" name="publicado_en" value="{{ now('America/Santiago')->format('Y-m-d') }}" required>
    <label class="sr-only" for="pu-{{ $i->id }}">Enlace (https)</label><input class="form-control form-control-sm mr-1" id="pu-{{ $i->id }}" name="url" maxlength="300" placeholder="https://…" style="width:11rem">
    <button class="btn btn-sm btn-outline-primary">Registrar<span class="sr-only"> {{ $i->nombre }}</span></button></form>@endif</td>@endif
</tr>
@endforeach
</tbody></table></div>
@if($puede)
<details class="card card-body mb-3"><summary class="h6 mb-0">Agregar o editar ítems del calendario</summary>
<form method="post" action="{{ route('app.transparencia.activa.crear') }}" class="mt-3">@csrf
 <h2 class="h6">Nuevo ítem</h2>
 <div class="form-row"><div class="form-group col-md-3"><label for="n-codigo">Código</label><input class="form-control" id="n-codigo" name="codigo" required maxlength="30" pattern="[A-Za-z0-9_]{3,30}"></div>
 <div class="form-group col-md-5"><label for="n-nombre">Información</label><input class="form-control" id="n-nombre" name="nombre" required maxlength="190"></div>
 <div class="form-group col-md-2"><label for="n-dia">Día límite</label><input class="form-control" type="number" id="n-dia" name="dia_limite" min="1" max="28" value="{{ $defectoDia }}" required></div>
 <div class="form-group col-md-2"><label for="n-unidad">Dirección</label><select class="form-control" id="n-unidad" name="unidad_id"><option value="">—</option>@foreach($unidades as $un)<option value="{{ $un->id }}">{{ $un->nombre }}</option>@endforeach</select></div></div>
 <input type="hidden" name="activo" value="1"><button class="btn btn-primary btn-sm">Agregar</button></form>
@foreach($filas as $x) @php $i = $x['i']; @endphp
<form method="post" action="{{ route('app.transparencia.activa.guardar', $i->id) }}" class="border-top pt-2 mt-3">@csrf
 <h2 class="h6">{{ $i->codigo }}</h2>
 <div class="form-row"><div class="form-group col-md-4"><label for="e-n-{{ $i->id }}">Información</label><input class="form-control" id="e-n-{{ $i->id }}" name="nombre" required maxlength="190" value="{{ $i->nombre }}"></div>
 <div class="form-group col-md-3"><label for="e-u-{{ $i->id }}">Dirección responsable</label><select class="form-control" id="e-u-{{ $i->id }}" name="unidad_id"><option value="">—</option>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected($i->unidad_id===$un->id)>{{ $un->nombre }}</option>@endforeach</select></div>
 <div class="form-group col-md-1"><label for="e-d-{{ $i->id }}">Día</label><input class="form-control" type="number" id="e-d-{{ $i->id }}" name="dia_limite" min="1" max="28" value="{{ $i->dia_limite }}" required></div>
 <div class="form-group col-md-3"><label for="e-w-{{ $i->id }}">Enlace de publicación</label><input class="form-control" id="e-w-{{ $i->id }}" name="url_publicacion" maxlength="300" value="{{ $i->url_publicacion }}"></div>
 <div class="form-group col-md-1 pt-4"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="e-a-{{ $i->id }}" name="activo" value="1" @checked($i->activo)><label class="custom-control-label" for="e-a-{{ $i->id }}">Activo</label></div></div></div>
 <button class="btn btn-sm btn-outline-primary">Guardar {{ $i->codigo }}</button></form>
@endforeach
</details>
@endif
<p class="small text-muted">Se considera «al día» cuando hay una publicación registrada para el periodo vigente; vence el día indicado del mes en curso. Los registros son un historial: no se borran.</p>
@endsection
