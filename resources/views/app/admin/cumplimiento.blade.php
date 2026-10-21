@extends('layouts.app')
@section('titulo', 'Cumplimiento normativo')
@section('contenido')
@php
  $estados = \App\Support\NormasTecnicas::ESTADOS; $decretos = \App\Support\NormasTecnicas::decretos();
  $filtro = request('estado'); $filtroD = request('decreto');
  $matriz = collect(\App\Support\NormasTecnicas::matriz())->when($filtro && isset($estados[$filtro]), fn ($c) => $c->where('estado', $filtro))->when($filtroD, fn ($c) => $c->where('decreto', (int) $filtroD));
  $clase = ['implementado' => 'estado-vigente', 'parcial' => 'estado-pendiente', 'pendiente' => 'estado-fallida', 'institucional' => '', 'externo' => ''];
@endphp
<h1>Cumplimiento normativo</h1>
<p class="lead-sub">Normas técnicas de la Ley 21.180 (Transformación Digital del Estado): qué hace el sistema, qué falta y qué debe hacer el municipio. Los decretos 8 a 12 se revisaron en su texto oficial; las guías técnicas que ellos ordenan dictar no se han entregado, por lo que los nombres exactos de metadatos, formatos y contratos de API siguen <strong>A VALIDAR</strong>.</p>
<div class="alert alert-warning" role="note"><strong>Plazo:</strong> {{ \App\Support\NormasTecnicas::avisoPisee() }}</div>

<div class="row mb-3">@foreach($resumen as $k => $n)<div class="col-6 col-md"><div class="card h-100"><div class="card-body py-2"><div class="h4 mb-0">{{ $n }}</div><div class="small">{{ $estados[$k] }}</div></div></div></div>@endforeach</div>

<section class="card mb-4" aria-labelledby="dec"><div class="card-body"><h2 class="h5" id="dec">Normas revisadas</h2>
<ul class="mb-0">@foreach($decretos as $d)<li><strong>Decreto {{ $d['numero'] }}</strong> — {{ $d['titulo'] }}. <span class="small text-muted">Publicación {{ $d['publicacion'] }} · {{ $d['version'] }}.</span>
 @if($d['bcn'])<a href="{{ $d['bcn'] }}" rel="noopener noreferrer" target="_blank">Texto oficial (BCN)<span class="sr-only"> (se abre en una pestaña nueva)</span></a> · @endif<a href="{{ $d['wiki'] }}" rel="noopener noreferrer" target="_blank">Guías y documentos<span class="sr-only"> (se abre en una pestaña nueva)</span></a>@unless($d['leido']) <span class="estado estado-pendiente">texto no entregado</span>@endunless</li>@endforeach</ul></div></section>

<section class="card mb-4" aria-labelledby="mx"><div class="card-body"><h2 class="h5" id="mx">Matriz de cumplimiento por artículo</h2>
<form method="get" class="form-inline mb-3" aria-label="Filtrar la matriz">
 <label class="mr-2" for="fd">Decreto</label><select class="form-control mr-3" id="fd" name="decreto"><option value="">Todos</option>@foreach($decretos as $d)<option value="{{ $d['numero'] }}" @selected((string) $filtroD === (string) $d['numero'])>{{ $d['numero'] }}</option>@endforeach</select>
 <label class="mr-2" for="fe">Estado</label><select class="form-control mr-3" id="fe" name="estado"><option value="">Todos</option>@foreach($estados as $k => $l)<option value="{{ $k }}" @selected($filtro === $k)>{{ $l }}</option>@endforeach</select>
 <button class="btn btn-outline-primary">Filtrar</button> <a class="btn btn-link" href="{{ route('app.admin.cumplimiento') }}">Quitar filtros</a></form>
<p class="small text-muted"><strong>Implementado:</strong> lo hace el sistema y tiene pruebas automáticas. <strong>Parcial:</strong> lo hace en parte. <strong>Pendiente:</strong> el sistema no lo hace. <strong>Del municipio:</strong> lo define o decide la institución. <strong>Componente externo:</strong> lo provee Gobierno Digital u otro órgano.</p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Matriz de cumplimiento de las normas técnicas</caption>
<thead><tr><th scope="col">Decreto</th><th scope="col">Art.</th><th scope="col">Requisito</th><th scope="col">Estado</th><th scope="col">Qué hace el sistema / qué falta</th><th scope="col">Dónde</th></tr></thead><tbody>
@forelse($matriz as $m)<tr><td>{{ $m['decreto'] }}</td><td class="text-nowrap">{{ $m['art'] }}</td><td>{{ $m['requisito'] }}</td><td><span class="estado {{ $clase[$m['estado']] }}">{{ $estados[$m['estado']] }}</span></td><td>{{ $m['sistema'] }}</td><td class="small">{{ $m['donde'] }}</td></tr>
@empty<tr><td colspan="6" class="text-muted">No hay filas con este filtro.</td></tr>@endforelse</tbody></table></div></div></section>

