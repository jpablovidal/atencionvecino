@extends('layouts.app')
@section('titulo', 'Requerimientos de transparencia')
@section('contenido')
<h1>Requerimientos de transparencia</h1>
<p class="lead-sub">{{ $todos ? 'Información que Transparencia ha requerido a las direcciones.' : 'Información que Transparencia requiere a su dirección para responder una solicitud de acceso a la información.' }}</p>
@if($todos)@include('app.transparencia._nav')@endif
<nav aria-label="Filtro" class="mb-3"><ul class="nav nav-pills">
@foreach([null=>'Todos','pendiente'=>'Pendientes','respondido'=>'Respondidos'] as $k=>$v)<li class="nav-item"><a class="nav-link {{ $estado===$k ? 'active' : '' }}" @if($estado===$k) aria-current="page" @endif href="{{ route('app.transparencia.requerimientos', $k ? ['estado'=>$k] : []) }}">{{ $v }}</a></li>@endforeach
</ul></nav>
@forelse($pag as $q)
<section class="card mb-3" aria-labelledby="rq-{{ $q->id }}"><div class="card-body">
  <h2 class="h6" id="rq-{{ $q->id }}">Folio {{ $q->solicitud->folio }} · {{ $q->solicitud->tramite->nombre }}</h2>
  <p class="small mb-1"><strong>Dirección:</strong> {{ $q->unidad->nombre }} · <strong>Vence:</strong> {{ $q->vence_en->setTimezone('America/Santiago')->format('d-m-Y') }}
    @if($q->estado==='respondido')<span class="plazo plazo-cumplido">Respondido</span>@elseif($q->vencido())<span class="plazo plazo-vencido">Vencido</span>@else<span class="plazo plazo-en_plazo">Pendiente</span>@endif</p>
  <p class="mb-1"><strong>Lo que se solicita al solicitante (consulta original):</strong></p><blockquote class="small border-left pl-2 mb-2">{{ \Illuminate\Support\Str::limit($q->solicitud->detalle, 1200) }}</blockquote>
  <p class="mb-1"><strong>Instrucción de Transparencia:</strong></p><p class="small">{{ $q->instruccion }}</p>
  @if($q->estado==='respondido')
    <p class="mb-1"><strong>Respuesta</strong> ({{ $q->respondido_en?->setTimezone('America/Santiago')->format('d-m-Y H:i') }} · {{ $q->respondidoPor?->nombreCompleto() }}):</p><p class="small mb-0">{{ $q->respuesta }}</p>
  @elseif(auth()->user()->tienePermiso('transparencia.responder') && (int) auth()->user()->unidad_id === (int) $q->unidad_id)
    <form method="post" action="{{ route('app.transparencia.responder', $q->id) }}">@csrf
      <div class="form-group"><label for="resp-{{ $q->id }}">Su respuesta</label><textarea class="form-control" id="resp-{{ $q->id }}" name="respuesta" rows="4" required minlength="5" maxlength="4000"></textarea><small class="form-text text-muted">Texto plano. Si debe entregar documentos, indique dónde están o entréguelos a Transparencia por los canales institucionales.</small></div>
      <button class="btn btn-primary">Enviar respuesta</button>
    </form>
  @endif
</div></section>
@empty<div class="vacio"><svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-check-circle"/></svg> No hay requerimientos.</div>@endforelse
{{ $pag->links() }}
@endsection
