@extends('layouts.app')
@section('titulo', 'Expediente '.$e->codigo)
@section('contenido')
<div class="d-flex justify-content-between"><h1>Expediente {{ $e->codigo }}</h1>
<div><span class="estado estado-{{ $e->estado }}">{{ $e->estado }}</span> <a class="btn btn-sm btn-outline-primary" href="{{ route('app.expediente.exportar',$e) }}">Exportar metadatos (JSON)</a></div></div>
@php $m=$e->metadatos; $s=$e->solicitud; @endphp
<div class="card mb-3"><div class="card-body"><h2 class="h5">Metadatos MGDEE</h2>
<dl class="row mb-0">
<dt class="col-sm-3">Nombre</dt><dd class="col-sm-9">{{ $m['identificacion']['nombre'] ?? '' }}</dd>
<dt class="col-sm-3">Procedimiento (CPAT)</dt><dd class="col-sm-9">{{ $m['procedimiento']['codigo_cpat'] ?? '—' }} · {{ $m['procedimiento']['tramite'] ?? '' }}</dd>
<dt class="col-sm-3">Apertura / cierre</dt><dd class="col-sm-9">{{ $m['temporalidad']['fecha_apertura'] ?? '' }} / {{ $m['temporalidad']['fecha_cierre'] ?? '—' }}</dd>
<dt class="col-sm-3">Seguridad</dt><dd class="col-sm-9">{{ $m['seguridad']['nivel_acceso'] ?? '' }}</dd>
<dt class="col-sm-3">Responsable</dt><dd class="col-sm-9">{{ $e->responsable->nombreCompleto() }} ({{ $e->unidad->nombre }})</dd>
<dt class="col-sm-3">Solicitante</dt><dd class="col-sm-9">{{ $s->nombres }} {{ $s->apellidos }} · {{ $s->run }} · {{ $s->correo }} · notificar por {{ str_replace('_',' ',$s->canal_notificacion) }}</dd>
<dt class="col-sm-3">Detalle</dt><dd class="col-sm-9">{{ $s->detalle }}</dd></dl>
@if($puedeOperar)
<details class="mt-3"><summary>Editar metadatos</summary>
<form method="post" action="{{ route('app.expediente.metadatos',$e) }}" class="mt-2">@csrf
<div class="form-group"><label for="resumen">Descripción</label><textarea class="form-control" id="resumen" name="resumen" maxlength="1000">{{ $m['descripcion']['resumen'] ?? '' }}</textarea></div>
<div class="form-row"><div class="form-group col-md-6"><label for="serie">Serie documental</label><input class="form-control" id="serie" name="serie" value="{{ $m['caracterizacion']['serie_documental'] ?? '' }}"></div>
<div class="form-group col-md-6"><label for="nivel_acceso">Nivel de acceso</label><select class="form-control" id="nivel_acceso" name="nivel_acceso"><option value="reservado" @selected(($m['seguridad']['nivel_acceso']??'')==='reservado')>Reservado</option><option value="publico" @selected(($m['seguridad']['nivel_acceso']??'')==='publico')>Público</option></select></div></div>
<button class="btn btn-sm btn-primary">Guardar metadatos</button></form></details>@endif
</div></div>

<h2 class="h4">Índice del expediente</h2>
<table class="table table-sm"><thead><tr><th>Tipo</th><th>Título</th><th>SHA-256</th></tr></thead><tbody>@foreach($indice as $i)<tr><td>{{ $i['tipo'] }}</td><td>{{ $i['titulo'] }} {{ $i['numero'] ?? '' }}</td><td class="hash">{{ $i['sha256'] }}</td></tr>@endforeach</tbody></table>

