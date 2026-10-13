@extends('layouts.app')
@section('titulo', 'Vista previa del trámite')
@section('contenido')
<div class="alert alert-info" role="status">Vista previa: así lo verá la ciudadanía. Estado actual: <strong>{{ $tramite->estado }}</strong>.</div>
@if($problemas && $tramite->estado!=='publicado')<div class="alert alert-warning" role="alert"><strong>Falta para poder publicarlo:</strong><ul class="mb-0">@foreach($problemas as $p)<li>{{ $p }}</li>@endforeach</ul></div>@endif
@include('partials.tramite_ficha', ['tramite' => $tramite])
<a class="btn btn-outline-secondary" href="{{ route('app.tramites.editar',$tramite->id) }}">Volver a editar</a>
@endsection
