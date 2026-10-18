@extends('layouts.app')
@section('titulo', 'Solicitud '.$fila['folio'])
@section('contenido')
<h1>Solicitud {{ $fila['folio'] }}</h1>
<p><a href="{{ $volver }}">← Volver</a></p>
<section class="card mb-4"><div class="card-body"><dl class="row mb-0">
  <dt class="col-md-3">Solicitante</dt><dd class="col-md-9">{{ $fila['solicitante'] }} · RUN {{ $fila['run'] }}</dd>
  <dt class="col-md-3">Trámite</dt><dd class="col-md-9">{{ $fila['tramite'] }}</dd>
  <dt class="col-md-3">Dirección</dt><dd class="col-md-9">{{ $fila['direccion'] }}</dd>
  <dt class="col-md-3">Etapa actual</dt><dd class="col-md-9"><span class="etapa etapa-{{ $fila['etapa'] }}">{{ $fila['etapa_txt'] }}</span> · {{ $fila['terminada'] ? 'Terminó el '.$fila['termino']->format('d-m-Y H:i') : 'En curso' }}</dd>
  <dt class="col-md-3">Quién la tiene</dt><dd class="col-md-9">@if($fila['terminada'])Terminada @else{{ $fila['tiene'] ?? 'Sin asignar' }}@endif @if($fila['responsable'] && !$fila['terminada'])<span class="text-muted">· responsable: {{ $fila['responsable'] }}</span>@endif</dd>
  <dt class="col-md-3">Plazo</dt><dd class="col-md-9">Vence el {{ $fila['vence']->format('d-m-Y') }} · {{ ['vencido'=>'vencido','por_vencer'=>'por vencer','en_plazo'=>'en plazo','cumplido'=>'terminada dentro del plazo','fuera_de_plazo'=>'terminada fuera de plazo','suspendido'=>'suspendido mientras el solicitante responde'][$fila['plazo']] }}</dd>
  @if($fila['expediente'])<dt class="col-md-3">Expediente</dt><dd class="col-md-9">{{ $fila['expediente'] }}</dd>@endif
</dl></div></section>
<h2 class="seccion">Historial</h2>
<ol class="sgd-historial">
@foreach($eventos as $ev)<li><time datetime="{{ $ev['ts']->toIso8601String() }}">{{ $ev['ts']->setTimezone('America/Santiago')->format('d-m-Y H:i') }}</time> — {{ $ev['txt'] }}</li>@endforeach
</ol>
<p class="small text-muted">Esta vista no muestra documentos ni sus contenidos. Su consulta quedó registrada en la bitácora de auditoría.</p>
@endsection
