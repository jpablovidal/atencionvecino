@extends('layouts.app')
@section('titulo', $m->codigo)
@section('contenido')
@php $S = \App\Models\Microforma::SOPORTES; $E = \App\Models\Microforma::ESTADOS; @endphp
<nav aria-label="Ruta de navegación"><a href="{{ route('app.archivo.index') }}">&larr; Archivo municipal</a></nav>
<div class="d-flex justify-content-between flex-wrap mt-2"><h1>{{ $m->codigo }}</h1><span class="estado estado-{{ $m->estado }} align-self-center">{{ $E[$m->estado] ?? $m->estado }}</span></div>
<p class="lead-sub">{{ $m->titulo }}</p>

<div class="d-flex flex-wrap mb-3">
  <form method="post" action="{{ route('app.archivo.verificar',$m->id) }}" class="mr-2 mb-2">@csrf<button class="btn btn-outline-secondary">Verificar integridad</button></form>
  <a class="btn btn-outline-secondary mr-2 mb-2" href="{{ route('app.archivo.constancia',$m->id) }}" target="_blank" rel="noopener">Constancia de registro (PDF)<span class="sr-only"> (se abre en una pestaña nueva)</span></a>
</div>

<section class="card mb-4"><div class="card-body">
  <h2 class="h5">Ficha</h2>
  <dl class="row mb-0">
    <dt class="col-md-3">Soporte</dt><dd class="col-md-9">{{ $S[$m->tipo_soporte] ?? $m->tipo_soporte }}</dd>
    <dt class="col-md-3">Unidad productora</dt><dd class="col-md-9">{{ $m->unidad?->nombre ?? '—' }}</dd>
    <dt class="col-md-3">Serie documental</dt><dd class="col-md-9">{{ $m->serie ?? '—' }}</dd>
    <dt class="col-md-3">Extremos</dt><dd class="col-md-9">{{ $m->fecha_desde?->format('d-m-Y') ?? '—' }} a {{ $m->fecha_hasta?->format('d-m-Y') ?? '—' }}</dd>
    <dt class="col-md-3">Documentos / imágenes</dt><dd class="col-md-9">{{ $m->n_documentos }} / {{ $m->n_imagenes }}</dd>
    <dt class="col-md-3">Ubicación física</dt><dd class="col-md-9">{{ $m->ubicacion ?? '—' }}</dd>
    <dt class="col-md-3">Descripción</dt><dd class="col-md-9" style="white-space:pre-line">{{ $m->descripcion ?? '—' }}</dd>
    <dt class="col-md-3">Registrada por</dt><dd class="col-md-9">{{ $m->creador?->nombreCompleto() }} · {{ $m->created_at?->format('d-m-Y H:i') }}</dd>
    @if($m->estado==='certificada')
    <dt class="col-md-3">Certificación</dt><dd class="col-md-9">Acta <strong>{{ $m->acta_numero }}</strong> · {{ $m->certificador?->nombreCompleto() }} · {{ $m->certificada_en?->format('d-m-Y H:i') }}
      @if($acta)<br>Acta: <a href="{{ route('app.archivo.archivo',[$m->id,$acta->id]) }}">{{ $acta->nombre_original }}</a> — firma electrónica: <strong>{{ ['sin_firma'=>'no detectada','firmado'=>'detectada','firmado_invalida'=>'detectada con observaciones','no_verificable'=>'no verificable','no_aplica'=>'no aplica'][$acta->firma_estado] ?? $acta->firma_estado }}</strong>@endif</dd>
    <dt class="col-md-3">Hash del conjunto (SHA-256)</dt><dd class="col-md-9 hash">{{ $m->sha256_conjunto }}</dd>
    @endif
  </dl>
  @if($m->editable() && $u->tienePermiso('archivo.gestionar'))
  <details class="mt-3"><summary class="btn btn-sm btn-outline-primary">Editar ficha</summary>
    <form method="post" action="{{ route('app.archivo.actualizar',$m->id) }}" class="mt-3">@csrf @include('app.archivo._campos',['m'=>$m])<button class="btn btn-primary">Guardar cambios</button></form></details>
  @endif
