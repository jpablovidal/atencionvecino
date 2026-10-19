@extends('layouts.app')
@section('titulo', 'Expediente '.$e->codigo)
@section('contenido')
<div class="d-flex justify-content-between"><h1>Expediente {{ $e->codigo }}</h1>
<div><span class="estado estado-{{ $e->estado }}">{{ $e->estado }}</span> <a class="btn btn-sm btn-outline-primary" href="{{ route('app.expediente.exportar',$e) }}">Exportar metadatos (JSON)</a></div></div>
@php $m=$e->metadatos; $s=$e->solicitud; @endphp
@if($entrante)
<div class="card border-info mb-3"><div class="card-body">
<h2 class="h5">Comunicación oficial recibida por DocDigital</h2>
<p class="mb-1"><strong>{{ $entrante->asunto }}</strong> — de {{ $entrante->institucion_nombre ?? $entrante->institucion_codigo ?? 'institución no informada' }} · ID DocDigital <span class="hash">{{ $entrante->evidencia }}</span> · recibida {{ $entrante->evidencia_en }}</p>
<p class="mb-2">Documento original: @foreach($e->archivos->where('ambito','docdigital') as $a)@if(in_array($a->id, $ocultos['archivos'], true))<em>archivo restringido</em>@continue @endif<a href="{{ route('app.expediente.archivo',[$e,$a]) }}">{{ $a->nombre_original }}</a> <span class="hash">{{ $a->sha256 }}</span> @include('partials.firma_resultado', ['estado' => $a->firma_estado, 'det' => $a->firma()])@endforeach</p>
@if($entrante->anexos->isNotEmpty())<p class="mb-2"><strong>Anexos recibidos:</strong>
@foreach($entrante->anexos as $x)@if($x->estado==='recibido' && $x->archivo_id && ! in_array($x->archivo_id, $ocultos['archivos'], true))<a href="{{ route('app.expediente.archivo.ver',[$e,$x->archivo_id]) }}" data-visor data-visor-mime="{{ $x->archivo?->mime }}" data-visor-titulo="{{ $x->nombre }}" data-visor-descarga="{{ route('app.expediente.archivo',[$e,$x->archivo_id]) }}">{{ $x->nombre }}</a>@elseif($x->estado==='omitido')<span class="text-muted">{{ $x->nombre }} <em>(no se guardó: {{ $x->motivo }})</em></span>@else<span class="text-muted">{{ $x->nombre }}</span>@endif @unless($loop->last) · @endunless @endforeach</p>@endif
@if($entrante->acuse_estado==='devuelto')
<p class="mb-0"><span class="estado estado-fallida">devuelto a la entidad de origen</span> {{ $entrante->acuse_en }} · <span class="hash">{{ $entrante->acuse_evidencia }}</span></p>
@elseif($entrante->acuse_estado==='registrado')
<p class="mb-0"><span class="estado estado-vigente">acuse de recibo registrado</span> ({{ $entrante->acuse_tipo }}) {{ $entrante->acuse_en }} · <span class="hash">{{ $entrante->acuse_evidencia }}</span>@if($entrante->acuse_tipo==='simulado') <span class="sim">SIMULADO – no enviado realmente</span>@endif</p>
@else
<p class="mb-2"><span class="estado estado-pendiente">acuse de recibo pendiente</span> — debe acusar recibo antes de gestionar y responder.</p>
@if($puedeOperar)
@if($docModo==='outbox')
<form method="post" action="{{ route('app.comunicacion.acuse',$entrante) }}" class="form-inline">@csrf<label class="mr-2" for="ac_ev">Referencia del acuse emitido en DocDigital</label><input class="form-control form-control-sm mr-2" id="ac_ev" name="evidencia" required maxlength="200"><button class="btn btn-sm btn-primary">Registrar acuse</button></form>
@else
<form method="post" action="{{ route('app.comunicacion.acuse',$entrante) }}" class="d-inline-block mr-3">@csrf<button class="btn btn-sm btn-primary">Acusar recibo por DocDigital</button></form>
<form method="post" action="{{ route('app.comunicacion.devolver',$entrante) }}" class="form-inline mt-2" data-confirm="¿Devolver este documento a la entidad de origen? Quedará registrado en DocDigital.">@csrf<label class="mr-2" for="dv_m">Devolver a la entidad de origen: motivo</label><input class="form-control form-control-sm mr-2" id="dv_m" name="motivo" required minlength="3" maxlength="255"><button class="btn btn-sm btn-outline-danger">Devolver</button></form>
@endif
@endif
@endif
@if($puedeOperar && ! $e->responsable_id)<form method="post" action="{{ route('app.expediente.asignarme',$e) }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-primary">Asignarme este expediente</button></form>@endif
</div></div>
@endif
@if(!empty($restr))@include('app.restringidos._expediente', ['r' => $restr])@endif
@if(!empty($transp))@include('app.transparencia._expediente', ['t' => $transp])@endif
<div class="card mb-3"><div class="card-body"><h2 class="h5">Metadatos MGDEE</h2>
<dl class="row mb-0">
<dt class="col-sm-3">Nombre</dt><dd class="col-sm-9">{{ $m['identificacion']['nombre'] ?? '' }}</dd>
<dt class="col-sm-3">Procedimiento (CPAT)</dt><dd class="col-sm-9">{{ $m['procedimiento']['codigo_cpat'] ?? '—' }} · {{ $m['procedimiento']['tramite'] ?? '' }}</dd>
<dt class="col-sm-3">Apertura / cierre</dt><dd class="col-sm-9">{{ $m['temporalidad']['fecha_apertura'] ?? '' }} / {{ $m['temporalidad']['fecha_cierre'] ?? '—' }}</dd>
<dt class="col-sm-3">Seguridad</dt><dd class="col-sm-9">{{ $m['seguridad']['nivel_acceso'] ?? '' }}</dd>
<dt class="col-sm-3">Identificador único</dt><dd class="col-sm-9"><strong>{{ $m['identificacion']['identificador_unico'] ?? $e->codigo }}</strong> · origen: {{ $e->origen==='docdigital' ? 'comunicación oficial (DocDigital)' : 'solicitud ciudadana' }}</dd>
<dt class="col-sm-3">Responsable</dt><dd class="col-sm-9">{{ $e->responsable?->nombreCompleto() ?? 'Sin asignar' }} ({{ $e->unidad->nombre }})</dd>
@if($s)
<dt class="col-sm-3">Solicitante</dt><dd class="col-sm-9">{{ $s->nombres }} {{ $s->apellidos }} · {{ $s->run }} · {{ $s->correo }} · notificar por {{ str_replace('_',' ',$s->canal_notificacion) }}</dd>
<dt class="col-sm-3">Detalle</dt><dd class="col-sm-9">{{ $s->detalle }}</dd>
@endif
</dl>
@if($puedeOperar)
<details class="mt-3"><summary>Editar metadatos</summary>
<form method="post" action="{{ route('app.expediente.metadatos',$e) }}" class="mt-2">@csrf
<div class="form-group"><label for="resumen">Descripción</label><textarea class="form-control" id="resumen" name="resumen" maxlength="1000">{{ $m['descripcion']['resumen'] ?? '' }}</textarea></div>
<div class="form-row"><div class="form-group col-md-6"><label for="serie">Serie documental</label><input class="form-control" id="serie" name="serie" value="{{ $m['caracterizacion']['serie_documental'] ?? '' }}"></div>
<div class="form-group col-md-6"><label for="nivel_acceso">Nivel de acceso</label><select class="form-control" id="nivel_acceso" name="nivel_acceso"><option value="reservado" @selected(($m['seguridad']['nivel_acceso']??'')==='reservado')>Reservado</option><option value="publico" @selected(($m['seguridad']['nivel_acceso']??'')==='publico')>Público</option></select></div></div>
<button class="btn btn-sm btn-primary">Guardar metadatos</button></form></details>@endif
</div></div>

