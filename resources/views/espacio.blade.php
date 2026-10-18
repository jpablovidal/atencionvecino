@extends('layouts.app')
@section('titulo', 'Elija su espacio')
@section('sinlateral', '1')
@section('contenido')
<h1>Bienvenido(a), {{ $c['nombres'] }}</h1>
<p class="lead-sub">Usted es funcionario(a) municipal y también puede realizar trámites como vecino(a). ¿En qué espacio desea trabajar? Puede cambiar en cualquier momento desde el menú de su nombre.</p>
<div class="espacios">
  <section class="espacio-card" aria-labelledby="esp-fun">
    <span class="espacio-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-building"/></svg></span>
    <h2 id="esp-fun" class="h4">Espacio funcionario</h2>
    <p>{{ $u->rol }}@if($u->cargo) · {{ $u->cargo }}@endif — bandeja de trabajo, expedientes, documentos y firmas.</p>
    @if($opera)<ul class="list-unstyled espacio-datos"><li><strong>{{ $pendientes }}</strong> visación(es) o firma(s) pendiente(s)</li><li><strong>{{ $porAsignar }}</strong> solicitud(es) por asignar</li></ul>@endif
    <a class="btn btn-primary" href="{{ route('app.bandeja') }}">Ir a mi trabajo</a>
  </section>
  <section class="espacio-card" aria-labelledby="esp-vec">
    <span class="espacio-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-folder"/></svg></span>
    <h2 id="esp-vec" class="h4">Espacio vecino(a)</h2>
    <p>Sus propios trámites ante la municipalidad (RUN {{ $c['run'] }}), con los mismos derechos que cualquier vecino(a).</p>
    <ul class="list-unstyled espacio-datos"><li><strong>{{ $enCurso }}</strong> solicitud(es) en trámite</li><li><strong>{{ $devueltas }}</strong> devuelta(s) que requieren su respuesta</li></ul>
    <a class="btn btn-primary" href="{{ route('ciudadano.index') }}">Ir a mis trámites</a>
    <a class="btn btn-outline-primary" href="{{ route('catalogo') }}">Buscar un trámite</a>
  </section>
</div>
<p class="small text-muted mt-3">Por transparencia y para evitar conflictos de interés, usted no puede tramitar como funcionario(a) las solicitudes que presentó como vecino(a): esas solo las ve en su espacio vecino(a).</p>
@endsection
