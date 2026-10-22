@extends('layouts.app')
@section('titulo', 'Solicitud '.$s->folio)
@section('contenido')
<h1>Solicitud {{ $s->folio }}</h1>
<dl class="row">
<dt class="col-sm-3">Solicitante</dt><dd class="col-sm-9">{{ $s->nombres }} {{ $s->apellidos }} ({{ $s->run }})</dd>
<dt class="col-sm-3">Correo</dt><dd class="col-sm-9">{{ $s->correo }}</dd>
<dt class="col-sm-3">Trámite</dt><dd class="col-sm-9">{{ $s->tramite->nombre }}</dd>
<dt class="col-sm-3">Notificación</dt><dd class="col-sm-9">{{ str_replace('_',' ',$s->canal_notificacion) }}</dd>
<dt class="col-sm-3">Detalle</dt><dd class="col-sm-9">{{ $s->detalle }}</dd>
@if($s->en_nombre_de)<dt class="col-sm-3">A nombre de</dt><dd class="col-sm-9"><strong>{{ $s->interesado_nombre }}</strong> · {{ $s->interesado_run }}@if($s->interesado_correo) · {{ $s->interesado_correo }}@endif<br>Presentada por {{ \App\Models\Solicitud::RELACIONES[$s->relacion] ?? $s->relacion }}. Acreditación declarada: «{{ $s->acreditacion }}» <span class="small text-muted">(el sistema no verifica el poder: revíselo en los anexos)</span></dd>@endif
@if(! empty($s->datos))<dt class="col-sm-3">Datos del formulario</dt><dd class="col-sm-9">@include('partials.datos_formulario', ['datos' => $s->datos])</dd>@endif</dl>
<h2 class="h5">Anexos</h2>
<ul>@foreach($s->archivos->where('ambito','!=','comprobante') as $a)<li><a href="{{ route('app.solicitud.archivo',[$s->folio,$a]) }}">{{ $a->titulo }} — {{ $a->nombre_original }}</a> <span class="hash">{{ $a->sha256 }}</span> @if($a->mime === 'application/pdf')@include('partials.firma_resultado', ['estado' => $a->firma_estado, 'det' => $a->firma()])@endif</li>@endforeach</ul>
<form method="post" action="{{ route('app.asignar',$s->folio) }}">@csrf<button class="btn btn-primary">Asignármela y crear expediente</button></form>
@endsection
