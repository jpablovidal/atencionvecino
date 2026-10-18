@extends('layouts.app')
@section('titulo', 'Solicitud '.$s->folio)
@section('contenido')
<h1>Solicitud {{ $s->folio }}</h1>
<p><span class="estado estado-{{ $s->estado }}">{{ str_replace('_',' ',$s->estado) }}</span> · {{ $s->tramite->nombre }}</p>
<dl class="row" aria-label="Datos de su solicitud">
 <dt class="col-sm-4 col-md-3">Solicitud N°</dt><dd class="col-sm-8 col-md-9">{{ $s->folio }}</dd>
 <dt class="col-sm-4 col-md-3">Expediente N°</dt><dd class="col-sm-8 col-md-9">{{ $s->expediente?->codigo ?? 'Aún no se abre el expediente' }}</dd>
 <dt class="col-sm-4 col-md-3">Ingresada el</dt><dd class="col-sm-8 col-md-9">{{ $s->created_at->setTimezone('America/Santiago')->format('d-m-Y H:i') }}</dd>
 <dt class="col-sm-4 col-md-3">Dirección responsable</dt><dd class="col-sm-8 col-md-9">{{ $direccion ?? 'Por asignar' }}</dd>
</dl>
<p>{{ $s->detalle }}</p>
@if($s->prorroga_dias)<div class="alert alert-info" role="status"><strong>Plazo ampliado.</strong> La municipalidad prorrogó el plazo de respuesta {{ $s->prorroga_dias }} días el {{ $s->prorrogada_en?->setTimezone('America/Santiago')->format('d-m-Y') }}. Motivo: {{ $s->prorroga_motivo }}</div>@endif
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
<h2 class="h4" id="movimientos">Movimientos de su solicitud</h2>
<p class="small text-muted">Le avisamos de cada movimiento a su Casilla Única, con copia a su correo electrónico.</p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Movimientos de la solicitud {{ $s->folio }}</caption>
<thead><tr><th scope="col">Fecha</th><th scope="col">Movimiento</th><th scope="col">Dirección municipal</th></tr></thead><tbody>
@foreach($linea as $m)<tr><td class="text-nowrap">{{ $m['fecha']->setTimezone('America/Santiago')->format('d-m-Y H:i') }}</td><td>{{ $m['descripcion'] }}</td><td>{{ $m['direccion'] ?? '—' }}</td></tr>@endforeach</tbody></table></div>
<h2 class="h4">Historial de devoluciones</h2>
@forelse($s->devoluciones as $d)<div class="card mb-2"><div class="card-body"><strong>{{ $d->created_at->format('d-m-Y') }}</strong>: {{ $d->observacion }}@if($d->respondida_en)<br><em>Respondida el {{ $d->respondida_en->format('d-m-Y') }}: {{ $d->respuesta }}</em>@endif</div></div>@empty<p>Sin devoluciones.</p>@endforelse
<h2 class="h4 mt-4">Documentos notificados</h2>
@forelse($docs as $d)<p><a href="{{ route('ciudadano.documento',[$s->folio,$d]) }}">{{ $d->tipo->nombre }} {{ $d->numeroFormateado() }} — {{ $d->titulo }}</a></p>@empty @if($archivos->isEmpty())<p>Aún no tiene documentos notificados.</p>@endif @endforelse
@foreach($archivos as $a)<p><a href="{{ route('ciudadano.archivo',[$s->folio,$a]) }}">{{ $a->titulo ?: $a->nombre_original }}</a></p>@endforeach
@endsection