</div></section>

<h2 class="seccion">Archivos maestros ({{ $archivos->count() }})</h2>
@if($m->editable() && $u->tienePermiso('archivo.gestionar'))
<form method="post" action="{{ route('app.archivo.subir',$m->id) }}" enctype="multipart/form-data" class="mb-3">@csrf
  <div class="form-group"><label for="archivos">Cargar imágenes o PDF de la microforma (PDF, JPG, PNG o TIFF; máx. 10 MB c/u; hasta 40 por vez)</label>
    <input class="form-control-file" type="file" id="archivos" name="archivos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" required></div>
  <button class="btn btn-outline-primary">Cargar y cifrar</button></form>
@endif
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Archivos maestros</caption><thead><tr><th scope="col">N.º</th><th scope="col">Archivo</th><th scope="col">Tamaño</th><th scope="col">SHA-256</th><th scope="col">Firma electrónica</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($archivos as $a)
<tr><td>{{ $a->pivot->orden }}</td><td>{{ $a->nombre_original }}</td><td>{{ number_format($a->tamano/1024, 0, ',', '.') }} KB</td><td class="hash">{{ $a->sha256 }}</td><td>{{ ['sin_firma'=>'No detectada','firmado'=>'Detectada','firmado_invalida'=>'Con observaciones','no_verificable'=>'No verificable','no_aplica'=>'No aplica'][$a->firma_estado] ?? '—' }}</td>
<td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ route('app.archivo.archivo',[$m->id,$a->id]) }}">Descargar<span class="sr-only"> {{ $a->nombre_original }}</span></a>
@if($m->editable() && $u->tienePermiso('archivo.gestionar'))<form method="post" action="{{ route('app.archivo.quitar',[$m->id,$a->id]) }}" class="d-inline" data-confirm="¿Quitar el archivo «{{ $a->nombre_original }}»?">@csrf<button class="btn btn-sm btn-outline-danger">Quitar<span class="sr-only"> {{ $a->nombre_original }}</span></button></form>@endif</td></tr>
@empty<tr><td colspan="6">Aún no hay archivos cargados.</td></tr>@endforelse</tbody></table></div>

<h2 class="seccion">Índice de documentos ({{ $m->items->count() }})</h2>
@if($m->editable() && $u->tienePermiso('archivo.gestionar'))
<form method="post" action="{{ route('app.archivo.item.agregar',$m->id) }}" class="mb-3">@csrf<div class="form-row align-items-end">
  <div class="form-group col-md-3"><label for="referencia">Referencia</label><input class="form-control" id="referencia" name="referencia" required maxlength="120" placeholder="Ej.: Decreto N° 15/2021"></div>
  <div class="form-group col-md-3"><label for="descripcion_item">Descripción</label><input class="form-control" id="descripcion_item" name="descripcion" maxlength="255"></div>
  <div class="form-group col-md-2"><label for="fecha_documento">Fecha</label><input class="form-control" type="date" id="fecha_documento" name="fecha_documento"></div>
  <div class="form-group col-md-1"><label for="fojas">Fojas</label><input class="form-control" id="fojas" name="fojas" maxlength="30"></div>
  <div class="form-group col-md-2"><label for="posicion">Posición</label><input class="form-control" id="posicion" name="posicion" maxlength="30" placeholder="Fotograma"></div>
  <div class="form-group col-md-1"><button class="btn btn-outline-primary btn-block">Agregar</button></div></div></form>