<h2 class="h4">Índice del expediente</h2>
<table class="table table-sm"><thead><tr><th scope="col">Tipo</th><th scope="col">Título</th><th scope="col">SHA-256</th></tr></thead><tbody>@foreach($indice as $i)<tr><td>{{ $i['tipo'] }}</td><td>@if(!empty($i['restringido']))<em>{{ $i['titulo'] }}</em>@else{{ $i['titulo'] }} {{ $i['numero'] ?? '' }}@endif</td><td class="hash">{{ $i['sha256'] ?? '—' }}</td></tr>@endforeach</tbody></table>

<h2 class="h4">Documentos</h2>
<table class="table table-sm"><thead><tr><th scope="col">Título</th><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">N°</th></tr></thead><tbody>
@forelse($e->documentos as $d)@if(in_array($d->id, $ocultos['documentos'], true))<tr><td><em>Documento restringido</em> <span class="chip chip-claro">acceso limitado</span></td><td>—</td><td>—</td><td>—</td></tr>@continue @endif<tr><td><a href="{{ route('app.documento',$d) }}">{{ $d->titulo }}</a> @if($d->pdf_ruta)<a class="small" href="{{ route('app.documento.pdf',$d) }}" data-visor data-visor-mime="application/pdf" data-visor-titulo="{{ $d->titulo }}" data-visor-descarga="{{ route('app.documento.pdf',$d) }}">Ver PDF<span class="sr-only"> de {{ $d->titulo }}</span></a> @endif<a class="small" href="{{ route('app.expediente.documento.historial',[$e,$d]) }}">Historial<span class="sr-only"> de {{ $d->titulo }}</span></a>@if(in_array($d->id, $ocultos['restringidos_documentos'], true)) <span class="chip chip-claro">restringido</span>@endif</td><td>{{ $d->tipo->nombre }}</td><td><span class="estado estado-{{ $d->estado }}">{{ str_replace('_',' ',$d->estado) }}</span></td><td>{{ $d->numeroFormateado() }}</td></tr>@empty<tr><td colspan="4">Sin documentos.</td></tr>@endforelse</tbody></table>
@if($puedeOperar && $e->estado==='abierto')
<form method="post" action="{{ route('app.documento.store',$e) }}" class="form-inline mb-4">@csrf
<label class="mr-2" for="tipo_documento_id">Nuevo documento</label>
<select class="form-control mr-2" id="tipo_documento_id" name="tipo_documento_id" required>@foreach($tipos as $t)<option value="{{ $t->id }}">{{ $t->nombre }}</option>@endforeach</select>
<label class="mr-2 sr-only" for="plantilla_id">Plantilla</label>
<select class="form-control mr-2" id="plantilla_id" name="plantilla_id"><option value="">Sin plantilla</option>@foreach($plantillas as $p)<option value="{{ $p->id }}" data-tipo="{{ $p->tipo_documento_id }}">{{ $p->nombre }}</option>@endforeach</select>
<label class="sr-only" for="titulo">Título</label><input class="form-control mr-2" id="titulo" name="titulo" placeholder="Título" required maxlength="190">
<button class="btn btn-primary">Crear</button></form>@endif

