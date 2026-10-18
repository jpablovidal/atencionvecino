@extends('layouts.app')
@section('titulo', 'Contenido asociado')
@section('contenido')
<p><a href="{{ route('app.expediente', $v->expediente) }}">← Expediente {{ $v->expediente->codigo }}</a></p>
<h1>{{ $v->tipo==='microforma' ? 'Microforma asociada' : 'Expediente asociado' }}: {{ $c['titulo'] }}</h1>
<p>Asociado por {{ $v->creador?->nombreCompleto() }} el {{ $v->created_at->setTimezone('America/Santiago')->format('d-m-Y H:i') }}. Motivo: {{ $v->motivo }}</p>
@if($v->tipo==='expediente' && $v->origen && $c['acceso'])<p><a href="{{ route('app.expediente', $v->origen) }}">Abrir el expediente {{ $v->origen->codigo }}</a></p>@endif
@if(! $c['acceso'])<div class="alert alert-warning" role="status">Ya no tiene acceso a este contenido ({{ $v->tipo==='microforma' ? 'requiere el permiso de consulta del archivo municipal' : 'no puede ver el expediente' }}).</div>
@else
<form method="post" action="{{ route('app.vinculos.incorporar', $v) }}" class="card card-body mb-3">@csrf
 <h2 class="h5">Incorporar copias al expediente {{ $v->expediente->codigo }}</h2>
 <p class="small text-muted">Se copia el contenido (queda su origen y su huella SHA-256). Lo restringido solo se copia si usted está autorizado y la copia queda restringida.</p>
 @if($c['documentos'])<fieldset class="mb-2"><legend class="h6">Documentos vigentes</legend>@foreach($c['documentos'] as $d)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="vd{{ $d['id'] }}" name="documentos[]" value="{{ $d['id'] }}" @disabled($d['oculto'] || ! $puedeOperar)><label class="custom-control-label" for="vd{{ $d['id'] }}">{{ $d['nombre'] }}@if($d['restringido']) <span class="chip chip-claro">restringido</span>@endif</label></div>@endforeach</fieldset>@endif
 <fieldset class="mb-2"><legend class="h6">Archivos</legend>@forelse($c['archivos'] as $a)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="va{{ $a['id'] }}" name="archivos[]" value="{{ $a['id'] }}" @disabled($a['oculto'] || ! $puedeOperar)><label class="custom-control-label" for="va{{ $a['id'] }}">{{ $a['nombre'] }}@if($a['restringido']) <span class="chip chip-claro">restringido</span>@endif</label></div>@empty<p class="mb-0">No hay archivos para incorporar.</p>@endforelse</fieldset>
 @if($puedeOperar)<div><button class="btn btn-primary">Incorporar lo seleccionado</button></div>@else<p class="small mb-0">El expediente está cerrado o usted no puede operarlo: solo consulta.</p>@endif</form>
@endif
@if($puedeOperar)<form method="post" action="{{ route('app.vinculos.quitar', $v) }}" data-confirm="¿Quitar esta asociación? Las copias ya incorporadas se conservan.">@csrf<button class="btn btn-outline-secondary">Quitar la asociación</button></form>@endif
@endsection
