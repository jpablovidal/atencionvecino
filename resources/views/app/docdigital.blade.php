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
    <dt class="col-md-3">API</dt><dd class="col-md-9">DocDigital API v3 (OAuth2 con Client ID y Client Secret; rutas fijas según la especificación)</dd>
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
  @if($puedeGestionar)<p class="small mb-2"><a href="{{ route('app.docdigital.directorio') }}">Directorio de DocDigital</a>: entidades y personas habilitadas, cargadas automáticamente.</p>@endif
  @if($puedeGestionar)<p class="small mb-2"><a href="{{ route('app.docdigital.entidades') }}">Buscar entidades de DocDigital y sus ids</a> (necesarias para enviar documentos a otras instituciones).</p>@endif
  @if($diagnostico)
  <div class="mt-2" role="status"><h3 class="h6">Resultado del diagnóstico</h3>
    <ul class="list-unstyled mb-0">@foreach($diagnostico as [$etq,$okk,$det])<li><span class="estado {{ $okk ? 'estado-vigente' : 'estado-fallida' }}">{{ $okk ? 'OK' : 'Falla' }}</span> {{ $etq }}: {{ $det }}</li>@endforeach</ul></div>
  @endif
</div></section>

@if($veContenido)
@include('partials.panel', ['panel' => $panel])
@if($puedeGestionar)
<section class="card mb-4"><div class="card-body">
  <h2 class="h5">Evidencias de las llamadas a DocDigital</h2>
  <p class="small mb-2">Para la certificación con Gobierno Digital: guarda el <strong>JSON enviado y la respuesta</strong> de cada llamada (ingreso, acuse, devolución, consultas). No guarda credenciales, el token ni los archivos (los campos base64 se reemplazan por su tamaño y huella SHA-256). Se activa por 24 horas.</p>
  <p class="mb-2">Estado: @if($evidenciaHasta)<span class="estado estado-vigente">Activo hasta {{ $evidenciaHasta->format('d-m-Y H:i') }}</span>@else<span class="estado">Inactivo</span>@endif · {{ $evidenciaTotal }} llamada(s) registradas</p>
  <div class="d-flex flex-wrap">
    <form method="post" action="{{ route('app.docdigital.evidencia') }}" class="mr-2 mb-2">@csrf<input type="hidden" name="accion" value="{{ $evidenciaHasta ? 'desactivar' : 'activar' }}"><button class="btn btn-outline-primary">{{ $evidenciaHasta ? 'Desactivar registro' : 'Activar registro (24 h)' }}</button></form>
    @if($evidenciaTotal)
    <a class="btn btn-outline-secondary mr-2 mb-2" href="{{ route('app.docdigital.evidencia.descargar', ['formato' => 'json']) }}">Descargar JSON</a>
    <a class="btn btn-outline-secondary mr-2 mb-2" href="{{ route('app.docdigital.evidencia.descargar', ['formato' => 'zip']) }}">Descargar ZIP (un archivo por llamada)</a>
    <form method="post" action="{{ route('app.docdigital.evidencia') }}" class="mb-2" data-confirm="¿Borrar todas las evidencias registradas?">@csrf<input type="hidden" name="accion" value="borrar"><button class="btn btn-outline-danger">Borrar evidencias</button></form>
    @endif
  </div>
  @if($evidencias->isNotEmpty())
  <div class="table-responsive mt-2"><table class="table table-sm"><caption class="sr-only">Últimas llamadas registradas</caption><thead><tr><th scope="col">N°</th><th scope="col">Fecha</th><th scope="col">Ambiente</th><th scope="col">Llamada</th><th scope="col">HTTP</th><th scope="col">Detalle</th></tr></thead><tbody>
    @foreach($evidencias as $e)
    <tr><td>{{ $e->id }}</td><td>{{ $e->creado_en }}</td><td>{{ $e->ambiente }}</td><td><code>{{ $e->metodo }} {{ $e->ruta }}</code></td><td>{{ $e->http_status ?? 'sin respuesta' }}</td>
      <td><details><summary>Ver JSON</summary><p class="small mb-1"><strong>Solicitud</strong>@if($e->consulta) (consulta: {{ $e->consulta }})@endif</p><pre class="small">{{ $e->solicitud ?? '—' }}</pre><p class="small mb-1"><strong>Respuesta</strong></p><pre class="small">{{ $e->respuesta ?? '—' }}</pre></details></td></tr>
    @endforeach
  </tbody></table></div>
  @endif
</div></section>
@endif

<h2 class="seccion">Comunicaciones recibidas (últimas 15)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Comunicaciones recibidas</caption><thead><tr><th scope="col">Recibida</th><th scope="col">Origen</th><th scope="col">Asunto</th><th scope="col">Acuse</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($recibidas as $c)<tr><td class="text-nowrap">{{ $c->evidencia_en?->format('d-m-Y H:i') }}</td><td>{{ $c->institucion_nombre ?? $c->institucion_codigo ?? '—' }}</td><td>{{ $c->asunto }}</td><td><span class="estado estado-{{ $c->acuse_estado === 'pendiente' ? 'pendiente' : 'vigente' }}">{{ $c->acuse_estado }}</span></td><td>@if($c->expediente_id)<a class="btn btn-sm btn-outline-primary" href="{{ route('app.expediente',$c->expediente_id) }}">Abrir expediente<span class="sr-only"> de {{ $c->asunto }}</span></a>@endif</td></tr>
@empty<tr><td colspan="5">Sin comunicaciones recibidas.</td></tr>@endforelse</tbody></table></div>
<p class="small"><a href="{{ route('app.comunicaciones') }}">Ver toda la bandeja de recibidas</a></p>

<h2 class="seccion">Comunicaciones enviadas (últimas 15)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Comunicaciones enviadas</caption><thead><tr><th scope="col">Creada</th><th scope="col">Destino</th><th scope="col">Asunto</th><th scope="col">Estado</th><th scope="col">Intentos</th><th scope="col">Último error</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($enviadas as $c)<tr><td class="text-nowrap">{{ $c->created_at }}</td><td>{{ $c->institucion_nombre ?? $c->institucion_codigo }}</td><td>{{ $c->asunto }}</td><td><span class="estado estado-{{ $c->estado }}">{{ $c->estado }}</span>@if($c->ddEtiqueta())<br><small>{{ $c->ddEtiqueta() }}</small>@endif</td><td>{{ $c->intentos }}</td><td class="small">{{ $c->ultimo_error ? \Illuminate\Support\Str::limit($c->ultimo_error, 80) : '' }}</td><td>@if($c->expediente_id)<a class="btn btn-sm btn-outline-primary" href="{{ route('app.expediente',$c->expediente_id) }}">Abrir expediente<span class="sr-only"> de {{ $c->asunto }}</span></a>@endif</td></tr>
@empty<tr><td colspan="7">Sin comunicaciones enviadas.</td></tr>@endforelse</tbody></table></div>
<p class="small text-muted">Para enviar una comunicación, abra el expediente, elija un documento vigente y use «Comunicar por DocDigital». DocDigital exige un PDF con firma electrónica avanzada.</p>
@else
<p class="alert alert-info">Su rol puede probar la conexión y sincronizar, pero no ve el contenido de las comunicaciones.</p>
@endif
@endsection
