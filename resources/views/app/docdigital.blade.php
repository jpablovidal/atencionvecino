@extends('layouts.app')
@section('titulo', 'DocDigital')
@section('contenido')
<h1>DocDigital</h1>
<p class="lead-sub">Gestión de las comunicaciones oficiales con otras instituciones del Estado.</p>

<section class="card mb-4"><div class="card-body">
  <h2 class="h5">Estado de la conexión</h2>
  <dl class="row mb-2">
    <dt class="col-md-3">Modo</dt><dd class="col-md-9"><span class="estado {{ in_array($modo,['prod','demo'],true) ? 'estado-vigente' : 'estado-pendiente' }}">{{ $modoEtq }}</span> @if($modo==='mock')<span class="sim">SIMULADO</span>@endif</dd>
    <dt class="col-md-3">Servidor</dt><dd class="col-md-9">{{ $host ?? 'sin definir' }}</dd>
    <dt class="col-md-3">Credenciales</dt><dd class="col-md-9">{{ $credenciales ?? 'sin definir' }} <span class="small text-muted">(los valores no se muestran)</span></dd>
    <dt class="col-md-3">Rutas de la API</dt><dd class="col-md-9">{{ $rutas ? 'configuradas (enviar, recibidos y acuse)' : 'incompletas' }}</dd>
    <dt class="col-md-3">Unidad receptora</dt><dd class="col-md-9"><code>{{ $unidadRecepcion }}</code></dd>
    <dt class="col-md-3">Última sincronización</dt><dd class="col-md-9">
      @if($estado['ultima_sync']){{ \Illuminate\Support\Carbon::parse($estado['ultima_sync'])->format('d-m-Y H:i') }} — @if($estado['error'])<strong class="text-danger">con error:</strong> {{ $estado['error'] }}@else {{ $estado['nuevas'] }} nueva(s), {{ $estado['omitidas'] }} omitida(s)@endif
      @else aún no se ha sincronizado @endif</dd>
  </dl>
  <p class="small text-muted mb-2">La sincronización automática corre cada 10 minutos cuando el modo es DemoDoc o Producción. Las credenciales se configuran en <em>Administración → APIs e integraciones → DocDigital</em>.</p>
  @if($puedeGestionar)
  <div class="d-flex flex-wrap">
    <form method="post" action="{{ route('app.docdigital.diagnosticar') }}" class="mr-2 mb-2">@csrf<button class="btn btn-outline-primary">Probar conexión</button></form>
    <form method="post" action="{{ route('app.docdigital.sincronizar') }}" class="mr-2 mb-2">@csrf<button class="btn btn-outline-primary">Sincronizar ahora</button></form>
    <form method="post" action="{{ route('app.docdigital.reintentar') }}" class="mb-2" data-confirm="¿Reintentar los envíos fallidos (hasta 20)?">@csrf<button class="btn btn-outline-secondary">Reintentar envíos fallidos</button></form>
  </div>
  @endif
  @if($diagnostico)
  <div class="mt-2" role="status"><h3 class="h6">Resultado del diagnóstico</h3>
    <ul class="list-unstyled mb-0">@foreach($diagnostico as [$etq,$okk,$det])<li><span class="estado {{ $okk ? 'estado-vigente' : 'estado-fallida' }}">{{ $okk ? 'OK' : 'Falla' }}</span> {{ $etq }}: {{ $det }}</li>@endforeach</ul></div>
  @endif
</div></section>

@if($veContenido)
@include('partials.panel', ['panel' => $panel])
<h2 class="seccion">Comunicaciones recibidas (últimas 15)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Comunicaciones recibidas</caption><thead><tr><th scope="col">Recibida</th><th scope="col">Origen</th><th scope="col">Asunto</th><th scope="col">Acuse</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($recibidas as $c)<tr><td class="text-nowrap">{{ $c->evidencia_en?->format('d-m-Y H:i') }}</td><td>{{ $c->institucion_nombre ?? $c->institucion_codigo ?? '—' }}</td><td>{{ $c->asunto }}</td><td><span class="estado estado-{{ $c->acuse_estado === 'pendiente' ? 'pendiente' : 'vigente' }}">{{ $c->acuse_estado }}</span></td><td>@if($c->expediente_id)<a class="btn btn-sm btn-outline-primary" href="{{ route('app.expediente',$c->expediente_id) }}">Abrir expediente<span class="sr-only"> de {{ $c->asunto }}</span></a>@endif</td></tr>
@empty<tr><td colspan="5">Sin comunicaciones recibidas.</td></tr>@endforelse</tbody></table></div>
<p class="small"><a href="{{ route('app.comunicaciones') }}">Ver toda la bandeja de recibidas</a></p>

<h2 class="seccion">Comunicaciones enviadas (últimas 15)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Comunicaciones enviadas</caption><thead><tr><th scope="col">Creada</th><th scope="col">Destino</th><th scope="col">Asunto</th><th scope="col">Estado</th><th scope="col">Intentos</th><th scope="col">Último error</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($enviadas as $c)<tr><td class="text-nowrap">{{ $c->created_at }}</td><td>{{ $c->institucion_nombre ?? $c->institucion_codigo }}</td><td>{{ $c->asunto }}</td><td><span class="estado estado-{{ $c->estado }}">{{ $c->estado }}</span></td><td>{{ $c->intentos }}</td><td class="small">{{ $c->ultimo_error ? \Illuminate\Support\Str::limit($c->ultimo_error, 80) : '' }}</td><td>@if($c->expediente_id)<a class="btn btn-sm btn-outline-primary" href="{{ route('app.expediente',$c->expediente_id) }}">Abrir expediente<span class="sr-only"> de {{ $c->asunto }}</span></a>@endif</td></tr>
@empty<tr><td colspan="7">Sin comunicaciones enviadas.</td></tr>@endforelse</tbody></table></div>
<p class="small text-muted">Para enviar una comunicación, abra el expediente, elija un documento vigente y use «Comunicar por DocDigital». DocDigital exige un PDF con firma electrónica avanzada.</p>
@else
<p class="alert alert-info">Su rol puede probar la conexión y sincronizar, pero no ve el contenido de las comunicaciones.</p>
@endif
@endsection
