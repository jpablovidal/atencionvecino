@extends('layouts.app')
@section('titulo', 'Rol')
@section('contenido')
@php $ro = $rol && $rol->sistema; $base = old('rol_base', $rol->rol_base ?? 'funcionario'); $marcados = old('permisos', $ro ? \App\Support\Permisos::deBase($rol->rol_base) : $sel); @endphp
<h1>Rol: {{ $rol ? ($ro ? $rol->nombre.' (solo lectura)' : 'editar') : 'nuevo' }}</h1>
<form method="post" action="{{ $rol && ! $ro ? route('app.admin.roles.actualizar', $rol) : route('app.admin.roles.guardar') }}" class="col-lg-10 px-0">@csrf
  <div class="form-row"><div class="form-group col-md-4"><label for="clave">Clave (minúsculas, números y _; 3-20)</label><input class="form-control" id="clave" name="clave" maxlength="20" pattern="[a-z][a-z0-9_]{2,19}" @if($rol) value="{{ $rol->clave }}" readonly @else value="{{ old('clave') }}" required @endif></div>
  <div class="form-group col-md-8"><label for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" maxlength="80" required value="{{ old('nombre', $rol->nombre ?? '') }}" @readonly($ro)></div></div>
  <div class="form-group"><label for="descripcion">Descripción</label><input class="form-control" id="descripcion" name="descripcion" maxlength="300" value="{{ old('descripcion', $rol->descripcion ?? '') }}" @readonly($ro)></div>
  <div class="form-group"><label for="rol_base">Rol base</label><select class="form-control" id="rol_base" name="rol_base" aria-describedby="base-ayuda" @disabled($ro || $asignados > 0)>@foreach(\App\Models\Usuario::ROLES as $b)<option value="{{ $b }}" @selected($base === $b)>{{ $b }}</option>@endforeach</select>
    @if($asignados > 0 && ! $ro)<input type="hidden" name="rol_base" value="{{ $rol->rol_base }}">@endif
    <small id="base-ayuda" class="form-text text-muted">Define el techo de permisos y las reglas de negocio (p. ej., quién puede firmar o ser asignado a expedientes).@if($asignados > 0 && ! $ro) No editable mientras haya usuarios asignados.@endif</small></div>
  <fieldset class="mb-3"><legend class="h5">Permisos</legend>
    <p class="small text-muted">Los permisos deshabilitados no son admitidos por el rol base seleccionado o están reservados a los roles del sistema.</p>
    @foreach(collect($catalogo)->groupBy(fn ($d) => $d[1], true) as $grupo => $items)
      <fieldset class="mb-2"><legend class="h6">{{ $grupo }}</legend>
      @foreach($items as $k => $d)@php $ok = ! $ro && empty($d[3]) && in_array($base, $d[2], true); @endphp
        <div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="p_{{ str_replace('.', '_', $k) }}" name="permisos[]" value="{{ $k }}" data-bases="{{ implode(',', empty($d[3]) ? $d[2] : []) }}" @checked(in_array($k, $marcados, true)) @disabled(! $ok)><label class="custom-control-label" for="p_{{ str_replace('.', '_', $k) }}">{{ $d[0] }} <code class="small">{{ $k }}</code></label></div>
      @endforeach
      </fieldset>
    @endforeach
  </fieldset>
  @unless($ro)<button class="btn btn-primary">Guardar</button> @endunless<a class="btn btn-link" href="{{ route('app.admin.roles') }}">{{ $ro ? 'Volver' : 'Cancelar' }}</a>
</form>
@endsection