@endif
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Índice de documentos</caption><thead><tr><th scope="col">Referencia</th><th scope="col">Descripción</th><th scope="col">Fecha</th><th scope="col">Fojas</th><th scope="col">Posición</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($m->items as $i)
<tr><td>{{ $i->referencia }}</td><td>{{ $i->descripcion }}</td><td>{{ $i->fecha_documento?->format('d-m-Y') }}</td><td>{{ $i->fojas }}</td><td>{{ $i->posicion }}</td>
<td>@if($m->editable() && $u->tienePermiso('archivo.gestionar'))<form method="post" action="{{ route('app.archivo.item.quitar',[$m->id,$i->id]) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger">Quitar<span class="sr-only"> {{ $i->referencia }}</span></button></form>@endif</td></tr>
@empty<tr><td colspan="6">Sin documentos indexados.</td></tr>@endforelse</tbody></table></div>

@php $hayAcciones = ($m->estado==='borrador' && $u->tienePermiso('archivo.gestionar')) || ($m->estado==='en_revision' && ($u->tienePermiso('archivo.certificar') || $u->tienePermiso('archivo.gestionar'))); @endphp
@if($hayAcciones)<h2 class="seccion">Acciones</h2>@endif
<div class="row">
@if($m->estado==='borrador' && $u->tienePermiso('archivo.gestionar'))
  <div class="col-md-6 mb-3"><div class="card h-100"><div class="card-body"><h3 class="h6">Enviar a revisión</h3><p class="small text-muted">Fija el conjunto de archivos y lo envía al ministro de fe para su certificación.</p>
    <form method="post" action="{{ route('app.archivo.enviar',$m->id) }}" data-confirm="¿Enviar a revisión? Ya no podrá cambiar los archivos sin que se la devuelvan.">@csrf<button class="btn btn-primary">Enviar a revisión</button></form></div></div></div>
@endif
@if($m->estado==='en_revision' && $u->tienePermiso('archivo.certificar'))
  <div class="col-md-6 mb-3"><div class="card h-100"><div class="card-body"><h3 class="h6">Certificar</h3>
    @if($esCreador)<p class="alert alert-warning small">Separación de funciones: usted registró esta microforma, por lo que no puede certificarla.</p>
    @else<p class="small text-muted">Verifica la integridad de todos los archivos, registra el acta y deja los archivos inmutables.</p>
    <form method="post" action="{{ route('app.archivo.certificar',$m->id) }}" enctype="multipart/form-data">@csrf
      <div class="form-group"><label for="acta_numero">N.º del acta de certificación</label><input class="form-control" id="acta_numero" name="acta_numero" required maxlength="60"></div>
      <div class="form-group"><label for="acta">Acta firmada (PDF)</label><input class="form-control-file" type="file" id="acta" name="acta" accept=".pdf" required></div>
      <button class="btn btn-success">Certificar</button></form>@endif</div></div></div>
  <div class="col-md-6 mb-3"><div class="card h-100"><div class="card-body"><h3 class="h6">Devolver a borrador</h3>
    <form method="post" action="{{ route('app.archivo.devolver',$m->id) }}">@csrf<div class="form-group"><label for="motivo_dev">Motivo</label><textarea class="form-control" id="motivo_dev" name="motivo" rows="2" required minlength="10" maxlength="1000"></textarea></div><button class="btn btn-outline-secondary">Devolver</button></form></div></div></div>
@endif
@if(in_array($m->estado,['borrador','en_revision'],true) && $u->tienePermiso('archivo.gestionar'))
  <div class="col-md-6 mb-3"><div class="card h-100"><div class="card-body"><h3 class="h6">Anular</h3>
    <form method="post" action="{{ route('app.archivo.anular',$m->id) }}" data-confirm="¿Anular esta microforma? No se puede deshacer.">@csrf<div class="form-group"><label for="motivo_anu">Motivo</label><textarea class="form-control" id="motivo_anu" name="motivo" rows="2" required minlength="10" maxlength="1000"></textarea></div><button class="btn btn-outline-danger">Anular</button></form></div></div></div>
@endif
</div>