<section class="card mb-4" aria-labelledby="pol"><div class="card-body"><h2 class="h5" id="pol">Política de Gestión Documental (Decreto 10, arts. 3 y 4)</h2>
<p class="mb-2"><span class="estado {{ $estadoPolitica['estado']==='vigente' ? 'estado-vigente' : ($estadoPolitica['estado']==='sin_registro' ? 'estado-fallida' : 'estado-pendiente') }}">{{ ['sin_registro'=>'Sin registro','vigente'=>'Vigente','por_revisar'=>'Por revisar','vencida'=>'Revisión vencida'][$estadoPolitica['estado']] }}</span> {{ $estadoPolitica['texto'] }}</p>
<p class="small text-muted">La política debe definir el objetivo, los roles responsables, los procedimientos, la preservación, los criterios de los procesos y la gobernanza, y revisarse entre cada 1 y 3 años. Aquí se registra el acto que la aprueba para llevar el control.</p>
@if($puedeEditar)
<form method="post" action="{{ route('app.admin.cumplimiento.politica') }}">@csrf
<div class="form-row"><div class="form-group col-md-5"><label for="p_d">Acto que la aprueba (decreto)</label><input class="form-control" id="p_d" name="decreto" required maxlength="190" value="{{ old('decreto', $politica['decreto']) }}" placeholder="Ej.: Decreto Alcaldicio N° 1234 del 01-01-2026"></div>
<div class="form-group col-md-3"><label for="p_f">Fecha de aprobación</label><input class="form-control" type="date" id="p_f" name="fecha_aprobacion" required value="{{ old('fecha_aprobacion', $politica['fecha_aprobacion']) }}"></div>
<div class="form-group col-md-2"><label for="p_r">Revisar cada (años)</label><select class="form-control" id="p_r" name="revision_anios">@foreach([1,2,3] as $a)<option value="{{ $a }}" @selected((int) old('revision_anios', $politica['revision_anios'])===$a)>{{ $a }}</option>@endforeach</select></div>
<div class="form-group col-md-2"><label for="p_p">Responsable</label><input class="form-control" id="p_p" name="responsable" maxlength="150" value="{{ old('responsable', $politica['responsable']) }}"></div></div>
<div class="form-group"><label for="p_n">Notas</label><input class="form-control" id="p_n" name="notas" maxlength="1000" value="{{ old('notas', $politica['notas']) }}"></div>
<button class="btn btn-primary">Guardar política</button></form>@endif</div></section>

<section class="card mb-4" aria-labelledby="cor"><div class="card-body"><h2 class="h5" id="cor">Coordinador(a) de Transformación Digital (Decreto 11, arts. 15 y 16)</h2>
<p class="small text-muted">Es quien desarrolla y gestiona el Plan de Mejora Continua y reporta a la División de Gobierno Digital. La Jefatura Superior debe informar su nombre (y cualquier cambio) mediante una comunicación oficial.</p>
@if($puedeEditar)
<form method="post" action="{{ route('app.admin.cumplimiento.coordinador') }}">@csrf
<div class="form-row"><div class="form-group col-md-4"><label for="c_t">Titular</label><input class="form-control" id="c_t" name="titular" required maxlength="150" value="{{ old('titular', $coordinador['titular']) }}"></div>
<div class="form-group col-md-4"><label for="c_s">Subrogante</label><input class="form-control" id="c_s" name="subrogante" maxlength="150" value="{{ old('subrogante', $coordinador['subrogante']) }}"></div>
<div class="form-group col-md-4"><label for="c_d">Acto de designación</label><input class="form-control" id="c_d" name="decreto" maxlength="190" value="{{ old('decreto', $coordinador['decreto']) }}"></div></div>
<div class="form-row"><div class="form-group col-md-3"><label for="c_f">Fecha de designación</label><input class="form-control" type="date" id="c_f" name="fecha" value="{{ old('fecha', $coordinador['fecha']) }}"></div>
<div class="form-group col-md-4"><label for="c_c">Informado a Gobierno Digital el</label><input class="form-control" type="date" id="c_c" name="comunicado_en" value="{{ old('comunicado_en', $coordinador['comunicado_en']) }}"></div></div>
<button class="btn btn-primary">Guardar designación</button></form>
@else<p>{{ $coordinador['titular'] }} (subrogante: {{ $coordinador['subrogante'] ?: '—' }}) · {{ $coordinador['decreto'] }}</p>@endif
@unless($coordinador['comunicado_en'])<p class="small text-danger mb-0 mt-2">Falta registrar la fecha en que se informó la designación a la División de Gobierno Digital.</p>@endunless</div></section>

