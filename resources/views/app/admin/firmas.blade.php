@extends('layouts.app')
@section('titulo', 'Estilo y posición de firmas')
@section('contenido')
<h1>Estilo y posición de firmas</h1>
<p>Define cómo se ve el <strong>sello de firma</strong> en la <strong>copia pública</strong> de cada documento y dónde se ubica (por tipo de documento y orden de firma). Página carta (215 × 279 mm). La apariencia de la firma electrónica avanzada dentro del PDF firmado por FirmaGob la define FirmaGob.</p>

<h2 class="h4">Vista previa</h2>
<form method="get" class="form-inline mb-2"><label class="mr-2" for="prev_tipo">Tipo de documento</label>
<select class="form-control mr-2" id="prev_tipo" name="tipo"><option value="">Configuración general</option>@foreach($tipos as $t)<option value="{{ $t->id }}" @selected($tipoPrev===$t->id)>{{ $t->nombre }}</option>@endforeach</select>
<button class="btn btn-outline-primary mr-2">Actualizar vista previa</button>
<a class="btn btn-outline-secondary" href="{{ route('app.admin.firmas.prueba',['tipo'=>$tipoPrev]) }}">Descargar PDF de prueba</a></form>
<div class="hoja-wrap mb-4" role="img" aria-label="Vista previa de la hoja con los sellos de firma en sus posiciones"><div class="hoja-previa">
{{-- HTML generado por las plantillas Blade del sello (datos escapados): seguro de imprimir sin filtrar --}}
{!! $muestra['inicio'] !!}
<div class="lineas">@for($i=0;$i<10;$i++)<p>Texto del documento. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>@endfor</div>
{!! $muestra['final'] !!}
</div></div>

<h2 class="h4">Estilos</h2>
<table class="table table-sm"><caption class="sr-only">Estilos de firma</caption><thead><tr><th scope="col">Estilo</th><th scope="col">Colores</th><th scope="col">Tamaño</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($estilos as $e)<tr><td>{{ $e->nombre }} @if($e->defecto)<span class="estado estado-vigente">por defecto</span>@endif</td><td><code>{{ $e->color_borde }}</code> / <code>{{ $e->color_fondo }}</code> / <code>{{ $e->color_texto }}</code></td><td>{{ $e->ancho_mm }}×{{ $e->alto_mm }} mm · {{ $e->tamano_fuente }} pt</td><td>{{ $e->activo ? 'activo' : 'inactivo' }}</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.firmas',['editar_estilo'=>$e->id]) }}#form-estilo">Editar</a>
@unless($e->defecto)@if($e->activo)<form class="d-inline" method="post" action="{{ route('app.admin.firmas.estilo.defecto',$e) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Hacer por defecto</button></form>@endif
<form class="d-inline" method="post" action="{{ route('app.admin.firmas.estilo.activo',$e) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $e->activo ? 'Desactivar' : 'Activar' }}</button></form>@endunless</td></tr>@endforeach</tbody></table>

