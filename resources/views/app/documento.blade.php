@extends('layouts.app')
@section('titulo', $d->titulo)
@section('contenido')
<p><a href="{{ route('app.expediente',$d->expediente_id) }}">&larr; Expediente {{ $d->expediente->codigo }}</a></p>
<h1>{{ $d->titulo }}</h1>
<p><span class="estado estado-{{ $d->estado }}">{{ str_replace('_',' ',$d->estado) }}</span> · {{ $d->tipo->nombre }} · versión {{ $d->version_actual }} · flujo {{ $d->flujo }}
@if($d->numero) · <strong>{{ $d->numeroFormateado() }}</strong> · código <strong>{{ $d->codigo_validacion }}</strong> @endif</p>

@if($d->estado==='vigente')
<div class="card mb-3 border-success"><div class="card-body">
<p class="mb-1">Fecha de firma: {{ $d->fecha_firma->timezone('America/Santiago')->format('d-m-Y H:i') }} (hora de Chile)</p>
<p class="mb-1">Validación pública: <a href="{{ route('validar',$d->codigo_validacion) }}">{{ route('validar',$d->codigo_validacion) }}</a></p>
<p class="mb-1">SHA-256 de la copia: <span class="hash">{{ $d->pdf_sha256 }}</span></p>
<a class="btn btn-sm btn-primary" href="{{ route('app.documento.pdf',$d) }}">Ver copia pública (PDF)</a></div></div>
@else
<a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('app.documento.pdf',$d) }}">Ver PDF de la versión actual</a>
@endif

@if($d->editable() && $puedeOperar)
<form method="post" action="{{ route('app.documento.guardar',$d) }}" class="mb-4">@csrf
<div class="form-group"><label for="titulo">Título</label><input class="form-control" id="titulo" name="titulo" value="{{ old('titulo',$d->titulo) }}" required maxlength="190"></div>
<div class="form-group"><label for="html">Contenido</label><textarea id="html" name="html" data-editor rows="14">{{ old('html',$d->html) }}</textarea></div>
<div class="form-row">
<div class="form-group col-md-6"><label for="resumen">Resumen (MGDDE)</label><input class="form-control" id="resumen" name="resumen" maxlength="1000" value="{{ old('resumen',$d->resumen) }}"></div>
<div class="form-group col-md-6"><label for="palabras_clave">Palabras clave</label><input class="form-control" id="palabras_clave" name="palabras_clave" maxlength="255" value="{{ old('palabras_clave',$d->palabras_clave) }}"></div></div>
<div class="form-row"><div class="form-group col-md-4"><label for="nivel_acceso">Nivel de acceso</label><select class="form-control" id="nivel_acceso" name="nivel_acceso"><option value="reservado" @selected($d->nivel_acceso==='reservado')>Reservado</option><option value="publico" @selected($d->nivel_acceso==='publico')>Público</option></select></div>
<div class="col-md-4 pt-4"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="sensible" name="sensible" value="1" @checked($d->sensible)><label class="custom-control-label" for="sensible">Contiene datos sensibles</label></div></div></div>
<button class="btn btn-primary">Guardar nueva versión</button>
</form>
@if($llm)
<div class="card mb-4"><div class="card-body"><h2 class="h5">Asistente de redacción (IA)</h2>
<p class="small text-muted">Genera un borrador de texto. No guarda ni firma: usted debe revisarlo e insertarlo. No inventa normas ni numeración ([COMPLETAR]). Se envía solo el texto del borrador con RUN, correos y teléfonos redactados.</p>
<label for="llm-instruccion">¿Qué desea redactar?</label><textarea class="form-control mb-2" id="llm-instruccion" rows="2" maxlength="1000"></textarea>
<button type="button" class="btn btn-sm btn-outline-primary" id="llm-generar" data-url="{{ route('app.llm') }}">Generar borrador</button>
<div id="llm-salida" class="mt-2 border p-2" aria-live="polite"></div>
<button type="button" class="btn btn-sm btn-primary mt-2" id="llm-insertar" hidden>Insertar en el documento</button></div></div>
@endif
@else
<div class="card mb-4"><div class="card-body">{!! $d->html !!}</div></div>
@endif

