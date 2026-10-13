@extends('layouts.app')
@section('titulo', $tramite->nombre)
@section('contenido')
<nav aria-label="Ruta de navegación"><a href="{{ route('catalogo') }}">&larr; Todos los trámites y servicios</a></nav>
<div class="row mt-3"><div class="col-lg-8">
@include('partials.tramite_ficha', ['tramite' => $tramite])
<p class="small text-muted">Última actualización: {{ ($tramite->publicado_en ?? $tramite->updated_at)?->format('d-m-Y') }}.</p>
</div>
<aside class="col-lg-4" aria-label="Solicitar este trámite"><div class="card"><div class="card-body">
  <h2 class="h5">¿Cómo lo solicito?</h2>
  <ol class="pl-3 small"><li>Ingrese con su ClaveÚnica.</li><li>Complete sus datos y adjunte los documentos indicados.</li><li>Reciba su folio y haga seguimiento en línea.</li></ol>
  <a class="btn btn-primary btn-block" href="{{ route('ciudadano.nueva', $tramite) }}">Realizar este trámite en línea</a>
  <p class="small text-muted mt-2 mb-0">Si no ha iniciado sesión, se le pedirá ingresar y luego lo encontrará en «Mis solicitudes».</p>
</div></div></aside></div>
@endsection
