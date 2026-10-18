@extends('layouts.app')
@section('titulo', 'Validación de documento')
@section('contenido')
<h1>Validación de documento</h1>
@if($doc)
<div class="alert alert-success" role="status">El documento es <strong>auténtico y está vigente</strong>.</div>
<dl class="row">
<dt class="col-sm-3">Tipo</dt><dd class="col-sm-9">{{ $doc->tipo->nombre }}</dd>
<dt class="col-sm-3">N° / año</dt><dd class="col-sm-9">{{ $doc->numeroFormateado() }}</dd>
<dt class="col-sm-3">Fecha de firma</dt><dd class="col-sm-9">{{ $doc->fecha_firma->timezone('America/Santiago')->format('d-m-Y H:i') }} (hora de Chile)</dd>
@unless($restringido ?? false)<dt class="col-sm-3">Firmantes</dt><dd class="col-sm-9">@foreach($doc->pasos->where('tipo','firma') as $p){{ $p->usuario->nombreCompleto() }}@if($p->simulada) <span class="sim">FIRMA SIMULADA – sin validez legal</span>@endif<br>@endforeach</dd>@endunless
<dt class="col-sm-3">SHA-256 de la copia</dt><dd class="col-sm-9 hash">{{ $doc->pdf_sha256 }}</dd>
<dt class="col-sm-3">Código</dt><dd class="col-sm-9">{{ $doc->codigo_validacion }}</dd>
</dl>
@if($doc->esPublico())<a class="btn btn-primary" href="{{ route('validar.copia',$doc->codigo_validacion) }}">Descargar copia pública (PDF)</a>
@else<p class="text-muted">Este documento no es público: la copia solo se entrega a quien fue notificado.</p>@endif
@else
<div class="alert alert-danger" role="alert">El código <strong>{{ $codigo }}</strong> no corresponde a un documento vigente.</div>
@endif
@endsection
