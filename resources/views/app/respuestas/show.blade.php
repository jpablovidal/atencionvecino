@extends('layouts.app')
@section('titulo', 'Respuesta final '.$r->solicitud->folio)
@section('contenido')
@php $s = $r->solicitud; $e = $r->expediente; $pend = $r->estado === 'instruida'; @endphp
<p><a href="{{ route('app.respuestas') }}">← Respuestas finales</a></p>
<h1>Responder la solicitud {{ $s->folio }}</h1>
<dl class="row">
 <dt class="col-sm-3">Solicitud N°</dt><dd class="col-sm-9">{{ $s->folio }} · {{ $s->tramite->nombre }}</dd>
 <dt class="col-sm-3">Expediente N°</dt><dd class="col-sm-9">{{ $e->codigo }} · {{ $e->unidad?->nombre }} · {{ $e->estado }}</dd>
 <dt class="col-sm-3">Solicitante</dt><dd class="col-sm-9">{{ $s->nombres }} {{ $s->apellidos }} · {{ $s->run }} · {{ $s->correo }}</dd>
 <dt class="col-sm-3">Instruida por</dt><dd class="col-sm-9">{{ $r->instructor?->nombreCompleto() }} el {{ $r->created_at->setTimezone('America/Santiago')->format('d-m-Y H:i') }}</dd>
 <dt class="col-sm-3">Canal indicado</dt><dd class="col-sm-9">{{ $canales[$r->canal] ?? $r->canal }}@if($r->motivo_presencial) · motivo de la excepción: {{ $r->motivo_presencial }}@endif</dd>
 @if($r->instruccion)<dt class="col-sm-3">Indicaciones</dt><dd class="col-sm-9">{{ $r->instruccion }}</dd>@endif
 <dt class="col-sm-3">Estado</dt><dd class="col-sm-9"><span class="estado estado-{{ $r->estado }}">{{ $r->estado }}</span>@if($r->estado==='despachada') · entrega: {{ str_replace('_',' ',$r->entrega()) }} · {{ $r->despachada_en?->setTimezone('America/Santiago')->format('d-m-Y H:i') }} por {{ $r->despachador?->nombreCompleto() }}@endif @if($r->estado==='anulada') · {{ $r->anulada_motivo }}@endif</dd>
</dl>
@if($pend && $e->estado !== 'cerrado')<div class="alert alert-warning" role="status">El expediente aún está <strong>abierto</strong>: quien lo gestiona debe cerrarlo antes de que usted pueda despachar la respuesta.</div>@endif

<h2 class="h4">Documentos que debe entregar</h2>
<ul>@foreach($r->items as $it)<li>@if($it->documento)<a href="{{ route('app.respuestas.item', [$r, $it]) }}">Documento {{ $it->documento->tipo?->nombre }} {{ $it->documento->numeroFormateado() }} — {{ $it->documento->titulo }}</a>@elseif($it->archivo)<a href="{{ route('app.respuestas.item', [$r, $it]) }}">Archivo: {{ $it->archivo->titulo ?: $it->archivo->nombre_original }}</a>@endif</li>@endforeach</ul>

@if($pend)
<form method="post" action="{{ route('app.respuestas.despachar', $r) }}" class="card card-body mb-3" @if($r->canal==='casilla_unica') data-confirm="¿Despachar la respuesta por Casilla Única con copia al correo {{ $s->correo }}?" @endif>@csrf
 <h2 class="h5">Despachar</h2>
 @if($r->canal==='casilla_unica')<p>Se creará una notificación por Casilla Única para cada documento y una copia del aviso al correo <strong>{{ $s->correo }}</strong> (Gmail API). Si Casilla Única está en modo outbox, registre después el ID de cada notificación.</p>
 @else<div class="form-group"><label for="acta">Acta de entrega física (quién recibe, fecha y hora)</label><input class="form-control" id="acta" name="acta" maxlength="250" required></div>@endif
 <div><button class="btn btn-primary" @disabled($e->estado!=='cerrado')>{{ $r->canal==='casilla_unica' ? 'Despachar por Casilla Única' : 'Registrar entrega física' }}</button></div></form>
<form method="post" action="{{ route('app.respuestas.anular', $r) }}" class="form-inline mb-4">@csrf<label class="sr-only" for="an">Motivo de la anulación</label><input class="form-control mr-2" id="an" name="motivo" required maxlength="300" placeholder="Motivo para anular esta instrucción"><button class="btn btn-outline-secondary">Anular instrucción</button></form>
@endif

@if($r->notificaciones->isNotEmpty())
<h2 class="h4">Notificaciones</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Notificaciones de la respuesta</caption><thead><tr><th scope="col">Elemento</th><th scope="col">Canal</th><th scope="col">Estado</th><th scope="col">Evidencia</th></tr></thead><tbody>
@foreach($r->notificaciones as $n)<tr><td>@if($n->documento){{ $n->documento->numeroFormateado() }} {{ $n->documento->titulo }}@elseif($n->archivo){{ $n->archivo->titulo ?: $n->archivo->nombre_original }}@else Aviso por correo @endif</td><td>{{ str_replace('_',' ',$n->canal) }}</td>
<td><span class="estado estado-{{ $n->estado }}">{{ $n->estado }}</span> @if($n->ultimo_error)<small>{{ $n->ultimo_error }}</small>@endif</td>
<td>@if($n->evidencia){{ $n->evidencia_tipo }}: <span class="hash">{{ $n->evidencia }}</span>@elseif($n->canal==='casilla_unica')<form method="post" action="{{ route('app.respuestas.evidencia', [$r, $n]) }}" class="form-inline">@csrf<label class="sr-only" for="ev{{ $n->id }}">ID de la notificación en Casilla Única</label><input class="form-control form-control-sm mr-1" id="ev{{ $n->id }}" name="valor" required maxlength="250" placeholder="ID en Casilla Única"><button class="btn btn-sm btn-primary">Registrar</button></form>@else — @endif</td></tr>@endforeach</tbody></table></div>
<form method="post" action="{{ route('app.respuestas.reintentar', $r) }}">@csrf<button class="btn btn-outline-primary">Reintentar envíos pendientes o fallidos</button></form>
@endif
@endsection
