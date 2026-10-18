@extends('layouts.app')
@section('titulo', $tramite ? 'Editar trámite' : 'Nuevo trámite')
@section('contenido')
<h1>{{ $tramite ? 'Editar trámite' : 'Nuevo trámite o servicio' }}</h1>
@if($tramite)<p><span class="estado estado-{{ $tramite->estado }}">{{ $tramite->estado }}</span> · versión {{ $tramite->version }} · <a href="{{ route('app.tramites.vista',$tramite->id) }}">Vista previa como ciudadano</a> · <a href="{{ route('app.tramites.historial',$tramite->id) }}">Historial</a></p>@endif
@unless($puedeEditar)<div class="alert alert-warning" role="alert">Este trámite está publicado: para modificarlo se requiere el permiso de publicación. Puede deshabilitarlo.</div>@endunless
@php $filas = old('anexos', $tramite ? $tramite->anexos->map(fn($a)=>['id'=>$a->id,'nombre'=>$a->nombre,'descripcion'=>$a->descripcion,'obligatorio'=>$a->obligatorio])->all() : [['nombre'=>'','obligatorio'=>1]]); @endphp
<form method="post" action="{{ $tramite ? route('app.tramites.actualizar',$tramite->id) : route('app.tramites.guardar') }}" class="col-lg-9 px-0" @if($puedeEditar) data-pasos @endif>@csrf
<fieldset @disabled(!$puedeEditar)>
<div data-paso="Datos del trámite">
<p class="small text-muted">Dirección: <strong>{{ $tramite ? $tramite->propietaria?->nombre : ($unidad?->nombre ?? 'sin dirección asignada') }}</strong>. Los textos se guardan como texto plano (sin formato ni enlaces).</p>
@if($admin && !$tramite)<div class="form-group"><label for="unidad_propietaria_id">Dirección propietaria</label><select class="form-control" id="unidad_propietaria_id" name="unidad_propietaria_id" required>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected(old('unidad_propietaria_id')==$un->id)>{{ $un->nombre }}</option>@endforeach</select></div>@endif
<div class="form-group"><label for="nombre">Nombre del trámite o servicio</label><input class="form-control" id="nombre" name="nombre" required minlength="5" maxlength="190" value="{{ old('nombre',$tramite->nombre ?? '') }}"></div>
<div class="form-group"><label for="descripcion">Descripción (qué es y quién puede solicitarlo)</label><textarea class="form-control" id="descripcion" name="descripcion" rows="4" maxlength="2000" aria-describedby="d-desc">{{ old('descripcion',$tramite->descripcion ?? '') }}</textarea><small id="d-desc" class="form-text text-muted">Mínimo 30 caracteres para poder publicarlo.</small></div>
<div class="form-group"><label for="instrucciones">Instrucciones para el solicitante (opcional)</label><textarea class="form-control" id="instrucciones" name="instrucciones" rows="4" maxlength="4000">{{ old('instrucciones',$tramite->instrucciones ?? '') }}</textarea></div>
<div class="form-group col-md-4 px-0"><label for="plazo_dias">Plazo de respuesta (días)</label><input class="form-control" type="number" id="plazo_dias" name="plazo_dias" min="1" max="365" required value="{{ old('plazo_dias',$tramite->plazo_dias ?? 15) }}"></div>
</div>
@if($admin)
<div data-paso="Direcciones y codificación">
<div class="form-group"><span class="d-block font-weight-bold">Direcciones responsables</span>@foreach($unidades as $un)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="u{{ $un->id }}" name="unidades[]" value="{{ $un->id }}" @checked(in_array($un->id, old('unidades',$sel)))><label class="custom-control-label" for="u{{ $un->id }}">{{ $un->nombre }}</label></div>@endforeach</div>
<div class="form-row"><div class="form-group col-md-6"><label for="codigo_cpat">Código CPAT (opcional)</label><input class="form-control" id="codigo_cpat" name="codigo_cpat" value="{{ old('codigo_cpat',$tramite->codigo_cpat ?? '') }}"></div>
<div class="form-group col-md-6"><label for="proceso_codigo">Proceso del Codificador (opcional)</label><select class="form-control" id="proceso_codigo" name="proceso_codigo"><option value="">—</option>@foreach($procesos as $p)<option value="{{ $p->codigo }}" @selected(old('proceso_codigo',$tramite->proceso_codigo ?? '')===$p->codigo)>{{ $p->codigo }} — {{ $p->nombre }}</option>@endforeach</select></div></div>
</div>
@endif
<fieldset class="form-group" data-paso="Documentos requeridos"><legend class="h5">Documentos o archivos requeridos</legend>
<p class="small text-muted">El ciudadano deberá cargarlos al solicitar el trámite (PDF, JPG, PNG o TIFF). Los obligatorios impiden enviar la solicitud si faltan. Máximo 15.</p>
<div id="anexos">
@foreach($filas as $i => $f)
<div class="form-row anexo-fila border rounded p-2 mb-2"><input type="hidden" name="anexos[{{ $i }}][id]" value="{{ $f['id'] ?? '' }}">
<div class="form-group col-md-5 mb-1"><label for="a-n-{{ $i }}">Documento</label><input class="form-control" id="a-n-{{ $i }}" name="anexos[{{ $i }}][nombre]" maxlength="190" value="{{ $f['nombre'] ?? '' }}"></div>
<div class="form-group col-md-5 mb-1"><label for="a-d-{{ $i }}">Ayuda para el ciudadano (opcional)</label><input class="form-control" id="a-d-{{ $i }}" name="anexos[{{ $i }}][descripcion]" maxlength="255" value="{{ $f['descripcion'] ?? '' }}"></div>
<div class="form-group col-md-2 mb-1 pt-4"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="a-o-{{ $i }}" name="anexos[{{ $i }}][obligatorio]" value="1" @checked(!empty($f['obligatorio']))><label class="custom-control-label" for="a-o-{{ $i }}">Obligatorio</label></div></div></div>
@endforeach
</div>
<button type="button" class="btn btn-sm btn-outline-secondary" id="agregar-anexo">Agregar otro documento</button>
<p class="small text-muted mt-2">Para quitar un documento, deje su nombre en blanco.</p>
</fieldset>
<button class="btn btn-primary">Guardar{{ $tramite ? ' cambios' : ' como borrador' }}</button> <a class="btn btn-link" href="{{ route('app.tramites.index') }}">Volver</a>
</fieldset>
</form>
@if($tramite)
<hr><h2 class="h5">Estado</h2>
<div class="d-flex flex-wrap">
@if(\App\Services\TramiteService::puedePublicar($u,$tramite) && $tramite->estado!=='publicado')<form method="post" action="{{ route('app.tramites.publicar',$tramite->id) }}" class="mr-2" data-confirm="¿Publicar «{{ $tramite->nombre }}»? Quedará disponible para la ciudadanía.">@csrf<button class="btn btn-success">{{ $tramite->estado==='deshabilitado' ? 'Reactivar y publicar' : 'Publicar' }}</button></form>@endif
@if($tramite->estado==='publicado' && \App\Services\TramiteService::puedeGestionar($u,$tramite))<form method="post" action="{{ route('app.tramites.deshabilitar',$tramite->id) }}" class="mr-2" data-confirm="¿Deshabilitar «{{ $tramite->nombre }}»? Dejará de estar disponible para nuevas solicitudes.">@csrf<button class="btn btn-outline-danger">Deshabilitar</button></form>@endif
<form method="post" action="{{ route('app.tramites.duplicar',$tramite->id) }}" data-confirm="¿Crear una copia como borrador?">@csrf<button class="btn btn-outline-secondary">Duplicar como borrador</button></form>
</div>
@endif
@push('scripts')<script src="{{ asset('assets/js/tramites.js') }}"></script>@endpush
@endsection