@php $x = $editE; @endphp
<form id="form-estilo" method="post" action="{{ route('app.admin.firmas.estilo.guardar',$x ? [$x] : []) }}" class="card card-body mb-4">@csrf
<h3 class="h5">{{ $x ? 'Editar estilo: '.$x->nombre : 'Nuevo estilo' }}</h3>
<div class="form-row">
<div class="form-group col-md-4"><label for="e_nombre">Nombre</label><input class="form-control" id="e_nombre" name="nombre" value="{{ old('nombre',$x->nombre ?? '') }}" required maxlength="80"></div>
<div class="form-group col-md-8"><label for="e_texto">Texto de encabezado</label><input class="form-control" id="e_texto" name="texto_encabezado" value="{{ old('texto_encabezado',$x->texto_encabezado ?? 'Firmado electrónicamente por') }}" required maxlength="80"></div></div>
<div class="form-row">
<div class="form-group col-md-3"><label for="e_borde">Color del borde</label><input class="form-control" id="e_borde" name="color_borde" value="{{ old('color_borde',$x->color_borde ?? '#006fb3') }}" required maxlength="7" pattern="#[0-9a-fA-F]{6}" aria-describedby="e-col"></div>
<div class="form-group col-md-3"><label for="e_fondo">Color de fondo</label><input class="form-control" id="e_fondo" name="color_fondo" value="{{ old('color_fondo',$x->color_fondo ?? '#ffffff') }}" required maxlength="7" pattern="#[0-9a-fA-F]{6}"></div>
<div class="form-group col-md-3"><label for="e_texto_c">Color del texto</label><input class="form-control" id="e_texto_c" name="color_texto" value="{{ old('color_texto',$x->color_texto ?? '#111111') }}" required maxlength="7" pattern="#[0-9a-fA-F]{6}"></div>
<div class="form-group col-md-3"><label for="e_bs">Estilo del borde</label><select class="form-control" id="e_bs" name="borde_estilo">@foreach(['solid'=>'Continuo','dashed'=>'Guiones','dotted'=>'Puntos','none'=>'Sin borde'] as $v=>$l)<option value="{{ $v }}" @selected(old('borde_estilo',$x->borde_estilo ?? 'solid')===$v)>{{ $l }}</option>@endforeach</select></div></div>
<small id="e-col" class="form-text text-muted mb-2">Formato #RRGGBB. El texto debe tener contraste de al menos 4,5:1 sobre el fondo (se verifica al guardar).</small>
<div class="form-row">
<div class="form-group col-md-3"><label for="e_fs">Tamaño de letra (6–14 pt)</label><input class="form-control" type="number" id="e_fs" name="tamano_fuente" min="6" max="14" value="{{ old('tamano_fuente',$x->tamano_fuente ?? 8) }}" required></div>
<div class="form-group col-md-3"><label for="e_an">Ancho (40–120 mm)</label><input class="form-control" type="number" id="e_an" name="ancho_mm" min="40" max="120" value="{{ old('ancho_mm',$x->ancho_mm ?? 70) }}" required></div>
<div class="form-group col-md-3"><label for="e_al">Alto mínimo (15–60 mm)</label><input class="form-control" type="number" id="e_al" name="alto_mm" min="15" max="60" value="{{ old('alto_mm',$x->alto_mm ?? 28) }}" required></div>
<div class="form-group col-md-3"><label for="e_ta">Alineación del texto</label><select class="form-control" id="e_ta" name="alineacion_texto"><option value="left" @selected(old('alineacion_texto',$x->alineacion_texto ?? 'left')==='left')>Izquierda</option><option value="center" @selected(old('alineacion_texto',$x->alineacion_texto ?? '')==='center')>Centrada</option></select></div></div>
<fieldset class="mb-3"><legend class="h6">Datos que muestra el sello</legend>
@foreach(['mostrar_nombre'=>'Nombre del firmante','mostrar_cargo'=>'Cargo','mostrar_run'=>'RUN (enmascarado)','mostrar_fecha'=>'Fecha y hora de firma','mostrar_codigo'=>'Código de validación'] as $k=>$l)
<div class="custom-control custom-checkbox custom-control-inline"><input class="custom-control-input" type="checkbox" id="e_{{ $k }}" name="{{ $k }}" value="1" @checked(old($k,$x ? $x->$k : in_array($k,['mostrar_nombre','mostrar_cargo','mostrar_fecha','mostrar_codigo'])))><label class="custom-control-label" for="e_{{ $k }}">{{ $l }}</label></div>@endforeach</fieldset>
<div><button class="btn btn-primary">Guardar estilo</button> @if($x)<a class="btn btn-link" href="{{ route('app.admin.firmas') }}">Cancelar</a>@endif</div></form>

<h2 class="h4">Posiciones</h2>
<p class="small text-muted">Una posición activa por tipo de documento y orden de firma. Si un tipo no tiene posición propia se usa la «general».</p>
<table class="table table-sm"><caption class="sr-only">Posiciones de firma</caption><thead><tr><th scope="col">Nombre</th><th scope="col">Tipo</th><th scope="col">Firma n.º</th><th scope="col">Ubicación</th><th scope="col">Estilo</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($posiciones as $p)<tr><td>{{ $p->nombre }}</td><td>{{ $p->tipo?->nombre ?? 'General' }}</td><td>{{ $p->orden_firma }}</td><td>@if($p->pagina==='primera')Primera página: X {{ $p->x_mm }} mm, Y {{ $p->y_mm }} mm @else Al final del texto, {{ $p->alineacion }}, margen {{ $p->margen_superior_mm }} mm @endif</td><td>{{ $p->estilo?->nombre ?? 'por defecto' }}</td><td>{{ $p->activo ? 'activa' : 'inactiva' }}</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.firmas',['editar_posicion'=>$p->id]) }}#form-posicion">Editar</a>
<form class="d-inline" method="post" action="{{ route('app.admin.firmas.posicion.activo',$p) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $p->activo ? 'Desactivar' : 'Activar' }}</button></form></td></tr>@endforeach</tbody></table>