<h2 class="h4">Archivos del expediente</h2>
<ul>@foreach($e->archivos->where('ambito','!=','comprobante') as $a)@if(in_array($a->id, $ocultos['archivos'], true))<li><em>Archivo restringido</em> <span class="chip chip-claro">acceso limitado</span></li>@continue @endif<li><a href="{{ route('app.expediente.archivo.ver',[$e,$a]) }}" data-visor data-visor-mime="{{ $a->mime }}" data-visor-titulo="{{ $a->titulo ?: $a->nombre_original }}" data-visor-descarga="{{ route('app.expediente.archivo',[$e,$a]) }}">{{ $a->titulo ?: $a->nombre_original }}</a> <span class="chip chip-claro">v{{ $a->version }}</span> <a class="small" href="{{ route('app.expediente.archivo.versiones',[$e,$a]) }}">Versiones<span class="sr-only"> de {{ $a->titulo ?: $a->nombre_original }}</span></a>@if(in_array($a->id, $ocultos['restringidos_archivos'], true)) <span class="chip chip-claro">restringido</span>@endif <small>({{ $a->ambito }}, {{ $a->nivel_acceso }}{{ $a->sensible?', sensible':'' }})</small> @if($a->mime === 'application/pdf')@include('partials.firma_resultado', ['estado' => $a->firma_estado, 'det' => $a->firma()])@endif</li>@endforeach</ul>
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
@if($s && $e->estado==='abierto' && $s->estado==='en_tramite')
<form method="post" action="{{ route('app.expediente.devolver',$e) }}" class="col-md-4">@csrf
<div class="form-group"><label for="v_o">Devolver al ciudadano: observación</label><textarea class="form-control" id="v_o" name="observacion" required minlength="5" maxlength="2000"></textarea></div>
<div class="form-group"><label for="v_p">Plazo (1 a 90 días)</label><input class="form-control" id="v_p" type="number" min="1" max="90" name="plazo_dias" value="10" required></div><button class="btn btn-sm btn-warning">Devolver</button></form>@endif
</div>
<div class="mt-3">
@if($e->estado==='abierto')<form method="post" action="{{ route('app.expediente.cerrar',$e) }}" class="d-inline" data-confirm="¿Cerrar el expediente?">@csrf<button class="btn btn-outline-danger">Cerrar expediente</button></form>
@else<form method="post" action="{{ route('app.expediente.reabrir',$e) }}" class="form-inline">@csrf<label class="mr-2" for="r_m">Motivo de reapertura</label><input class="form-control mr-2" id="r_m" name="motivo" required minlength="5" maxlength="500"><button class="btn btn-outline-primary">Reabrir</button></form>@endif
</div>
@endif

