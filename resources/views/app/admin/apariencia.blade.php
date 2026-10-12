@extends('layouts.app')
@section('titulo', 'Apariencia')
@section('contenido')
<h1>Apariencia: logo, colores, encabezado y pie</h1>
<p>Los cambios se aplican a todo el sistema. Para proteger la accesibilidad, cada par de colores texto/fondo debe tener un contraste de al menos <strong>4,5:1</strong>; si no se cumple, el sistema no guarda.</p>

<h2 class="h4">Logo</h2>
<div class="card card-body mb-4">
  @if($tieneLogo)<p><img src="{{ route('marca.logo',['v'=>substr($a['logo']['sha256'],0,10)]) }}" alt="Logo actual" width="{{ (int) round(48*$a['logo']['ancho']/max(1,$a['logo']['alto'])) }}" height="48"> <small>{{ $a['logo']['ancho'] }}×{{ $a['logo']['alto'] }} px · {{ $a['logo']['mime'] }}</small></p>@else<p>No hay logo cargado.</p>@endif
  <form method="post" action="{{ route('app.admin.apariencia.logo') }}" enctype="multipart/form-data" class="form-inline">@csrf
    <label class="mr-2" for="logo">Subir logo (PNG, JPG o WebP, hasta 1 MB)</label><input class="form-control-file mr-2" type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" required aria-describedby="logo-ayuda"><button class="btn btn-primary">Subir</button></form>
  <small id="logo-ayuda" class="form-text text-muted">Se re-codifica en el servidor (se eliminan metadatos) y se reduce a 600 px de ancho. No se aceptan SVG.</small>
  @if($tieneLogo)<form method="post" action="{{ route('app.admin.apariencia.logo.quitar') }}" class="mt-2" data-confirm="¿Quitar el logo?">@csrf<button class="btn btn-sm btn-outline-secondary">Quitar logo</button></form>@endif
</div>

<form method="post" action="{{ route('app.admin.apariencia.guardar') }}" class="col-lg-10 px-0">@csrf
<h2 class="h4">Encabezado</h2>
<div class="form-row">
<div class="form-group col-md-6"><label for="h_titulo">Título</label><input class="form-control" id="h_titulo" name="header[titulo]" value="{{ old('header.titulo',$a['header']['titulo']) }}" required maxlength="120"></div>
<div class="form-group col-md-6"><label for="h_sub">Subtítulo</label><input class="form-control" id="h_sub" name="header[subtitulo]" value="{{ old('header.subtitulo',$a['header']['subtitulo']) }}" maxlength="120"></div></div>
<div class="form-row">
<div class="col-md-4"><div class="custom-control custom-checkbox mt-4"><input class="custom-control-input" type="checkbox" id="h_logo" name="header[mostrar_logo]" value="1" @checked(old('header.mostrar_logo',$a['header']['mostrar_logo']))><label class="custom-control-label" for="h_logo">Mostrar el logo</label></div></div>
<div class="form-group col-md-5"><label for="h_alt">Texto alternativo del logo</label><input class="form-control" id="h_alt" name="header[logo_alt]" value="{{ old('header.logo_alt',$a['header']['logo_alt']) }}" maxlength="150" aria-describedby="alt-ayuda"><small id="alt-ayuda" class="form-text text-muted">Obligatorio si se muestra (lectores de pantalla).</small></div>
<div class="form-group col-md-3"><label for="h_alto">Alto del logo (24–120 px)</label><input class="form-control" type="number" id="h_alto" name="header[logo_alto_px]" min="24" max="120" value="{{ old('header.logo_alto_px',$a['header']['logo_alto_px']) }}"></div></div>

<h2 class="h4">Colores</h2>
<p class="small text-muted">Formato #RRGGBB. Se verifica el contraste de cada par al guardar.</p>
<div class="form-row">
@foreach(['primario'=>'Primario (botones)','titulo'=>'Títulos','header_fondo'=>'Encabezado: fondo','header_texto'=>'Encabezado: texto','nav_fondo'=>'Menú: fondo','nav_texto'=>'Menú: texto','footer_fondo'=>'Pie: fondo','footer_texto'=>'Pie: texto','footer_enlace'=>'Pie: enlaces'] as $k=>$l)
<div class="form-group col-md-4"><label for="c_{{ $k }}">{{ $l }}</label><input class="form-control" id="c_{{ $k }}" name="colores[{{ $k }}]" value="{{ old('colores.'.$k,$a['colores'][$k]) }}" required maxlength="7" pattern="#[0-9a-fA-F]{6}"></div>
@endforeach</div>

<h2 class="h4">Pie de página</h2>
<div class="form-group"><label for="f_texto">Texto</label><input class="form-control" id="f_texto" name="footer[texto]" value="{{ old('footer.texto',$a['footer']['texto']) }}" maxlength="300"></div>
<div class="form-group"><label for="f_legal">Texto legal (opcional)</label><textarea class="form-control" id="f_legal" name="footer[texto_legal]" rows="2" maxlength="400">{{ old('footer.texto_legal',$a['footer']['texto_legal']) }}</textarea></div>
<fieldset class="mb-3"><legend class="h6">Enlaces del pie (hasta 8; vacío = enlaces por defecto)</legend>
@php $en = old('footer.enlaces',$a['footer']['enlaces']); @endphp
@for($i=0;$i<8;$i++)
<div class="form-row"><div class="form-group col-md-4"><label for="fe_{{ $i }}">Etiqueta {{ $i+1 }}</label><input class="form-control" id="fe_{{ $i }}" name="footer[enlaces][{{ $i }}][etiqueta]" value="{{ $en[$i]['etiqueta'] ?? '' }}" maxlength="60"></div>
<div class="form-group col-md-8"><label for="fu_{{ $i }}">Dirección {{ $i+1 }} (/ruta interna o https://…)</label><input class="form-control" id="fu_{{ $i }}" name="footer[enlaces][{{ $i }}][url]" value="{{ $en[$i]['url'] ?? '' }}" maxlength="255"></div></div>
@endfor</fieldset>
<button class="btn btn-primary">Guardar apariencia</button>
</form>
<form method="post" action="{{ route('app.admin.apariencia.restablecer') }}" class="mt-3" data-confirm="¿Restablecer colores, encabezado y pie a los valores originales?">@csrf<button class="btn btn-outline-secondary">Restablecer valores originales</button></form>
@endsection
