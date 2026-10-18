@extends('layouts.app')
@section('titulo', $a ? 'Editar anuncio' : 'Nuevo anuncio')
@section('contenido')
<h1>{{ $a ? 'Editar anuncio' : 'Nuevo anuncio' }}</h1>
<form method="post" enctype="multipart/form-data" action="{{ $a ? route('app.anuncios.actualizar', $a) : route('app.anuncios.crear') }}" class="mb-4">@csrf
 <div class="form-group"><label for="titulo">Título (obligatorio)</label><input class="form-control" id="titulo" name="titulo" maxlength="160" required value="{{ old('titulo', $a?->titulo) }}"></div>
 <div class="form-group"><label for="descripcion">Descripción</label><textarea class="form-control" id="descripcion" name="descripcion" rows="4" maxlength="1500" aria-describedby="d-ayuda">{{ old('descripcion', $a?->descripcion) }}</textarea><small id="d-ayuda" class="form-text text-muted">Texto simple, hasta 1.500 caracteres. No admite HTML.</small></div>
 <div class="form-group"><label for="audiencia">Dirigido a</label><select class="form-control" id="audiencia" name="audiencia">@foreach($audiencias as $k=>$v)<option value="{{ $k }}" @selected(old('audiencia', $a?->audiencia ?? 'vecinos')===$k)>{{ $v }}</option>@endforeach</select></div>
 <fieldset class="card card-body mb-3"><legend class="h5">Imagen o video</legend>
  @if($a?->medio_tipo)<div class="mb-2">@if($a->medio_tipo==='imagen')<img src="{{ route('anuncios.medio', $a) }}" alt="{{ $a->medio_alt }}" style="max-width:240px;height:auto">@else<video src="{{ route('anuncios.medio', $a) }}" controls preload="none" style="max-width:320px" aria-label="{{ $a->medio_alt }}"></video>@endif
  <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="quitar_medio" name="quitar_medio" value="1"><label class="form-check-label" for="quitar_medio">Quitar el medio actual</label></div></div>@endif
  <div class="form-group"><label for="medio">{{ $a?->medio_tipo ? 'Reemplazar por' : 'Archivo' }} (imagen PNG o JPG hasta 2 MB; video MP4 o WebM hasta 20 MB)</label><input class="form-control-file" type="file" id="medio" name="medio" accept="image/png,image/jpeg,video/mp4,video/webm" aria-describedby="m-ayuda"><small id="m-ayuda" class="form-text text-muted">El tamaño real permitido también depende del servidor (<code>upload_max_filesize</code>).</small></div>
  <div class="form-group mb-0"><label for="medio_alt">Descripción de la imagen o del video (texto alternativo, obligatorio si hay medio)</label><input class="form-control" id="medio_alt" name="medio_alt" maxlength="200" value="{{ old('medio_alt', $a?->medio_alt) }}"></div></fieldset>
 <fieldset class="card card-body mb-3"><legend class="h5">Enlace (opcional)</legend><div class="form-row"><div class="col-md-8 form-group"><label for="enlace_url">Dirección (https://… o ruta interna que comience con /)</label><input class="form-control" id="enlace_url" name="enlace_url" maxlength="300" value="{{ old('enlace_url', $a?->enlace_url) }}"></div>
  <div class="col-md-4 form-group"><label for="enlace_texto">Texto del botón</label><input class="form-control" id="enlace_texto" name="enlace_texto" maxlength="60" value="{{ old('enlace_texto', $a?->enlace_texto) }}"></div></div></fieldset>
 <fieldset class="card card-body mb-3"><legend class="h5">Vigencia</legend><div class="form-row">
  <div class="col-md-4 form-group"><label for="inicia_en">Desde (vacío = de inmediato)</label><input class="form-control" type="date" id="inicia_en" name="inicia_en" value="{{ old('inicia_en', $a?->inicia_en ? substr(\Illuminate\Support\Carbon::parse($a->inicia_en)->setTimezone('America/Santiago')->format('Y-m-d'), 0, 10) : '') }}"></div>
  <div class="col-md-4 form-group"><label for="termina_en">Hasta (vacío = sin término)</label><input class="form-control" type="date" id="termina_en" name="termina_en" value="{{ old('termina_en', $a?->termina_en ? \Illuminate\Support\Carbon::parse($a->termina_en)->setTimezone('America/Santiago')->format('Y-m-d') : '') }}"></div>
  <div class="col-md-4 form-group"><label for="orden">Orden (menor primero)</label><input class="form-control" type="number" min="1" max="999" id="orden" name="orden" value="{{ old('orden', $a?->orden ?? 10) }}"></div></div>
  <div class="form-check"><input type="hidden" name="activo" value="0"><input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" @checked(old('activo', $a?->activo ?? true))><label class="form-check-label" for="activo">Anuncio activo</label></div></fieldset>
 <button class="btn btn-primary">Guardar anuncio</button> <a class="btn btn-link" href="{{ route('app.anuncios') }}">Cancelar</a>
</form>
@endsection