@php $y = $editP; @endphp
<form id="form-posicion" method="post" action="{{ route('app.admin.firmas.posicion.guardar',$y ? [$y] : []) }}" class="card card-body mb-4">@csrf
<h3 class="h5">{{ $y ? 'Editar posición: '.$y->nombre : 'Nueva posición' }}</h3>
<div class="form-row">
<div class="form-group col-md-4"><label for="p_nombre">Nombre</label><input class="form-control" id="p_nombre" name="nombre" value="{{ old('nombre',$y->nombre ?? '') }}" required maxlength="80"></div>
<div class="form-group col-md-4"><label for="p_tipo">Tipo de documento</label><select class="form-control" id="p_tipo" name="tipo_documento_id"><option value="">General (todos los tipos)</option>@foreach($tipos as $t)<option value="{{ $t->id }}" @selected((int) old('tipo_documento_id',$y->tipo_documento_id ?? 0)===$t->id)>{{ $t->nombre }}</option>@endforeach</select></div>
<div class="form-group col-md-2"><label for="p_orden">Firma n.º</label><select class="form-control" id="p_orden" name="orden_firma">@foreach([1,2,3] as $n)<option value="{{ $n }}" @selected((int) old('orden_firma',$y->orden_firma ?? 1)===$n)>{{ $n }}.ª</option>@endforeach</select></div>
<div class="form-group col-md-2"><label for="p_estilo">Estilo</label><select class="form-control" id="p_estilo" name="estilo_id"><option value="">Por defecto</option>@foreach($estilos->where('activo',true) as $e)<option value="{{ $e->id }}" @selected((int) old('estilo_id',$y->estilo_id ?? 0)===$e->id)>{{ $e->nombre }}</option>@endforeach</select></div></div>
<div class="form-row">
<div class="form-group col-md-3"><label for="p_pagina">Página</label><select class="form-control" id="p_pagina" name="pagina" aria-describedby="p-ayuda"><option value="ultima" @selected(old('pagina',$y->pagina ?? 'ultima')==='ultima')>Al final del texto</option><option value="primera" @selected(old('pagina',$y->pagina ?? '')==='primera')>Primera página (coordenadas)</option></select></div>
<div class="form-group col-md-3"><label for="p_al">Alineación (al final del texto)</label><select class="form-control" id="p_al" name="alineacion">@foreach(['izquierda','centro','derecha'] as $a)<option value="{{ $a }}" @selected(old('alineacion',$y->alineacion ?? 'izquierda')===$a)>{{ ucfirst($a) }}</option>@endforeach</select></div>
<div class="form-group col-md-2"><label for="p_mg">Margen superior (mm)</label><input class="form-control" type="number" id="p_mg" name="margen_superior_mm" min="0" max="60" value="{{ old('margen_superior_mm',$y->margen_superior_mm ?? 12) }}" required></div>
<div class="form-group col-md-2"><label for="p_x">X (mm)</label><input class="form-control" type="number" id="p_x" name="x_mm" min="0" max="215" value="{{ old('x_mm',$y->x_mm ?? '') }}"></div>
<div class="form-group col-md-2"><label for="p_y">Y (mm)</label><input class="form-control" type="number" id="p_y" name="y_mm" min="0" max="279" value="{{ old('y_mm',$y->y_mm ?? '') }}"></div></div>
<small id="p-ayuda" class="form-text text-muted mb-2">«Al final del texto» sigue el flujo del documento (no se parte entre páginas). «Primera página» ubica el sello por coordenadas medidas desde la esquina superior izquierda; X e Y son obligatorios y el sello debe caber en la hoja.</small>
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="p_activo" name="activo" value="1" @checked(old('activo',$y ? $y->activo : true))><label class="custom-control-label" for="p_activo">Activa</label></div>
<div><button class="btn btn-primary">Guardar posición</button> @if($y)<a class="btn btn-link" href="{{ route('app.admin.firmas') }}">Cancelar</a>@endif</div></form>
@endsection
