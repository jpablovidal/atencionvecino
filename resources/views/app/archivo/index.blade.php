@extends('layouts.app')
@section('titulo', 'Archivo municipal')
@section('contenido')
<div class="d-flex justify-content-between flex-wrap"><h1>Archivo municipal</h1>@if(auth()->user()->tienePermiso('archivo.gestionar'))<a class="btn btn-primary align-self-center" href="{{ route('app.archivo.crear') }}">Registrar microforma</a>@endif</div>
<p class="lead-sub">Registro, certificación y custodia de las microformas del archivo municipal.</p>
@include('partials.kpis', ['kpis' => [
  ['icono' => 'archive', 'valor' => $conteo->sum(), 'etiqueta' => 'Microformas registradas', 'nivel' => 'ok'],
  ['icono' => 'file-text', 'valor' => $conteo['borrador'] ?? 0, 'etiqueta' => 'En borrador', 'nivel' => 'info', 'url' => route('app.archivo.index', ['estado' => 'borrador'])],
  ['icono' => 'clock', 'valor' => $conteo['en_revision'] ?? 0, 'etiqueta' => 'En revisión (por certificar)', 'nivel' => ($conteo['en_revision'] ?? 0) > 0 ? 'aviso' : 'ok', 'url' => route('app.archivo.index', ['estado' => 'en_revision'])],
  ['icono' => 'check-circle', 'valor' => $conteo['certificada'] ?? 0, 'etiqueta' => 'Certificadas', 'nivel' => 'ok', 'url' => route('app.archivo.index', ['estado' => 'certificada'])],
  ['icono' => 'alert', 'valor' => $pendDisp, 'etiqueta' => 'Disposiciones por autorizar', 'nivel' => $pendDisp > 0 ? 'aviso' : 'ok'],
]])
<form method="get" class="mt-3 mb-3" role="search"><div class="form-row align-items-end">
  <div class="col-md-4 mb-2"><label for="q">Buscar</label><input class="form-control" id="q" name="q" maxlength="100" value="{{ $f['q'] }}" placeholder="Código, título, serie o ubicación"></div>
  <div class="col-md-2 mb-2"><label for="estado">Estado</label><select class="form-control" id="estado" name="estado"><option value="">Todos</option>@foreach(\App\Models\Microforma::ESTADOS as $k=>$v)<option value="{{ $k }}" @selected($f['estado']===$k)>{{ $v }}</option>@endforeach</select></div>
  <div class="col-md-2 mb-2"><label for="soporte">Soporte</label><select class="form-control" id="soporte" name="soporte"><option value="">Todos</option>@foreach(\App\Models\Microforma::SOPORTES as $k=>$v)<option value="{{ $k }}" @selected($f['soporte']===$k)>{{ $v }}</option>@endforeach</select></div>
  <div class="col-md-3 mb-2"><label for="unidad">Unidad productora</label><select class="form-control" id="unidad" name="unidad"><option value="">Todas</option>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected((int)$f['unidad']===$un->id)>{{ $un->nombre }}</option>@endforeach</select></div>
  <div class="col-md-1 mb-2"><button class="btn btn-outline-secondary btn-block">Filtrar</button></div></div></form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Microformas</caption><thead><tr><th scope="col">Código</th><th scope="col">Título</th><th scope="col">Soporte</th><th scope="col">Unidad productora</th><th scope="col">Extremos</th><th scope="col">Estado</th></tr></thead><tbody>
@forelse($micros as $m)
<tr><td><a href="{{ route('app.archivo.ver',$m->id) }}">{{ $m->codigo }}</a></td><td>{{ $m->titulo }}@if($m->serie)<br><small class="text-muted">{{ $m->serie }}</small>@endif</td><td>{{ \App\Models\Microforma::SOPORTES[$m->tipo_soporte] ?? $m->tipo_soporte }}</td><td>{{ $m->unidad?->nombre ?? '—' }}</td>
<td>{{ $m->fecha_desde?->format('d-m-Y') }}{{ $m->fecha_desde || $m->fecha_hasta ? ' a ' : '—' }}{{ $m->fecha_hasta?->format('d-m-Y') }}</td><td><span class="estado estado-{{ $m->estado }}">{{ \App\Models\Microforma::ESTADOS[$m->estado] ?? $m->estado }}</span></td></tr>
@empty<tr><td colspan="6">No hay microformas que coincidan.</td></tr>@endforelse</tbody></table></div>
{{ $micros->links() }}
@endsection
