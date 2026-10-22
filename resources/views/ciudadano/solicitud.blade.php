@extends('layouts.app')
@section('titulo', 'Solicitud '.$s->folio)
@section('contenido')
<nav aria-label="Usted está en" class="portal-migas"><span class="portal-migas-et">Usted está en:</span><ol><li><a href="{{ route('inicio') }}">Inicio</a></li><li><a href="{{ route('ciudadano.index') }}">Mis solicitudes</a></li><li aria-current="page">{{ $s->folio }}</li></ol></nav>
<h1>Solicitud {{ $s->folio }}</h1>
<p class="portal-actualizado">Última actualización: {{ ($linea->last()['fecha'] ?? $s->updated_at)->copy()->setTimezone('America/Santiago')->locale('es')->translatedFormat('d \d\e F, Y') }}</p>
<p><span class="estado estado-{{ $s->estado }}">{{ str_replace('_',' ',$s->estado) }}</span> · {{ $s->tramite->nombre }}</p>
<dl class="row" aria-label="Datos de su solicitud">
 <dt class="col-sm-4 col-md-3">Solicitud N°</dt><dd class="col-sm-8 col-md-9">{{ $s->folio }}</dd>
@if($s->en_nombre_de) <dt class="col-sm-4 col-md-3">A nombre de</dt><dd class="col-sm-8 col-md-9">{{ $s->interesado_nombre }} ({{ \App\Models\Solicitud::RELACIONES[$s->relacion] ?? $s->relacion }})</dd>@endif
 <dt class="col-sm-4 col-md-3">Expediente N°</dt><dd class="col-sm-8 col-md-9">{{ $s->expediente?->codigo ?? 'Aún no se abre el expediente' }}</dd>
 <dt class="col-sm-4 col-md-3">Ingresada el</dt><dd class="col-sm-8 col-md-9">{{ $s->created_at->setTimezone('America/Santiago')->format('d-m-Y H:i') }}</dd>
 <dt class="col-sm-4 col-md-3">Dirección responsable</dt><dd class="col-sm-8 col-md-9">{{ $direccion ?? 'Por asignar' }}</dd>
</dl>
<p>{{ $s->detalle }}</p>
@if($s->consentimiento_datos === false)<p class="small text-muted">Usted indicó el {{ $s->consentimiento_en?->timezone('America/Santiago')->format('d-m-Y H:i') }} que no autoriza el tratamiento de sus datos personales; la Municipalidad solo trata los que la ley le exige para resolver el trámite.</p>@elseif($s->consentimiento_datos)<p class="small text-muted">Consentimiento al tratamiento de datos personales otorgado el {{ $s->consentimiento_en?->timezone('America/Santiago')->format('d-m-Y H:i') }} ({{ $s->consentimiento_version }}).</p>@endif
<form method="post" action="{{ route('ciudadano.consentimiento', $s->folio) }}" class="form-inline mb-3 small" aria-label="Cambiar su respuesta sobre el tratamiento de datos">@csrf
@if($s->consentimiento_datos)<input type="hidden" name="consentimiento" value="no"><span class="mr-2">Puede cambiar de opinión en cualquier momento (no afecta lo ya realizado).</span><button class="btn btn-sm btn-outline-secondary">Dejar de autorizar el tratamiento de mis datos</button>
@else<input type="hidden" name="consentimiento" value="si"><span class="mr-2">Si lo desea, puede autorizar el tratamiento de sus datos para esta solicitud.</span><button class="btn btn-sm btn-outline-primary">Autorizar el tratamiento de mis datos</button>@endif</form>
@if($s->prorroga_dias)<div class="alert alert-info" role="status"><strong>Plazo ampliado.</strong> La municipalidad prorrogó el plazo de respuesta {{ $s->prorroga_dias }} días el {{ $s->prorrogada_en?->setTimezone('America/Santiago')->format('d-m-Y') }}. Motivo: {{ $s->prorroga_motivo }}</div>@endif
@if(! empty($s->datos))<h2 class="h5">Datos que usted completó</h2>@include('partials.datos_formulario', ['datos' => $s->datos])@endif
<p><a class="btn btn-outline-primary" href="{{ route('ciudadano.comprobante',$s->folio) }}">Descargar comprobante de ingreso (PDF)</a></p>
@if($pendiente)
<div class="card border-warning mb-4"><div class="card-body">
<h2 class="h5">Su solicitud fue devuelta con observaciones</h2>
<p>{{ $pendiente->observacion }}</p>
<p><strong>Plazo para responder:</strong> hasta el {{ $pendiente->vence_en->format('d-m-Y') }}.</p>
<form method="post" action="{{ route('ciudadano.responder',$s->folio) }}" enctype="multipart/form-data">@csrf
<div class="form-group"><label for="respuesta">Su respuesta</label><textarea class="form-control" id="respuesta" name="respuesta" rows="4" required maxlength="4000"></textarea></div>
<div class="form-group"><label for="archivos">Nuevos antecedentes (opcional)</label><input class="form-control-file" type="file" id="archivos" name="archivos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff"></div>
<button class="btn btn-primary">Enviar respuesta</button></form></div></div>
@endif
<div class="portal-acordeon">
<details open id="movimientos"><summary>Movimientos de su solicitud</summary><div class="portal-acordeon-cuerpo">
<p class="small text-muted">Le avisamos de cada movimiento a su Casilla Única, con copia a su correo electrónico.</p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Movimientos de la solicitud {{ $s->folio }}</caption>
<thead><tr><th scope="col">Fecha</th><th scope="col">Movimiento</th><th scope="col">Dirección municipal</th></tr></thead><tbody>
@foreach($linea as $m)<tr><td class="text-nowrap">{{ $m['fecha']->setTimezone('America/Santiago')->format('d-m-Y H:i') }}</td><td>{{ $m['descripcion'] }}</td><td>{{ $m['direccion'] ?? '—' }}</td></tr>@endforeach</tbody></table></div>
</div></details>
<details><summary>Historial de devoluciones</summary><div class="portal-acordeon-cuerpo">
@forelse($s->devoluciones as $d)<div class="card mb-2"><div class="card-body"><strong>{{ $d->created_at->format('d-m-Y') }}</strong>: {{ $d->observacion }}@if($d->respondida_en)<br><em>Respondida el {{ $d->respondida_en->format('d-m-Y') }}: {{ $d->respuesta }}</em>@endif</div></div>@empty<p>Sin devoluciones.</p>@endforelse
</div></details>
<details open><summary>Documentos notificados</summary><div class="portal-acordeon-cuerpo">
@forelse($docs as $d)<p><a href="{{ route('ciudadano.documento',[$s->folio,$d]) }}">{{ $d->tipo->nombre }} {{ $d->numeroFormateado() }} — {{ $d->titulo }}</a></p>@empty @if($archivos->isEmpty())<p>Aún no tiene documentos notificados.</p>@endif @endforelse
@foreach($archivos as $a)<p><a href="{{ route('ciudadano.archivo',[$s->folio,$a]) }}">{{ $a->titulo ?: $a->nombre_original }}</a></p>@endforeach
</div></details>
</div>
<div class="portal-etiquetas"><p class="mb-1">Dirección responsable:</p><p class="mb-0"><span class="portal-tag">#{{ $direccion ?? 'Por asignar' }}</span></p></div>
@endsection
