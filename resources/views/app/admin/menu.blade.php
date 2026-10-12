@extends('layouts.app')
@section('titulo', 'Barra de menú')
@section('contenido')
<h1>Barra de menú</h1>
<p>Cambie el nombre y el orden de los ítems, oculte los que no use (los marcados como <em>protegidos</em> no pueden ocultarse) y agregue enlaces propios. Un ítem solo se muestra a quien tiene el permiso correspondiente: el menú <strong>no concede acceso</strong>.</p>
<h2 class="h4">Ítems del sistema</h2>
<table class="table table-sm"><caption class="sr-only">Ítems del sistema</caption><thead><tr><th scope="col">Ítem</th><th scope="col">Áreas</th><th scope="col">Etiqueta</th><th scope="col">Orden</th><th scope="col">Visible</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($sistema as $clave=>$d)@php $o = $overrides->get($clave); @endphp
<tr><td>{{ $d['etiqueta'] }}@if($d['protegido']) <span class="estado">protegido</span>@endif<br><small class="text-muted">{{ $d['grupo'] ?? 'Barra principal' }}</small></td><td>{{ implode(', ',$d['areas']) }}</td>
<td colspan="4"><form method="post" action="{{ route('app.admin.menu.sistema',$clave) }}" class="form-inline">@csrf
<label class="sr-only" for="et_{{ $clave }}">Etiqueta de {{ $d['etiqueta'] }}</label><input class="form-control form-control-sm mr-2" id="et_{{ $clave }}" name="etiqueta" value="{{ $o->etiqueta ?? $d['etiqueta'] }}" maxlength="40" required>
<label class="sr-only" for="or_{{ $clave }}">Orden de {{ $d['etiqueta'] }}</label><input class="form-control form-control-sm mr-2" style="width:5rem" type="number" id="or_{{ $clave }}" name="orden" min="0" max="999" value="{{ $o->orden ?? $d['orden'] }}" required>
<div class="custom-control custom-checkbox mr-2"><input class="custom-control-input" type="checkbox" id="ac_{{ $clave }}" name="activo" value="1" @checked($o ? $o->activo : true) @disabled($d['protegido'])><label class="custom-control-label" for="ac_{{ $clave }}">Visible</label></div>
<button class="btn btn-sm btn-primary mr-1">Guardar</button></form>
@if($o)<form method="post" action="{{ route('app.admin.menu.restablecer',$clave) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">Restablecer</button></form>@endif</td></tr>
@endforeach</tbody></table>

<h2 class="h4 mt-4">Enlaces propios</h2>
<table class="table table-sm"><caption class="sr-only">Enlaces propios</caption><thead><tr><th scope="col">Etiqueta</th><th scope="col">Dirección</th><th scope="col">Área</th><th scope="col">Roles</th><th scope="col">Orden</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($propios as $m)<tr><td>{{ $m->etiqueta }}</td><td>{{ $m->url }}{{ $m->nueva_pestana ? ' (pestaña nueva)' : '' }}</td><td>{{ $m->area }}</td><td>{{ $m->roles ?: 'todos' }}</td><td>{{ $m->orden }}</td><td>{{ $m->activo ? 'activo' : 'oculto' }}</td>
<td><form method="post" action="{{ route('app.admin.menu.eliminar',$m) }}" class="d-inline" data-confirm="¿Eliminar este enlace?">@csrf<button class="btn btn-sm btn-outline-danger">Eliminar</button></form></td></tr>
@empty<tr><td colspan="7">No hay enlaces propios.</td></tr>@endforelse</tbody></table>
<form method="post" action="{{ route('app.admin.menu.propio') }}" class="card card-body col-lg-10">@csrf<h3 class="h5">Nuevo enlace</h3>
<div class="form-row">
<div class="form-group col-md-3"><label for="n_etq">Etiqueta</label><input class="form-control" id="n_etq" name="etiqueta" required maxlength="40" value="{{ old('etiqueta') }}"></div>
<div class="form-group col-md-5"><label for="n_url">Dirección (/ruta interna o https://…)</label><input class="form-control" id="n_url" name="url" required maxlength="255" value="{{ old('url') }}"></div>
<div class="form-group col-md-2"><label for="n_area">Área</label><select class="form-control" id="n_area" name="area"><option value="app">Funcionarios</option><option value="ciudadano">Ciudadanos</option><option value="publico">Público</option></select></div>
<div class="form-group col-md-2"><label for="n_ord">Orden</label><input class="form-control" type="number" id="n_ord" name="orden" min="0" max="999" value="{{ old('orden',100) }}" required></div></div>
<fieldset class="mb-2"><legend class="h6">Roles que lo ven (solo área de funcionarios; ninguno = todos)</legend>
@foreach($roles as $r)<div class="custom-control custom-checkbox custom-control-inline"><input class="custom-control-input" type="checkbox" id="n_r_{{ $r }}" name="roles[]" value="{{ $r }}"><label class="custom-control-label" for="n_r_{{ $r }}">{{ $r }}</label></div>@endforeach</fieldset>
<div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="n_np" name="nueva_pestana" value="1"><label class="custom-control-label" for="n_np">Abrir en una pestaña nueva</label></div>
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="n_ac" name="activo" value="1" checked><label class="custom-control-label" for="n_ac">Visible</label></div>
<div><button class="btn btn-primary">Agregar enlace</button></div></form>
@endsection
