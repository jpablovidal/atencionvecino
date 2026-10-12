@extends('layouts.app')
@section('titulo', 'Plantilla')
@section('contenido')
@php $alc = old('alcance', $p->alcance ?? 'todas'); $marcadas = old('unidades', $sel); @endphp
<h1>Plantilla: {{ $p ? 'editar' : 'nueva' }}</h1>
@if($p)<p class="text-muted">Versión {{ $p->version }} · <a href="{{ route('app.admin.plantillas.versiones', $p) }}">Historial de versiones</a> · <a href="{{ route('app.formato', $p) }}">Vista previa</a></p>@endif
<form method="post" action="{{ $p ? route('app.admin.plantillas.actualizar', $p) : route('app.admin.plantillas.guardar') }}" class="px-0">@csrf
  <div class="form-row">
    <div class="form-group col-md-6"><label for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" maxlength="150" required value="{{ old('nombre', $p->nombre ?? '') }}"></div>
    <div class="form-group col-md-6"><label for="tipo_documento_id">Tipo de documento</label><select class="form-control" id="tipo_documento_id" name="tipo_documento_id" required>@foreach($tipos as $t)<option value="{{ $t->id }}" @selected((string) old('tipo_documento_id', $p->tipo_documento_id ?? '') === (string) $t->id)>{{ $t->nombre }}</option>@endforeach</select></div>
  </div>
  <div class="form-group"><label for="descripcion">Descripción (se muestra en el catálogo)</label><input class="form-control" id="descripcion" name="descripcion" maxlength="255" value="{{ old('descripcion', $p->descripcion ?? '') }}"></div>
  <fieldset class="form-group"><legend class="h6">¿Quién puede ver y usar este formato?</legend>
    <div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="alc_todas" name="alcance" value="todas" @checked($alc === 'todas')><label class="custom-control-label" for="alc_todas">Toda la municipalidad</label></div>
    <div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="alc_unidades" name="alcance" value="unidades" @checked($alc === 'unidades')><label class="custom-control-label" for="alc_unidades">Solo las direcciones o unidades que marque (p. ej., Salud, Educación)</label></div>
    <div class="ml-4 mt-2" role="group" aria-label="Unidades con acceso">@foreach($unidades as $u)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="u{{ $u->id }}" name="unidades[]" value="{{ $u->id }}" @checked(in_array($u->id, array_map('intval', $marcadas), true))><label class="custom-control-label" for="u{{ $u->id }}">{{ $u->nombre }}</label></div>@endforeach</div>
  </fieldset>
  <div class="form-group"><label for="contenido_html">Contenido</label>
    <p id="ayuda-editor" class="small text-muted">Use el botón <strong>Variables</strong> para insertar campos de combinación. Las imágenes se suben desde el botón de imagen (PNG, JPG o WebP, hasta 2 MB) o arrastrándolas al editor. Atajo de ayuda: Alt+0.</p>
    <textarea id="contenido_html" name="contenido_html" data-editor data-height="560" data-upload-url="{{ route('app.recursos.subir') }}" data-variables="{{ json_encode(collect($variables)->map(fn ($v) => $v[0])->all(), JSON_UNESCAPED_UNICODE) }}" aria-describedby="ayuda-editor" rows="18">{{ old('contenido_html', $contenido) }}</textarea></div>
  <button class="btn btn-primary">Guardar</button> <a class="btn btn-link" href="{{ route('app.admin.plantillas') }}">Cancelar</a>
</form>
@endsection
@push('scripts')<script src="{{ asset('assets/tinymce/tinymce.min.js') }}"></script>@endpush
