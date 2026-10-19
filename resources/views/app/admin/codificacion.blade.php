@extends('layouts.app')
@section('titulo', 'Codificación y normas técnicas')
@section('contenido')
<h1>Codificación y normas técnicas</h1>
<p class="lead-sub">La codificación de documentos y expedientes se basa en el <strong>Codificador del Estado</strong> y las normas técnicas de la Ley 21.180. Elija a qué institución corresponde su municipio y cómo se arma el identificador.</p>
<div class="alert alert-info" role="note"><strong>A VALIDAR:</strong> el texto de las normas técnicas y el contrato de la API del Codificador no pudieron leerse desde el entorno de desarrollo. Los patrones son configurables y deben contrastarse con la norma vigente antes de operar.</div>

<section class="card mb-4" aria-labelledby="mu"><div class="card-body"><h2 class="h5" id="mu">Municipio en el Codificador</h2>
<p>Actual: @if($municipio['codigo'])<strong>{{ $municipio['nombre'] ?? 'Sin nombre en el catálogo' }}</strong> (código <code>{{ $municipio['codigo'] }}</code>, origen: {{ $municipio['origen'] }})@else <strong>sin configurar</strong> (se usa «SGD»)@endif.</p>
<form method="get" class="form-inline mb-3"><label class="mr-2" for="q">Buscar en el catálogo ({{ $total }} instituciones)</label><input class="form-control mr-2" id="q" name="q" value="{{ $q }}" maxlength="60"><button class="btn btn-outline-primary">Buscar</button></form>
<form method="post" action="{{ route('app.admin.codificacion.municipio') }}">@csrf
 <div class="form-group"><label for="codigo">Institución</label>
 <select class="form-control" id="codigo" name="codigo" required><option value="">Seleccione…</option>@foreach($inst as $i)<option value="{{ $i->codigo }}" @selected($municipio['codigo']===$i->codigo)>{{ $i->nombre }} ({{ $i->codigo }})</option>@endforeach</select></div>
 <button class="btn btn-primary">Usar este municipio</button></form>
@if($total === 0)<p class="small mt-2">El catálogo está vacío. <a href="{{ route('app.admin.codificador') }}">Impórtelo o sincronícelo</a> o ingrese el código a mano:</p>
<form method="post" action="{{ route('app.admin.codificacion.municipio') }}" class="form-inline">@csrf<input type="hidden" name="manual" value="1"><label class="mr-2" for="cm">Código</label><input class="form-control mr-2" id="cm" name="codigo" maxlength="30" required><button class="btn btn-outline-primary">Guardar código manual</button></form>@endif
</div></section>

<section class="card mb-4" aria-labelledby="pa"><div class="card-body"><h2 class="h5" id="pa">Patrones de identificador</h2>
<form method="post" action="{{ route('app.admin.codificacion.patrones') }}">@csrf
 <div class="form-group"><label for="pe">Expediente (obligatorio incluir {codigo})</label><input class="form-control" id="pe" name="expediente" maxlength="100" required value="{{ old('expediente', $patrones['expediente']) }}" aria-describedby="pe-a"><small id="pe-a" class="form-text text-muted">Marcadores: @foreach($tokensExp as $t)<code>{{ '{'.$t.'}' }}</code> @endforeach · Por defecto <code>{{ $defecto['expediente'] }}</code>@if($ejExp) · Ejemplo: <strong>{{ $ejExp }}</strong>@endif</small></div>
 <div class="form-group"><label for="pd">Documento (obligatorio incluir {tipo}, {anio} y {numero})</label><input class="form-control" id="pd" name="documento" maxlength="100" required value="{{ old('documento', $patrones['documento']) }}" aria-describedby="pd-a"><small id="pd-a" class="form-text text-muted">Marcadores: @foreach($tokensDoc as $t)<code>{{ '{'.$t.'}' }}</code> @endforeach · Por defecto <code>{{ $defecto['documento'] }}</code>@if($ejDoc) · Ejemplo: <strong>{{ $ejDoc }}</strong>@endif</small></div>
 <button class="btn btn-primary">Guardar patrones</button></form></div></section>

<section class="card mb-4" aria-labelledby="no"><div class="card-body"><h2 class="h5" id="no">Normas técnicas y matriz de cumplimiento</h2>
<ul>@foreach($normas as $n => $u)<li><a href="{{ $u }}" rel="noopener noreferrer" target="_blank">{{ $n }}<span class="sr-only"> (se abre en una pestaña nueva)</span></a></li>@endforeach</ul>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Matriz de cumplimiento</caption><thead><tr><th scope="col">Tema</th><th scope="col">Qué hace el sistema</th><th scope="col">Estado</th><th scope="col">Dónde</th></tr></thead><tbody>
@foreach($matriz as $m)<tr><th scope="row">{{ $m['tema'] }}</th><td>{{ $m['control'] }}</td><td>{{ $m['estado'] }}</td><td>{{ $m['donde'] }}</td></tr>@endforeach</tbody></table></div></div></section>
@endsection
