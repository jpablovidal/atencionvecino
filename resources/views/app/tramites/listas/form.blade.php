@extends('layouts.app')
@section('titulo', $lista ? 'Listado de opciones' : 'Nuevo listado de opciones')
@section('contenido')
<nav aria-label="Ruta de navegación"><a href="{{ route('app.tramites.listas') }}">&larr; Volver a los listados</a></nav>
<h1>{{ $lista ? $lista->nombre : 'Nuevo listado de opciones' }}</h1>
@if($lista && ($usos ?? 0) > 0)<div class="alert alert-info" role="status">Este listado se usa en {{ $usos }} campo(s) de trámites. Los cambios se reflejan en todos ellos; lo que la ciudadanía ya envió conserva lo que eligió.</div>@endif
@unless($puede)<div class="alert alert-warning" role="alert">Este listado pertenece a otra dirección: puede usarlo en sus trámites, pero no modificarlo.</div>@endunless
<form method="post" action="{{ $lista ? route('app.tramites.listas.actualizar', $lista) : route('app.tramites.listas.guardar') }}" class="col-lg-8 px-0">@csrf
<fieldset @disabled(! $puede)>
<div class="form-group"><label for="nombre">Nombre del listado</label><input class="form-control" id="nombre" name="nombre" required minlength="3" maxlength="120" value="{{ old('nombre', $lista->nombre ?? '') }}"></div>
<div class="form-group"><label for="descripcion">Descripción (opcional)</label><input class="form-control" id="descripcion" name="descripcion" maxlength="250" value="{{ old('descripcion', $lista->descripcion ?? '') }}"></div>
@if($admin && ! $lista)<div class="form-group"><label for="unidad_id">Dirección propietaria (quién puede editarlo)</label><select class="form-control" id="unidad_id" name="unidad_id"><option value="">Solo administración</option>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected(old('unidad_id') == $un->id)>{{ $un->nombre }}</option>@endforeach</select></div>@endif
<div class="form-group"><label for="opciones">Opciones (una por línea)</label><textarea class="form-control" id="opciones" name="opciones" rows="10" required aria-describedby="op-ayuda">{{ old('opciones', $texto) }}</textarea>
<small id="op-ayuda" class="form-text text-muted">Escriba solo el texto (<code>Casa</code>) o <code>valor;texto</code> (<code>0.5;Media tarifa</code>). El valor se guarda en la solicitud y, si es un número, se puede usar en una fórmula. Mínimo 2 y máximo 200 opciones; no repita valores.</small></div>
@if($puede)<button class="btn btn-primary">Guardar listado</button>@endif
</fieldset>
</form>
@if($lista && $puede)
<form method="post" action="{{ route('app.tramites.listas.estado', $lista) }}" class="mt-3">@csrf<input type="hidden" name="activa" value="{{ $lista->activa ? 0 : 1 }}"><button class="btn btn-outline-secondary">{{ $lista->activa ? 'Desactivar (no ofrecer en campos nuevos)' : 'Volver a activar' }}</button></form>
@endif
@endsection