<h2 class="h4 mt-4">Comunicaciones oficiales (DocDigital)</h2>
@if($docModo==='outbox')<p class="small text-muted">Modo <strong>outbox</strong>: el sistema no envía; la comunicación queda <strong>pendiente</strong> hasta que usted registre el folio obtenido en DocDigital.</p>
@elseif($docModo==='mock')<p class="small sim">MODO SIMULADO (solo desarrollo): no se envía nada realmente.</p>@endif
@if($puedeOperar)
<form method="post" action="{{ route('app.comunicacion.crear',$e) }}" class="card card-body mb-3">@csrf
<div class="form-row">
<div class="form-group col-md-4"><label for="cd_doc">Documento vigente</label><select class="form-control" id="cd_doc" name="documento_id" required>@foreach($e->documentos->where('estado','vigente') as $d)<option value="{{ $d->id }}">{{ $d->numeroFormateado() }} — {{ $d->titulo }}</option>@endforeach</select></div>
<div class="form-group col-md-4"><label for="cd_inst">Institución destinataria (Codificador)</label><select class="form-control" id="cd_inst" name="institucion_codigo" required>@foreach($instituciones as $i)<option value="{{ $i->codigo }}" @selected($i->codigo===$origenCodigo)>{{ $i->nombre }} ({{ $i->codigo }})</option>@endforeach</select></div>
<div class="form-group col-md-4"><label for="cd_asunto">Asunto</label><input class="form-control" id="cd_asunto" name="asunto" required minlength="3" maxlength="190"></div></div>
@php $anexables = $e->archivos->where('ambito','!=','comprobante')->reject(fn ($aa) => in_array($aa->id, $ocultos['archivos'], true)); @endphp
@if($anexables->isNotEmpty())<fieldset class="form-group"><legend class="h6">Anexos que se envían con el documento (opcional, hasta {{ (int) config('sgd.docdigital.max_anexos', 10) }}, máx. 50 MB c/u)</legend>
@foreach($anexables as $aa)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="cd_ax{{ $aa->id }}" name="anexos[]" value="{{ $aa->id }}" @disabled($aa->tamano > 50 * 1048576)><label class="custom-control-label" for="cd_ax{{ $aa->id }}">{{ $aa->titulo ?: $aa->nombre_original }} <small class="text-muted">({{ $aa->nombre_original }}, {{ number_format($aa->tamano / 1024, 0, ',', '.') }} KB)</small></label></div>@endforeach</fieldset>@endif
<div><button class="btn btn-primary">Crear comunicación</button></div></form>@endif
<table class="table table-sm"><thead><tr><th scope="col">Documento</th><th scope="col">Destino</th><th scope="col">Asunto</th><th scope="col">Estado</th><th scope="col">Evidencia</th></tr></thead><tbody>
@forelse($comunicaciones as $c)<tr><td>{{ $c->documento?->numeroFormateado() }}</td><td>{{ $c->institucion_nombre }}</td><td>{{ $c->asunto }}@if($c->responde_a_id) <small class="text-muted">(en respuesta a la comunicación recibida)</small>@endif @if($c->anexos->isNotEmpty())<br><small class="text-muted">Anexos: {{ $c->anexos->pluck('nombre')->implode(', ') }}</small>@endif</td>
<td><span class="estado estado-{{ $c->estado }}">{{ $c->estado }}</span> @if($c->ultimo_error)<small>{{ $c->ultimo_error }}</small>@endif</td>
<td>@if($c->evidencia){{ $c->evidencia_tipo }}: <span class="hash">{{ $c->evidencia }}</span>@if($c->evidencia_tipo==='simulado') <span class="sim">SIMULADO – no enviado realmente</span>@endif
@elseif($puedeOperar && $c->estado==='fallida' && $docModo!=='outbox')<form method="post" action="{{ route('app.comunicacion.reintentar',$c) }}">@csrf<button class="btn btn-sm btn-outline-primary">Reintentar</button></form>
@elseif($puedeOperar && $docModo==='outbox')<form method="post" action="{{ route('app.comunicacion.evidencia',$c) }}" class="form-inline">@csrf<label class="sr-only" for="fo{{ $c->id }}">Folio DocDigital</label><input class="form-control form-control-sm mr-1" id="fo{{ $c->id }}" name="folio" required maxlength="120" placeholder="Folio DocDigital"><button class="btn btn-sm btn-primary">Registrar</button></form>@else — @endif</td></tr>
@empty<tr><td colspan="5">Sin comunicaciones.</td></tr>@endforelse</tbody></table>