@if($m->estado==='certificada')
<h2 class="seccion">Disposición de los documentos originales</h2>
<p class="small text-muted">Este sistema solo <strong>registra</strong> la solicitud, la autorización (otra persona, con el acto administrativo) y la ejecución; no elimina nada. La base legal debe ser validada por la asesoría jurídica.</p>
<p>Estado: <strong>{{ ['no_aplica'=>'Sin solicitud (se conservan los originales)','pendiente'=>'Pendiente de autorización','autorizada'=>'Autorizada, falta ejecutar','ejecutada'=>'Ejecutada'][$m->disposicion_estado] }}</strong>@if($m->disposicion_estado!=='no_aplica') · {{ $m->disposicion==='eliminar' ? 'Eliminación' : 'Transferencia' }}@endif @if($m->disposicion_acta) · Acto {{ $m->disposicion_acta }}@endif</p>
@if($m->disposicion_motivo)<p style="white-space:pre-line" class="small">{{ $m->disposicion_motivo }}</p>@endif
@if($m->disposicion_estado==='no_aplica' && $u->tienePermiso('archivo.gestionar'))
<form method="post" action="{{ route('app.archivo.disposicion.solicitar',$m->id) }}" class="col-lg-8 px-0">@csrf
  <div class="form-group"><label for="tipo_disp">Tipo</label><select class="form-control" id="tipo_disp" name="tipo"><option value="eliminar">Eliminación de originales</option><option value="transferir">Transferencia de originales</option></select></div>
  <div class="form-group"><label for="motivo_disp">Fundamento (base legal o reglamentaria)</label><textarea class="form-control" id="motivo_disp" name="motivo" rows="3" required minlength="20" maxlength="1500"></textarea></div>
  <button class="btn btn-outline-primary">Solicitar disposición</button></form>
@endif
@if($m->disposicion_estado==='pendiente' && $u->tienePermiso('archivo.disposicion'))
  @if($esSolicitante)<p class="alert alert-warning small">Doble control: usted solicitó esta disposición; la debe resolver otra persona.</p>
  @else<form method="post" action="{{ route('app.archivo.disposicion.resolver',$m->id) }}" class="col-lg-8 px-0">@csrf
    <div class="form-group"><label for="acta_disp">N.º del acto administrativo que autoriza</label><input class="form-control" id="acta_disp" name="acta" maxlength="60"></div>
    <div class="form-group"><label for="motivo_rech">Motivo (solo si rechaza)</label><textarea class="form-control" id="motivo_rech" name="motivo" rows="2" maxlength="3000"></textarea></div>
    <button class="btn btn-success" name="decision" value="autorizar">Autorizar</button> <button class="btn btn-outline-danger" name="decision" value="rechazar">Rechazar</button></form>@endif
@endif
@if($m->disposicion_estado==='autorizada' && $u->tienePermiso('archivo.gestionar'))
<form method="post" action="{{ route('app.archivo.disposicion.ejecutar',$m->id) }}" data-confirm="¿Confirma que la disposición ya se ejecutó?">@csrf<button class="btn btn-primary">Registrar ejecución</button></form>
@endif
@endif

<h2 class="seccion">Cadena de custodia</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Cadena de custodia</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Evento</th><th scope="col">Persona</th><th scope="col">Detalle</th></tr></thead><tbody>
@foreach($m->eventos->reverse() as $e)
<tr><td class="text-nowrap">{{ $e->created_at?->format('d-m-Y H:i') }}</td><td>{{ str_replace('_',' ',$e->tipo) }}</td><td>{{ $e->usuario?->nombreCompleto() }}</td><td class="small">@php $dt = json_decode((string) $e->detalle, true) ?: []; @endphp @foreach($dt as $k=>$v){{ $k }}: {{ is_array($v) ? implode(', ', $v) : (is_bool($v) ? ($v ? 'sí' : 'no') : $v) }}@if(!$loop->last) · @endif @endforeach</td></tr>
@endforeach</tbody></table></div>
@endsection
