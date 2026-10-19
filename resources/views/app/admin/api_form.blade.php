@extends('layouts.app')
@section('titulo', 'API: '.$d['etiqueta'])
@section('contenido')
<p><a href="{{ route('app.admin.api') }}">&larr; APIs e integraciones</a></p>
<h1>{{ $d['etiqueta'] }}</h1>
<p>{{ $d['descripcion'] }}</p>
<form method="post" action="{{ route('app.admin.api.actualizar',$clave) }}" class="col-lg-9 px-0" autocomplete="off" enctype="multipart/form-data">@csrf
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="activo" name="activo" value="1" @checked(old('activo',$estado['activo']))><label class="custom-control-label" for="activo">Usar esta configuración de la base de datos (si no, se usa <code>.env</code>)</label></div>
@foreach($d['campos'] as $k => $def)
@php [$etq,$tipo,$path] = $def; $op = $def[3] ?? []; $secreto = $op['secreto'] ?? false; $val = old($k, $estado['valores'][$k] ?? ($secreto ? null : config($path))); @endphp
<div class="form-group">
@if($tipo==='bool')
  <div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="{{ $k }}" name="{{ $k }}" value="1" @checked($val)><label class="custom-control-label" for="{{ $k }}">{{ $etq }}</label></div>
@elseif($secreto)
  <label for="{{ $k }}">{{ $etq }} <span class="estado">{{ in_array($k,$estado['secretos_bd']) ? 'definido en la BD' : ($ef[$k]['definido'] ? 'definido en .env' : 'sin definir') }}</span></label>
  <input class="form-control" type="password" id="{{ $k }}" name="{{ $k }}" autocomplete="new-password" @disabled(! $secretosBd) aria-describedby="s-{{ $k }}" placeholder="{{ $secretosBd ? 'Escriba para reemplazar (vacío = no cambiar)' : 'Desactivado: defínalo en .env' }}">
  <small id="s-{{ $k }}" class="form-text text-muted">El valor nunca se muestra.</small>
  @if(in_array($k,$estado['secretos_bd']))<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="b-{{ $k }}" name="borrar[]" value="{{ $k }}"><label class="custom-control-label" for="b-{{ $k }}">Eliminar el valor guardado en la BD</label></div>@endif
@else
  @if(!empty($op['subida']))
  @php $cuenta = \App\Services\Repositorio\CredencialDriveService::resumen($val ?: config($path)); @endphp
  <label for="archivo_{{ $k }}">Cargar la clave JSON de la cuenta de servicio</label>
  <input type="file" class="form-control-file mb-1" id="archivo_{{ $k }}" name="archivo_{{ $k }}" accept=".json,application/json" aria-describedby="f-{{ $k }}">
  <small id="f-{{ $k }}" class="form-text text-muted mb-2">Suba el archivo JSON descargado de Google Cloud (máx. 20 KB): se valida y se guarda en el servidor fuera del sitio web, con permisos 600, y <strong>la clave nunca se muestra</strong>; al guardar, la ruta de abajo se completa sola.@if($cuenta) <strong>Cuenta cargada:</strong> {{ $cuenta['email'] }} (proyecto {{ $cuenta['proyecto'] }}).@else Aún no hay una clave cargada.@endif</small>
  @endif
  <label for="{{ $k }}">{{ $etq }}</label>
  @if($tipo==='select')<select class="form-control" id="{{ $k }}" name="{{ $k }}">@foreach($op['opciones'] as $v=>$l)<option value="{{ $v }}" @selected($val===$v)>{{ $l }}</option>@endforeach</select>
  @else<input class="form-control" type="{{ $tipo==='number' ? 'number' : 'text' }}" id="{{ $k }}" name="{{ $k }}" value="{{ $val }}" maxlength="500" @if(!empty($op['ayuda'])) aria-describedby="a-{{ $k }}" @endif>@endif
  @if(!empty($op['ayuda']))<small id="a-{{ $k }}" class="form-text text-muted">{{ $op['ayuda'] }}</small>@endif
@endif
</div>
@endforeach
<button class="btn btn-primary">Guardar</button>
</form>
<div class="mt-3 d-flex">
<form method="post" action="{{ route('app.admin.api.probar',$clave) }}" class="mr-2">@csrf<button class="btn btn-outline-primary">Validar configuración</button></form>
@if($estado['activo'])<form method="post" action="{{ route('app.admin.api.env',$clave) }}" data-confirm="¿Volver a usar la configuración del archivo .env?">@csrf<button class="btn btn-outline-secondary">Volver a .env</button></form>@endif
</div>
@if($pruebas)<h2 class="h5 mt-4">Resultado de la validación</h2>
<table class="table table-sm"><caption class="sr-only">Validación</caption><thead><tr><th scope="col">Campo</th><th scope="col">Resultado</th><th scope="col">Detalle</th></tr></thead><tbody>
@foreach($pruebas as [$n,$ok,$det])<tr><td>{{ $n }}</td><td><span class="estado {{ $ok ? 'estado-vigente' : 'estado-fallida' }}">{{ $ok ? 'OK' : 'REVISAR' }}</span></td><td>{{ $det }}</td></tr>@endforeach</tbody></table>@endif
@endsection