<h2 class="h4 mt-4">Consultas a servicios del Estado (PISEE)</h2>
@if(! $piseeOn)<p class="small text-muted">La integración PISEE no está habilitada en este ambiente.</p>
@else
@if($piseeMock)<p class="small sim">MODO SIMULADO (solo desarrollo): los datos no provienen de PISEE.</p>@endif
<p class="small text-muted">Solo se consulta el RUN del solicitante de este expediente, con finalidad obligatoria. El resultado se guarda cifrado y cada consulta queda auditada.</p>
@if($puedeOperar && $e->estado==='abierto')
<form method="post" action="{{ route('app.pisee.consultar',$e) }}" class="card card-body mb-3">@csrf
<div class="form-row"><div class="form-group col-md-5"><label for="ps_s">Servicio</label><select class="form-control" id="ps_s" name="servicio_id" required>@foreach($servicios as $s)<option value="{{ $s->id }}">{{ $s->nombre }}</option>@endforeach</select></div>
<div class="form-group col-md-7"><label for="ps_f">Finalidad de la consulta</label><input class="form-control" id="ps_f" name="finalidad" required minlength="10" maxlength="255" placeholder="Ej.: verificar antecedentes para resolver la solicitud"></div></div>
<div><button class="btn btn-primary">Consultar</button></div></form>@endif
<table class="table table-sm"><thead><tr><th scope="col">Fecha</th><th scope="col">Servicio</th><th scope="col">Funcionario</th><th scope="col">Finalidad</th><th scope="col">Resultado</th></tr></thead><tbody>
@forelse($consultas as $c)<tr><td>{{ $c->created_at }}</td><td>{{ $c->servicio->nombre }}</td><td>{{ $c->usuario->nombreCompleto() }}</td><td>{{ $c->finalidad }}</td>
<td>@if($c->archivo_id)<a href="{{ route('app.expediente.archivo',[$e,$c->archivo_id]) }}">Ver resultado (JSON)</a>@else<span class="estado estado-fallida">{{ $c->error }}</span>@endif</td></tr>@empty<tr><td colspan="5">Sin consultas.</td></tr>@endforelse</tbody></table>
@endif

<h2 class="h4 mt-4" id="asociados">Expedientes y microformas asociados</h2>
<p class="small text-muted">Permite reutilizar procesos y antecedentes de otros expedientes o del archivo municipal: se asocian (queda registrado quién y por qué) y se pueden incorporar copias de su contenido, con su origen y huella.</p>
@if($vinculos->isNotEmpty())<ul>@foreach($vinculos as $vv)<li>@if($vv->tipo==='microforma')Microforma {{ $vv->microforma?->codigo ?: '#'.$vv->microforma_id }} — {{ $vv->microforma?->titulo }}@else Expediente {{ $vv->origen?->codigo }}@endif
 · <a href="{{ route('app.vinculos.ver', $vv) }}">ver y incorporar contenido<span class="sr-only"> de {{ $vv->tipo==='microforma' ? 'la microforma' : 'el expediente '.$vv->origen?->codigo }}</span></a> <small>({{ $vv->motivo }})</small></li>@endforeach</ul>@else<p>No hay expedientes ni microformas asociados.</p>@endif