<section class="card mb-4" aria-labelledby="cat"><div class="card-body"><h2 class="h5" id="cat">Catálogo de plataformas electrónicas y línea de base (Decreto 11, art. 3)</h2>
@if($puedeEditar)<p><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.cumplimiento.plataforma.nueva') }}">Agregar una plataforma</a></p>@endif
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Catálogo de plataformas</caption><thead><tr><th scope="col">Plataforma</th><th scope="col">Tipo</th><th scope="col">Línea de base</th><th scope="col">Priorizada</th><th scope="col">Iniciativas</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead><tbody>
@foreach($plataformas as $p)<tr><td>{{ $p->nombre }}@unless($p->activa) <em>(inactiva)</em>@endunless<br><span class="small text-muted">{{ $p->descripcion }}</span></td><td>{{ $p->tipo === 'propia' ? 'Propia' : 'Externa' }}</td><td class="small">{{ $p->linea_base_fecha?->format('d-m-Y') }} {{ \Illuminate\Support\Str::limit((string) $p->linea_base, 90) }}</td><td>{{ $p->priorizada ? 'Sí' : 'No' }}</td><td>{{ $p->iniciativas_count }}</td>
<td>@if($puedeEditar)<a class="btn btn-sm btn-outline-secondary" href="{{ route('app.admin.cumplimiento.plataforma', $p) }}">Editar<span class="sr-only"> {{ $p->nombre }}</span></a>@endif</td></tr>@endforeach</tbody></table></div></div></section>

<section class="card mb-4" aria-labelledby="pla"><div class="card-body"><h2 class="h5" id="pla">Plan de Mejora Continua (Decreto 11, arts. 4 a 10)</h2>
<p class="mb-2">Subcaracterísticas seleccionadas: <strong>{{ $subcar['distintas'] }}</strong> de al menos {{ $subcar['minimo'] }} requeridas. @if($subcar['cumple'])<span class="estado estado-vigente">Cumple</span>@else<span class="estado estado-pendiente">Faltan {{ $subcar['minimo'] - $subcar['distintas'] }}</span>@endif</p>
<p>@if($puedeEditar)<a class="btn btn-sm btn-outline-primary mr-2" href="{{ route('app.admin.cumplimiento.iniciativa.nueva') }}">Agregar una iniciativa</a>@endif<a class="btn btn-sm btn-outline-secondary" href="{{ route('app.admin.cumplimiento.informe') }}">Ver el informe del plan (imprimible)</a></p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Iniciativas del plan de mejora continua</caption><thead><tr><th scope="col">Plataforma</th><th scope="col">Iniciativa</th><th scope="col">Etapa</th><th scope="col">Subcaracterística y métrica</th><th scope="col">Responsable y plazo</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead><tbody>
@forelse($iniciativas as $i)<tr><td>{{ $i->plataforma?->nombre }}</td><td>{{ \Illuminate\Support\Str::limit($i->descripcion, 110) }}</td><td>{{ $i->etapa }}. {{ \App\Models\CalidadIniciativa::ETAPAS[$i->etapa] ?? '' }}</td><td class="small">{{ $i->subcaracteristica }}@if($i->metrica)<br>{{ $i->metrica }}@endif</td><td class="small">{{ $i->responsable }}@if($i->plazo)<br>{{ $i->plazo->format('d-m-Y') }}@endif</td><td>{{ \App\Models\CalidadIniciativa::ESTADOS[$i->estado] ?? $i->estado }}</td>
<td>@if($puedeEditar)<a class="btn btn-sm btn-outline-secondary" href="{{ route('app.admin.cumplimiento.iniciativa', $i) }}">Editar<span class="sr-only"> la iniciativa {{ $i->id }}</span></a>@endif</td></tr>
@empty<tr><td colspan="7" class="text-muted">Aún no hay iniciativas. Agregue las mejoras que el municipio planifica para sus plataformas priorizadas.</td></tr>@endforelse</tbody></table></div></div></section>
@endsection
