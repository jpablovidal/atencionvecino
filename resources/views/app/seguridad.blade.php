@extends('layouts.app')
@section('titulo', 'Seguridad')
@section('contenido')
<h1>Seguridad y auditoría</h1>
<form method="post" action="{{ route('app.seguridad.verificar') }}" class="mb-3">@csrf<button class="btn btn-primary">Verificar cadena de auditoría (HMAC)</button></form>
@if($resultado)<div class="alert alert-{{ $resultado['ok']?'success':'danger' }}" role="status">@if($resultado['ok'])Cadena íntegra: {{ $resultado['total'] }} registros verificados.@else ¡CADENA ROTA en el registro {{ $resultado['roto_en'] }}! Registre un incidente.@endif</div>@endif

<h2 class="h4">Registro de incidentes (Ley 21.663)</h2>
<p class="small text-muted">Marque los hitos de reporte al CSIRT Nacional (ANCI). Plazos exactos A VALIDAR en docs/NORMATIVA.md.</p>
<table class="table table-sm"><thead><tr><th>#</th><th>Incidente</th><th>Severidad</th><th>Estado</th><th>Alerta temprana</th><th>2.º reporte</th><th>Informe final</th><th></th></tr></thead><tbody>
@foreach($incidentes as $i)<tr><td>{{ $i->id }}</td><td>{{ $i->titulo }}<br><small>{{ $i->descripcion }}</small></td><td>{{ $i->severidad }}{{ $i->reportable_csirt?' · reportable':'' }}</td><td>{{ $i->estado }}</td><td>{{ $i->alerta_temprana_en?->format('d-m-Y H:i') ?? '—' }}</td><td>{{ $i->segundo_reporte_en?->format('d-m-Y H:i') ?? '—' }}</td><td>{{ $i->informe_final_en?->format('d-m-Y H:i') ?? '—' }}</td>
<td>@if(auth()->user()->rol==='admin')<form method="post" action="{{ route('app.seguridad.incidente.actualizar',$i) }}" class="form-inline">@csrf
<select name="estado" class="form-control form-control-sm mr-1" aria-label="Estado"><option>abierto</option><option>contenido</option><option>cerrado</option></select>
<select name="marca" class="form-control form-control-sm mr-1" aria-label="Hito CSIRT"><option value="">Sin hito</option><option value="alerta_temprana">Alerta temprana</option><option value="segundo_reporte">Segundo reporte</option><option value="informe_final">Informe final</option></select><button class="btn btn-sm btn-outline-primary">Actualizar</button></form>@endif</td></tr>@endforeach</tbody></table>
@if(auth()->user()->rol==='admin')
<form method="post" action="{{ route('app.seguridad.incidente') }}" class="card card-body mb-4">@csrf<h3 class="h6">Registrar incidente</h3>
<div class="form-group"><label for="i_t">Título</label><input class="form-control" id="i_t" name="titulo" required maxlength="190"></div>
<div class="form-group"><label for="i_d">Descripción (sin datos personales ni secretos)</label><textarea class="form-control" id="i_d" name="descripcion" required maxlength="4000"></textarea></div>
<div class="form-row"><div class="form-group col-md-3"><label for="i_s">Severidad</label><select class="form-control" id="i_s" name="severidad"><option>baja</option><option>media</option><option>alta</option><option>critica</option></select></div>
<div class="col-md-4 pt-4"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="i_r" name="reportable_csirt" value="1"><label class="custom-control-label" for="i_r">Reportable al CSIRT Nacional</label></div></div></div>
<div><button class="btn btn-primary">Registrar</button></div></form>@endif

<h2 class="h4">Últimos eventos de auditoría</h2>
<table class="table table-sm"><thead><tr><th>#</th><th>Fecha</th><th>Rol</th><th>Acción</th><th>Entidad</th><th>IP</th></tr></thead><tbody>
@foreach($eventos as $v)<tr><td>{{ $v->id }}</td><td>{{ $v->ts }}</td><td>{{ $v->actor_rol }}</td><td>{{ $v->accion }}</td><td>{{ $v->entidad }} {{ $v->entidad_id }}</td><td>{{ $v->ip }}</td></tr>@endforeach</tbody></table>
@endsection
