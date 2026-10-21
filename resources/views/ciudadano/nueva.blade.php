@extends('layouts.app')
@section('titulo', 'Nueva solicitud')
@section('contenido')
<nav aria-label="Usted está en" class="portal-migas"><span class="portal-migas-et">Usted está en:</span><ol><li><a href="{{ route('inicio') }}">Inicio</a></li><li><a href="{{ route('ciudadano.index') }}">Mis solicitudes</a></li><li><a href="{{ route('catalogo.ficha', [$tramite->id, \Illuminate\Support\Str::slug($tramite->nombre)]) }}">{{ \Illuminate\Support\Str::limit($tramite->nombre, 40) }}</a></li><li aria-current="page">Realizar trámite</li></ol></nav>
@include('partials.tramite_ficha', ['tramite' => $tramite])
<h2 class="h4 mt-4" id="completar">Complete su solicitud</h2>
<p class="small text-muted">Son 3 pasos: sus datos, los documentos y cómo desea ser notificado(a). Al final podrá revisar todo antes de enviar.</p>
<form method="post" action="{{ route('ciudadano.store') }}" enctype="multipart/form-data" class="portal-formulario" data-pasos aria-labelledby="completar">
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
<div class="form-group"><label for="detalle">Detalle de su solicitud</label><textarea class="form-control" id="detalle" name="detalle" rows="6" required minlength="10" maxlength="{{ \App\Http\Controllers\CiudadanoController::MAX_DETALLE }}" data-contador="{{ \App\Http\Controllers\CiudadanoController::MAX_DETALLE }}" aria-describedby="detalle-cont">{{ old('detalle') }}</textarea>
<small id="detalle-cont" class="form-text text-muted" data-contador-texto>Máximo {{ number_format(\App\Http\Controllers\CiudadanoController::MAX_DETALLE, 0, ',', '.') }} caracteres.</small></div>
</div>
<fieldset class="form-group" data-paso="Documentos"><legend class="h6">Anexos</legend>
<p class="small text-muted">Formatos admitidos: {{ \App\Support\UploadValidator::descripcionFormatos() }}. Máximo {{ round(\App\Services\Admin\ArchivoConfigService::maxBytes('ciudadano') / 1048576) }} MB por archivo.</p>
@foreach($tramite->anexos as $a)
<div class="form-group"><label for="anexo_{{ $a->id }}">{{ $a->nombre }} @if($a->obligatorio)<span class="text-danger">(obligatorio)</span>@else(opcional)@endif</label>@if($a->descripcion)<small class="form-text text-muted" id="ay-{{ $a->id }}">{{ $a->descripcion }}</small>@endif<input class="form-control-file" type="file" id="anexo_{{ $a->id }}" name="anexo_{{ $a->id }}" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" data-anexo="{{ $a->nombre }}" data-max="{{ \App\Services\Admin\ArchivoConfigService::maxBytes('ciudadano') }}" @required($a->obligatorio)></div>
@endforeach
<div class="form-group"><label for="extras">Otros anexos (opcional)</label><input class="form-control-file" type="file" id="extras" name="extras[]" multiple data-anexo="Otros anexos" data-extras data-max="{{ \App\Services\Admin\ArchivoConfigService::maxBytes('ciudadano') }}"></div>
</fieldset>
<fieldset class="form-group" data-paso="Notificación"><legend class="h6">¿Cómo desea ser notificado(a)?</legend>
@foreach(['correo'=>'Correo electrónico','casilla_unica'=>'Casilla Única','presencial'=>'Presencial (retiro en oficina)'] as $v=>$l)
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="canal-{{ $v }}" name="canal_notificacion" value="{{ $v }}" @checked(old('canal_notificacion','correo')===$v) required><label class="custom-control-label" for="canal-{{ $v }}">{{ $l }}</label></div>
@endforeach</fieldset>
<fieldset class="form-group sgd-consentimiento" id="consentimiento"><legend class="h6">Tratamiento de sus datos personales</legend>
<p class="small mb-2">Conforme a la Ley N° 19.628 y sus modificaciones, la I. Municipalidad de Santo Domingo le informa que los datos personales recabados en este formulario (RUN, nombres, apellidos, correo electrónico, detalle y documentos adjuntos) serán tratados <strong>exclusivamente para tramitar esta solicitud</strong>. Sus datos no serán cedidos a terceros con fines de publicidad. Usted puede ejercer sus derechos de acceso, rectificación, supresión u oposición en www.santodomingo.cl o en la oficina de partes. Más información en la <a href="{{ route('privacidad') }}" target="_blank" rel="noopener noreferrer">Política de Privacidad (Decreto Alcaldicio N° 2590)<span class="sr-only"> (se abre en otra pestaña)</span></a>.</p>
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="consent-si" name="consentimiento" value="si" @checked(old('consentimiento')==='si') required><label class="custom-control-label" for="consent-si">Sí, consiento el tratamiento de mis datos personales para tramitar esta solicitud.</label></div>
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="consent-no" name="consentimiento" value="no" @checked(old('consentimiento')==='no')><label class="custom-control-label" for="consent-no">No consiento.</label></div>
<p class="small text-danger mt-2 mb-0" id="consent-aviso" role="status" hidden>Sin su consentimiento no es posible ingresar la solicitud por este medio. Puede presentarla en la oficina de partes de la Municipalidad.</p>
</fieldset>
<button class="btn btn-primary" id="enviar-solicitud">Enviar solicitud</button> <a class="btn btn-link" href="{{ route('catalogo.ficha', [$tramite->id, \Illuminate\Support\Str::slug($tramite->nombre)]) }}">Volver al trámite</a>
</form>
@include('partials.visor_modal')
@push('scripts')<script src="{{ asset('assets/js/solicitud-form.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/solicitud-form.js')), 0, 8) }}" defer></script>@endpush
@endsection
