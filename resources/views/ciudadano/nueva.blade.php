@extends('layouts.app')
@section('titulo', 'Nueva solicitud')
@section('contenido')
@include('partials.tramite_ficha', ['tramite' => $tramite])
<form method="post" action="{{ route('ciudadano.store') }}" enctype="multipart/form-data" class="col-lg-8 px-0" data-pasos>
@csrf
<input type="hidden" name="tramite_id" value="{{ $tramite->id }}">
<div data-paso="Sus datos y solicitud">
<div class="form-row">
  <div class="form-group col-md-4"><label for="run">RUN</label><input class="form-control" id="run" value="{{ $c['run'] }}" readonly aria-describedby="ayuda-cu"></div>
  <div class="form-group col-md-4"><label for="nombres">Nombres</label><input class="form-control" id="nombres" value="{{ $c['nombres'] }}" readonly></div>
  <div class="form-group col-md-4"><label for="apellidos">Apellidos</label><input class="form-control" id="apellidos" value="{{ $c['apellidos'] }}" readonly></div>
</div>
<small id="ayuda-cu" class="form-text text-muted mb-3">Datos obtenidos de ClaveÚnica; no se pueden modificar.</small>
<div class="form-group"><label for="correo">Correo electrónico</label><input class="form-control" type="email" id="correo" name="correo" value="{{ old('correo') }}" required maxlength="190"></div>
<div class="form-group"><label for="detalle">Detalle de su solicitud</label><textarea class="form-control" id="detalle" name="detalle" rows="6" required minlength="10" maxlength="4000">{{ old('detalle') }}</textarea></div>
</div>
<fieldset class="form-group" data-paso="Documentos"><legend class="h6">Anexos</legend>
<p class="small text-muted">Solo PDF, JPG, PNG o TIFF. Máximo 10 MB por archivo.</p>
@foreach($tramite->anexos as $a)
<div class="form-group"><label for="anexo_{{ $a->id }}">{{ $a->nombre }} @if($a->obligatorio)<span class="text-danger">(obligatorio)</span>@else(opcional)@endif</label>@if($a->descripcion)<small class="form-text text-muted" id="ay-{{ $a->id }}">{{ $a->descripcion }}</small>@endif<input class="form-control-file" type="file" id="anexo_{{ $a->id }}" name="anexo_{{ $a->id }}" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" @required($a->obligatorio)></div>
@endforeach
<div class="form-group"><label for="extras">Otros anexos (opcional)</label><input class="form-control-file" type="file" id="extras" name="extras[]" multiple accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff"></div>
</fieldset>
<fieldset class="form-group" data-paso="Notificación"><legend class="h6">¿Cómo desea ser notificado(a)?</legend>
@foreach(['correo'=>'Correo electrónico','casilla_unica'=>'Casilla Única','presencial'=>'Presencial (retiro en oficina)'] as $v=>$l)
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="canal-{{ $v }}" name="canal_notificacion" value="{{ $v }}" @checked(old('canal_notificacion','correo')===$v) required><label class="custom-control-label" for="canal-{{ $v }}">{{ $l }}</label></div>
@endforeach</fieldset>
<button class="btn btn-primary">Enviar solicitud</button>
</form>
@endsection
