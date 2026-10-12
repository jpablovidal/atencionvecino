@extends('layouts.app')
@section('titulo', 'Usuario')
@section('contenido')
<h1>Usuario: {{ $fila ? 'editar' : 'nuevo' }}</h1>
<form method="post" action="{{ $fila ? route('app.admin.usuarios.actualizar', $fila) : route('app.admin.usuarios.guardar') }}" class="col-lg-9 px-0">@csrf
  <div class="form-group"><label for="run">RUN (con guion y DV)</label><input class="form-control" id="run" name="run" maxlength="12" required value="{{ old('run', $fila->run ?? '') }}"></div>
  <div class="form-row"><div class="form-group col-md-6"><label for="nombres">Nombres</label><input class="form-control" id="nombres" name="nombres" maxlength="120" required value="{{ old('nombres', $fila->nombres ?? '') }}"></div>
  <div class="form-group col-md-6"><label for="apellidos">Apellidos</label><input class="form-control" id="apellidos" name="apellidos" maxlength="120" required value="{{ old('apellidos', $fila->apellidos ?? '') }}"></div></div>
  <div class="form-group"><label for="correo">Correo institucional</label><input class="form-control" type="email" id="correo" name="correo" maxlength="190" required value="{{ old('correo', $fila->correo ?? '') }}"></div>
  <div class="form-group"><label for="cargo">Cargo</label><input class="form-control" id="cargo" name="cargo" maxlength="120" value="{{ old('cargo', $fila->cargo ?? '') }}"></div>
  <div class="form-group"><label for="rol">Rol</label><select class="form-control" id="rol" name="rol" aria-describedby="rol-ayuda" required>@foreach($roles as $r)<option value="{{ $r->clave }}" @selected(old('rol', $fila->rol ?? 'funcionario') === $r->clave)>{{ $r->nombre }}{{ $r->sistema ? '' : ' (personalizado)' }}</option>@endforeach</select><small id="rol-ayuda" class="form-text text-muted">Los permisos se definen en «Roles y permisos». Al cambiar el rol se cierran las sesiones abiertas del usuario.</small></div>
  <div class="form-group"><label for="unidad_id">Unidad</label><select class="form-control" id="unidad_id" name="unidad_id"><option value="">—</option>@foreach($unidades as $u)<option value="{{ $u->id }}" @selected((string) old('unidad_id', $fila->unidad_id ?? '') === (string) $u->id)>{{ $u->nombre }}</option>@endforeach</select></div>
  <div class="form-group"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="puede_firmar" name="puede_firmar" value="1" @checked(old('puede_firmar', $fila->puede_firmar ?? false))><label class="custom-control-label" for="puede_firmar">Habilitado para firmar (no aplica a administradores ni auditores)</label></div></div>
  <button class="btn btn-primary">Guardar</button> <a class="btn btn-link" href="{{ route('app.admin.index', 'usuarios') }}">Cancelar</a>
</form>
@endsection