<h2 class="h4">Documentos</h2>
<table class="table table-sm"><thead><tr><th>Título</th><th>Tipo</th><th>Estado</th><th>N°</th></tr></thead><tbody>
@forelse($e->documentos as $d)<tr><td><a href="{{ route('app.documento',$d) }}">{{ $d->titulo }}</a></td><td>{{ $d->tipo->nombre }}</td><td><span class="estado estado-{{ $d->estado }}">{{ str_replace('_',' ',$d->estado) }}</span></td><td>{{ $d->numeroFormateado() }}</td></tr>@empty<tr><td colspan="4">Sin documentos.</td></tr>@endforelse</tbody></table>
@if($puedeOperar && $e->estado==='abierto')
<form method="post" action="{{ route('app.documento.store',$e) }}" class="form-inline mb-4">@csrf
<label class="mr-2" for="tipo_documento_id">Nuevo documento</label>
<select class="form-control mr-2" id="tipo_documento_id" name="tipo_documento_id" required>@foreach($tipos as $t)<option value="{{ $t->id }}">{{ $t->nombre }}</option>@endforeach</select>
<label class="mr-2 sr-only" for="plantilla_id">Plantilla</label>
<select class="form-control mr-2" id="plantilla_id" name="plantilla_id"><option value="">Sin plantilla</option>@foreach($plantillas as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach</select>
<label class="sr-only" for="titulo">Título</label><input class="form-control mr-2" id="titulo" name="titulo" placeholder="Título" required maxlength="190">
<button class="btn btn-primary">Crear</button></form>@endif

<h2 class="h4">Archivos del expediente</h2>
<ul>@foreach($e->archivos->where('ambito','!=','comprobante') as $a)<li><a href="{{ route('app.expediente.archivo',[$e,$a]) }}">{{ $a->titulo ?: $a->nombre_original }}</a> <small>({{ $a->ambito }}, {{ $a->nivel_acceso }}{{ $a->sensible?', sensible':'' }})</small></li>@endforeach</ul>
@if($puedeOperar && $e->estado==='abierto')
<form method="post" action="{{ route('app.expediente.subir',$e) }}" enctype="multipart/form-data" class="card card-body mb-4">@csrf
<h3 class="h6">Cargar archivo</h3>
<div class="form-row">
<div class="form-group col-md-4"><label for="f_archivo">Archivo (PDF/JPG/PNG/TIFF)</label><input class="form-control-file" id="f_archivo" type="file" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff"></div>
<div class="form-group col-md-4"><label for="f_titulo">Título</label><input class="form-control" id="f_titulo" name="titulo" required maxlength="190"></div>
<div class="form-group col-md-4"><label for="f_kw">Palabras clave</label><input class="form-control" id="f_kw" name="palabras_clave" maxlength="255"></div></div>
<div class="form-group"><label for="f_res">Resumen</label><input class="form-control" id="f_res" name="resumen" maxlength="1000"></div>
<div class="form-row"><div class="form-group col-md-4"><label for="f_nivel">Nivel de acceso</label><select class="form-control" id="f_nivel" name="nivel_acceso"><option value="reservado">Reservado</option><option value="publico">Público</option></select></div>
<div class="col-md-4 pt-4"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="f_sens" name="sensible" value="1"><label class="custom-control-label" for="f_sens">Contiene datos sensibles</label></div></div></div>
<div><button class="btn btn-sm btn-primary">Cargar</button></div></form>@endif

<h2 class="h4">Comentarios y derivaciones</h2>
@foreach($e->comentarios as $c)<p class="mb-1"><strong>{{ $c->usuario->nombreCompleto() }}</strong> <small>({{ $c->tipo }}, {{ $c->created_at }})</small>: {{ $c->texto }}</p>@endforeach
@foreach($e->derivaciones as $d)<p class="mb-1"><em>Derivado de {{ $d->de->nombreCompleto() }} a {{ $d->a->nombreCompleto() }}: {{ $d->motivo }}</em></p>@endforeach
@if($puedeOperar)
<div class="row mt-3">
<form method="post" action="{{ route('app.expediente.comentar',$e) }}" class="col-md-4">@csrf
<div class="form-group"><label for="c_texto">Comentario u observación</label><textarea class="form-control" id="c_texto" name="texto" required maxlength="2000"></textarea></div>
<select class="form-control mb-2" name="tipo" aria-label="Tipo"><option value="comentario">Comentario</option><option value="observacion">Observación</option></select><button class="btn btn-sm btn-primary">Agregar</button></form>
<form method="post" action="{{ route('app.expediente.derivar',$e) }}" class="col-md-4">@csrf
<div class="form-group"><label for="d_a">Derivar a</label><select class="form-control" id="d_a" name="a_usuario_id" required>@foreach($usuarios as $u)<option value="{{ $u->id }}">{{ $u->nombreCompleto() }} ({{ $u->rol }})</option>@endforeach</select></div>
<div class="form-group"><label for="d_m">Motivo</label><input class="form-control" id="d_m" name="motivo" required maxlength="500"></div><button class="btn btn-sm btn-primary">Derivar</button></form>
@if($e->estado==='abierto' && $s->estado==='en_tramite')
<form method="post" action="{{ route('app.expediente.devolver',$e) }}" class="col-md-4">@csrf
<div class="form-group"><label for="v_o">Devolver al ciudadano: observación</label><textarea class="form-control" id="v_o" name="observacion" required minlength="5" maxlength="2000"></textarea></div>
<div class="form-group"><label for="v_p">Plazo (1 a 90 días)</label><input class="form-control" id="v_p" type="number" min="1" max="90" name="plazo_dias" value="10" required></div><button class="btn btn-sm btn-warning">Devolver</button></form>@endif
</div>
<div class="mt-3">
@if($e->estado==='abierto')<form method="post" action="{{ route('app.expediente.cerrar',$e) }}" class="d-inline" data-confirm="¿Cerrar el expediente?">@csrf<button class="btn btn-outline-danger">Cerrar expediente</button></form>
@else<form method="post" action="{{ route('app.expediente.reabrir',$e) }}" class="form-inline">@csrf<label class="mr-2" for="r_m">Motivo de reapertura</label><input class="form-control mr-2" id="r_m" name="motivo" required minlength="5" maxlength="500"><button class="btn btn-outline-primary">Reabrir</button></form>@endif
</div>
@endif

@if($e->estado==='cerrado')
<h2 class="h4 mt-4">Notificación al ciudadano</h2>
<p class="small text-muted">Las notificaciones quedan <strong>pendientes</strong> hasta registrar evidencia real de envío. El sistema no afirma haber enviado nada sin evidencia.</p>
@if($puedeOperar)
<form method="post" action="{{ route('app.expediente.notificar',$e) }}" class="card card-body mb-3">@csrf
<fieldset><legend class="h6">Documentos a notificar</legend>
@foreach($e->documentos->where('estado','vigente') as $d)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="n_{{ $d->id }}" name="documentos[]" value="{{ $d->id }}"><label class="custom-control-label" for="n_{{ $d->id }}">{{ $d->numeroFormateado() }} — {{ $d->titulo }}</label></div>@endforeach</fieldset>
<div class="form-group mt-2"><label for="n_canal">Canal</label><select class="form-control col-md-4" id="n_canal" name="canal">@foreach(['casilla_unica'=>'Casilla Única','correo'=>'Correo (aviso con enlace)','presencial'=>'Soporte físico / presencial'] as $v=>$l)<option value="{{ $v }}" @selected($s->canal_notificacion===$v)>{{ $l }}</option>@endforeach</select></div>
<div><button class="btn btn-primary">Crear notificaciones</button></div></form>@endif
<table class="table table-sm"><thead><tr><th>Documento</th><th>Canal</th><th>Estado</th><th>Evidencia</th></tr></thead><tbody>
@foreach($e->notificaciones as $n)<tr><td>{{ $n->documento?->numeroFormateado() }}</td><td>{{ str_replace('_',' ',$n->canal) }}</td><td><span class="estado estado-{{ $n->estado }}">{{ $n->estado }}</span> @if($n->ultimo_error)<small>{{ $n->ultimo_error }}</small>@endif</td>
<td>@if($n->evidencia){{ $n->evidencia_tipo }}: <span class="hash">{{ $n->evidencia }}</span>@elseif($puedeOperar && $n->canal!=='correo' && $n->estado!=='enviada')
<form method="post" action="{{ route('app.notificacion.evidencia',$n) }}" class="form-inline">@csrf<label class="sr-only" for="ev{{ $n->id }}">Evidencia</label>
<select class="form-control form-control-sm mr-1" name="tipo" aria-label="Tipo de evidencia"><option value="id_casilla_unica">ID Casilla Única</option><option value="acta_presencial">Acta presencial</option></select>
<input class="form-control form-control-sm mr-1" id="ev{{ $n->id }}" name="valor" required maxlength="250" placeholder="Referencia"><button class="btn btn-sm btn-primary">Registrar</button></form>@else — @endif</td></tr>@endforeach</tbody></table>
@endif
@endsection
