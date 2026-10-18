@extends('layouts.app')
@section('titulo', 'Casilla Única')
@section('contenido')
<h1>Casilla Única</h1>
<p class="lead-sub">Notificador de la ciudadanía: notificaciones de sus expedientes por Casilla Única, con su evidencia de envío.</p>

<section class="card mb-4"><div class="card-body">
  <h2 class="h5">Estado de la conexión</h2>
  <dl class="row mb-2">
    <dt class="col-md-3">Modo</dt><dd class="col-md-9"><span class="estado {{ $api ? 'estado-vigente' : 'estado-pendiente' }}">{{ $api ? 'API' : 'Outbox (registro manual de evidencia)' }}</span></dd>
    <dt class="col-md-3">Servidor</dt><dd class="col-md-9">{{ $host ?? 'sin definir' }}</dd>
    <dt class="col-md-3">Credenciales</dt><dd class="col-md-9">{{ $credenciales ?? 'sin definir' }} <span class="small text-muted">(los valores no se muestran)</span></dd>
  </dl>
  <p class="small text-muted mb-2">En modo <em>Outbox</em> el sistema no envía nada: cada notificación queda «pendiente» hasta que un funcionario registra la evidencia real (ID de Casilla Única). El contrato de la API de integración está <strong>A VALIDAR</strong>; la conexión se configura en <em>Administración → APIs e integraciones → Casilla Única / Notificador</em>.</p>
  @if($puedeGestionar)
  <div class="d-flex flex-wrap">
    <form method="post" action="{{ route('app.casilla.diagnosticar') }}" class="mr-2 mb-2">@csrf<button class="btn btn-outline-primary">Probar conexión</button></form>
    <form method="post" action="{{ route('app.casilla.reintentar') }}" class="mb-2" data-confirm="¿Reintentar los envíos pendientes y fallidos (hasta 20)?">@csrf<button class="btn btn-outline-secondary">Reintentar envíos</button></form>
  </div>
  @endif
  @if($diagnostico)
  <div class="mt-2" role="status"><h3 class="h6">Resultado del diagnóstico</h3>
    <ul class="list-unstyled mb-0">@foreach($diagnostico as [$etq,$okk,$det])<li><span class="estado {{ $okk ? 'estado-vigente' : 'estado-fallida' }}">{{ $okk ? 'OK' : 'Falla' }}</span> {{ $etq }}: {{ $det }}</li>@endforeach</ul></div>
  @endif
</div></section>

@include('partials.kpis', ['kpis' => $kpis])

<h2 class="seccion">Pendientes y fallidas (hasta 50)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Notificaciones pendientes o fallidas</caption><thead><tr><th scope="col">Creada</th><th scope="col">Expediente</th><th scope="col">Documento</th><th scope="col">Estado</th><th scope="col">Intentos</th><th scope="col">Registrar evidencia</th></tr></thead><tbody>
@forelse($pendientes as $n)<tr><td class="text-nowrap">{{ $n->created_at }}</td><td><a href="{{ route('app.expediente',$n->expediente_id) }}">{{ $n->expediente?->codigo }}</a></td><td>{{ $n->documento?->titulo }}</td><td><span class="estado estado-{{ $n->estado }}">{{ $n->estado }}</span>@if($n->ultimo_error)<br><span class="small">{{ \Illuminate\Support\Str::limit($n->ultimo_error, 80) }}</span>@endif</td><td>{{ $n->intentos }}</td>
<td>@if($puedeEvidencia)<form method="post" action="{{ route('app.notificacion.evidencia',$n) }}" class="form-inline">@csrf<input type="hidden" name="tipo" value="id_casilla_unica"><label class="sr-only" for="ev{{ $n->id }}">ID de Casilla Única de la notificación {{ $n->id }}</label><input id="ev{{ $n->id }}" name="valor" class="form-control form-control-sm mr-1" maxlength="250" required placeholder="ID de Casilla Única"><button class="btn btn-sm btn-outline-primary">Registrar</button></form>@endif</td></tr>
@empty<tr><td colspan="6">No hay notificaciones pendientes.</td></tr>@endforelse</tbody></table></div>

<h2 class="seccion">Enviadas recientemente (últimas 15)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Notificaciones enviadas</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Expediente</th><th scope="col">Documento</th><th scope="col">Evidencia</th></tr></thead><tbody>
@forelse($recientes as $n)<tr><td class="text-nowrap">{{ $n->evidencia_en?->format('d-m-Y H:i') }}</td><td><a href="{{ route('app.expediente',$n->expediente_id) }}">{{ $n->expediente?->codigo }}</a></td><td>{{ $n->documento?->titulo }}</td><td><code>{{ $n->evidencia }}</code></td></tr>
@empty<tr><td colspan="4">Aún no hay notificaciones enviadas.</td></tr>@endforelse</tbody></table></div>
<p class="small text-muted">Para notificar, cierre el expediente y use «Notificar al ciudadano» eligiendo el canal Casilla Única.</p>
@endsection