@if($copias->isNotEmpty())<p class="small mb-1">Copias incorporadas: @foreach($copias as $cp)<span class="chip chip-claro" title="{{ $cp->origen_descripcion }}">{{ $cp->titulo }}</span> @endforeach</p>@endif
@if($referenciadoPor->isNotEmpty())<p class="small mb-1">Este expediente fue reutilizado en: @foreach($referenciadoPor as $rp)<a href="{{ route('app.expediente', $rp->expediente) }}">{{ $rp->expediente->codigo }}</a>@unless($loop->last), @endunless @endforeach.</p>@endif
@if($puedeOperar && $e->estado==='abierto' && auth()->user()->tienePermiso('expedientes.vincular'))<p><a class="btn btn-outline-primary" href="{{ route('app.vinculos.buscar', $e) }}">Buscar y asociar expedientes o microformas</a></p>@endif
@if($decretoPago)<p><strong>Decreto de pago:</strong> <a href="{{ route('app.pagos.ver', $decretoPago) }}">{{ $decretoPago->proveedor_nombre }} · ${{ number_format($decretoPago->monto, 0, ',', '.') }}</a> ({{ \App\Models\DecretoPago::FASES[$decretoPago->fase()] }})</p>
@elseif($puedeOperar && $e->estado==='abierto' && auth()->user()->tienePermiso('decretopago.gestionar'))<p><a class="btn btn-outline-primary" href="{{ route('app.pagos.nuevo', ['expediente' => $e->id]) }}">Crear decreto de pago para este expediente</a></p>@endif