<h2 class="h4">Flujo de visación y firma</h2>
@if($d->pasos->isNotEmpty())
<ol>@foreach($d->pasos as $p)<li>{{ $p->tipo==='firma'?'Firma':'Visación' }}: {{ $p->usuario->nombreCompleto() }} — <span class="estado estado-{{ $p->estado }}">{{ $p->estado }}</span>@if($p->simulada) <span class="sim">FIRMA SIMULADA – sin validez legal</span>@endif @if($p->comentario)<em>({{ $p->comentario }})</em>@endif</li>@endforeach</ol>
@else<p>Sin flujo definido.</p>@endif

@if($d->estado==='borrador' && $puedeOperar)
<form method="post" action="{{ route('app.documento.flujo',$d) }}" class="card card-body mb-4">@csrf
<h3 class="h6">Definir flujo (reglas del tipo: ágil {{ $d->tipo->firmas_agil }} firma(s), clásico {{ $d->tipo->firmas_clasico }} firma(s)@if($d->tipo->firma_alcalde); 1ª firma: alcalde o delegado vigente @endif)</h3>
<div class="form-group col-md-4 px-0"><label for="flujo">Flujo</label><select class="form-control" id="flujo" name="flujo"><option value="agil">Ágil</option><option value="clasico">Clásico</option></select></div>
<div class="form-row">
@foreach([1,2,3] as $i)<div class="form-group col-md-4"><label for="vis{{ $i }}">Visador {{ $i }} (opcional)</label><select class="form-control" id="vis{{ $i }}" name="visadores[]"><option value="">—</option>@foreach($usuarios as $u)<option value="{{ $u->id }}">{{ $u->nombreCompleto() }}</option>@endforeach</select></div>@endforeach</div>
<div class="form-row">
@foreach([1,2] as $i)<div class="form-group col-md-6"><label for="fir{{ $i }}">Firmante {{ $i }}</label><select class="form-control" id="fir{{ $i }}" name="firmantes[]"><option value="">—</option>@foreach($usuarios->where('puede_firmar',true) as $u)<option value="{{ $u->id }}">{{ $u->nombreCompleto() }} ({{ $u->rol }})</option>@endforeach</select></div>@endforeach</div>
<div><button class="btn btn-primary">Iniciar flujo</button></div></form>
@endif

@if($miTurno && $turno->tipo==='visacion')
<form method="post" action="{{ route('app.documento.visar',$d) }}" class="card card-body mb-4">@csrf
<h3 class="h6">Es su turno de visar</h3>
<div class="form-group"><label for="comentario">Comentario</label><input class="form-control" id="comentario" name="comentario" maxlength="1000"></div>
<div><button class="btn btn-success" name="accion" value="aprobar">Visar</button> <button class="btn btn-outline-danger" name="accion" value="rechazar">Rechazar</button></div></form>
@endif
@if($miTurno && $turno->tipo==='firma')
<form method="post" action="{{ route('app.documento.firmar',$d) }}" class="card card-body mb-4" autocomplete="off">@csrf
<h3 class="h6">Es su turno de firmar (firma electrónica avanzada FirmaGob)</h3>
<div class="form-group col-md-4 px-0"><label for="otp">Código OTP de 6 dígitos</label><input class="form-control" id="otp" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code"></div>
<div><button class="btn btn-success">Firmar</button></div></form>
@endif
@if($completos && $puedeOperar)
<form method="post" action="{{ route('app.documento.finalizar',$d) }}" class="mb-4">@csrf<p>Todas las firmas están completas pero el documento no se finalizó.</p><button class="btn btn-primary">Reintentar finalización</button></form>
@endif

<h2 class="h4">Historial de versiones (inmutable)</h2>
<table class="table table-sm"><thead><tr><th>Versión</th><th>Fecha</th><th>SHA-256 HTML</th><th>SHA-256 PDF</th></tr></thead><tbody>
@foreach($d->versiones as $v)<tr><td>{{ $v->version }}</td><td>{{ $v->created_at }}</td><td class="hash">{{ $v->html_sha256 }}</td><td class="hash">{{ $v->pdf_sha256 }}</td></tr>@endforeach</tbody></table>
@endsection
@push('scripts')<script src="{{ asset('assets/tinymce/tinymce.min.js') }}"></script>@endpush
