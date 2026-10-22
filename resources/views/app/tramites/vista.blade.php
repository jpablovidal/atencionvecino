@extends('layouts.app')
@section('titulo', 'Vista previa del trámite')
@section('contenido')
<div class="alert alert-info" role="status">Vista previa: así lo verá la ciudadanía. Estado actual: <strong>{{ $tramite->estado }}</strong>.</div>
@if($problemas && $tramite->estado!=='publicado')<div class="alert alert-warning" role="alert"><strong>Falta para poder publicarlo:</strong><ul class="mb-0">@foreach($problemas as $p)<li>{{ $p }}</li>@endforeach</ul></div>@endif
@include('partials.tramite_ficha', ['tramite' => $tramite])
@if($campos->isNotEmpty())<h2 class="h4 mt-4">Datos que se le pedirán</h2><p class="small text-muted">Así se verán los campos al completar la solicitud (esta vista previa no envía nada).</p>
<form class="portal-formulario mb-4" data-formulario data-solo-vista>@include('partials.tramite_campos', ['campos' => $campos, 'hoy' => $hoy])</form>
@push('scripts')<script src="{{ asset('assets/js/formulario.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/formulario.js')), 0, 8) }}" defer></script>@endpush
@endif
<a class="btn btn-outline-secondary" href="{{ route('app.tramites.editar',$tramite->id) }}">Volver a editar</a>
@endsection