@if($s)
<h2 class="h4 mt-4" id="respuesta-final">Respuesta final al solicitante</h2>
<p class="small text-muted">Quien gestionó la solicitud indica <strong>qué documentos y archivos del expediente</strong> se entregan; la persona encargada de responder (p. ej. Oficina de Partes) los notifica por <strong>Casilla Única con copia al correo</strong> o, excepcionalmente, con entrega física. El sistema no afirma haber enviado nada sin evidencia.</p>
@foreach($e->respuestas as $rf)<div class="card card-body mb-2"><div><strong>Respuesta #{{ $rf->id }}</strong> · <span class="estado estado-{{ $rf->estado }}">{{ $rf->estado }}</span> · {{ \App\Services\RespuestaFinalService::CANALES[$rf->canal] ?? $rf->canal }} · instruida el {{ $rf->created_at->setTimezone('America/Santiago')->format('d-m-Y H:i') }} por {{ $rf->instructor?->nombreCompleto() }}
@if($rf->estado==='despachada') · entrega: <strong>{{ str_replace('_',' ',$rf->entrega()) }}</strong>@endif @if($rf->estado==='anulada') · {{ $rf->anulada_motivo }}@endif</div>
<ul class="mb-1">@foreach($rf->items as $it)<li>@if($it->documento){{ $it->documento->numeroFormateado() }} — {{ $it->documento->titulo }}@elseif($it->archivo)Archivo: {{ $it->archivo->titulo ?: $it->archivo->nombre_original }}@endif</li>@endforeach</ul>
@if($rf->instruccion)<div class="small">Indicaciones: {{ $rf->instruccion }}</div>@endif
@if($rf->estado==='instruida' && $puedeOperar && auth()->user()->tienePermiso('respuestas.instruir'))<form method="post" action="{{ route('app.respuestas.anular',$rf) }}" class="form-inline mt-2">@csrf<label class="sr-only" for="an{{ $rf->id }}">Motivo de la anulación</label><input class="form-control form-control-sm mr-2" id="an{{ $rf->id }}" name="motivo" required maxlength="300" placeholder="Motivo de la anulación"><button class="btn btn-sm btn-outline-secondary">Anular instrucción</button></form>@endif</div>@endforeach
@if($puedeOperar && auth()->user()->tienePermiso('respuestas.instruir') && ! $e->respuestas->contains('estado','instruida'))
@php $entregables = $e->archivos->whereIn('ambito', \App\Services\RespuestaFinalService::AMBITOS)->reject(fn ($aa) => in_array($aa->id, $ocultos['archivos'], true)); @endphp
<form method="post" action="{{ route('app.respuestas.instruir',$e) }}" class="card card-body mb-3" data-pasos>@csrf
<fieldset data-paso="Documentos"><legend class="h6">1. Documentos y archivos que se entregarán al solicitante</legend>
@foreach($e->documentos->where('estado','vigente')->reject(fn ($dd) => in_array($dd->id, $ocultos['documentos'], true)) as $d)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="rd_{{ $d->id }}" name="documentos[]" value="{{ $d->id }}"><label class="custom-control-label" for="rd_{{ $d->id }}">Documento {{ $d->numeroFormateado() }} — {{ $d->titulo }}</label></div>@endforeach
@foreach($entregables as $aa)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="ra_{{ $aa->id }}" name="archivos[]" value="{{ $aa->id }}"><label class="custom-control-label" for="ra_{{ $aa->id }}">Archivo: {{ $aa->titulo ?: $aa->nombre_original }} <small>({{ $aa->ambito }})</small></label></div>@endforeach
@if($e->documentos->where('estado','vigente')->isEmpty() && $entregables->isEmpty())<p class="mb-0">Aún no hay documentos vigentes ni archivos entregables en el expediente.</p>@endif</fieldset>
<fieldset data-paso="Canal de notificación"><legend class="h6">2. Cómo se notificará</legend>
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="rc_cu" name="canal" value="casilla_unica" checked><label class="custom-control-label" for="rc_cu">Casilla Única (notificador), con copia al correo {{ $s->correo }}</label></div>
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="rc_pr" name="canal" value="presencial"><label class="custom-control-label" for="rc_pr">Entrega física de los documentos (excepcional)</label></div>
<div class="form-group mt-2"><label for="rc_mot">Si es entrega física, motivo de la excepción</label><input class="form-control" id="rc_mot" name="motivo_presencial" maxlength="300"></div></fieldset>
<fieldset data-paso="Confirmación"><legend class="h6">3. Indicaciones y confirmación</legend>
<div class="form-group"><label for="rc_ins">Indicaciones para quien responde (opcional)</label><textarea class="form-control" id="rc_ins" name="instruccion" rows="2" maxlength="600"></textarea></div>
<div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="rc_ok" name="confirmo" value="1" required><label class="custom-control-label" for="rc_ok">Confirmo que enviaré la respuesta final de notificación de esta solicitud con los documentos indicados.</label></div>
</fieldset>
<button class="btn btn-primary">Indicar respuesta final</button></form>
@elseif($e->respuestas->isEmpty())<p class="small text-muted">Aún no se ha indicado la respuesta final.</p>@endif
<table class="table table-sm"><thead><tr><th scope="col">Documento</th><th scope="col">Canal</th><th scope="col">Estado</th><th scope="col">Evidencia</th></tr></thead><tbody>
@foreach($e->notificaciones as $n)<tr><td>@if($n->documento){{ $n->documento->numeroFormateado() }}@elseif($n->archivo){{ $n->archivo->titulo ?: $n->archivo->nombre_original }}@else Aviso por correo @endif</td><td>{{ str_replace('_',' ',$n->canal) }}</td><td><span class="estado estado-{{ $n->estado }}">{{ $n->estado }}</span> @if($n->ultimo_error)<small>{{ $n->ultimo_error }}</small>@endif</td>
<td>@if($n->evidencia){{ $n->evidencia_tipo }}: <span class="hash">{{ $n->evidencia }}</span>@elseif($puedeOperar && $n->canal!=='correo' && $n->estado!=='enviada')
<form method="post" action="{{ route('app.notificacion.evidencia',$n) }}" class="form-inline">@csrf<label class="sr-only" for="ev{{ $n->id }}">Evidencia</label>
<select class="form-control form-control-sm mr-1" name="tipo" aria-label="Tipo de evidencia"><option value="id_casilla_unica">ID Casilla Única</option><option value="acta_presencial">Acta presencial</option></select>
<input class="form-control form-control-sm mr-1" id="ev{{ $n->id }}" name="valor" required maxlength="250" placeholder="Referencia"><button class="btn btn-sm btn-primary">Registrar</button></form>@else — @endif</td></tr>@endforeach</tbody></table>
@endif
@include('partials.visor_modal')
@endsection
