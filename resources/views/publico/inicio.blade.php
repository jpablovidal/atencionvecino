@extends('layouts.app')
@section('titulo', 'Trámites y servicios en línea')
@php
  $iconos = ['DOM' => 'building', 'DIDECO' => 'users', 'ALC' => 'home', 'SECMUN' => 'file-text', 'ARCH' => 'archive', 'TRANSP' => 'book'];
  $ficha = fn ($t) => route('catalogo.ficha', [$t->id, \Illuminate\Support\Str::slug($t->nombre)]);
@endphp
@section('hero')
<h1 id="hero-titulo">Trámites y servicios en línea</h1>
<p class="hero-sub">Realice sus trámites con la Municipalidad de Santo Domingo, haga seguimiento y reciba sus respuestas, sin filas.</p>
@include('partials.buscador', ['id' => 'q-inicio', 'grande' => true, 'valor' => ''])
@if($destacados->isNotEmpty())
<p class="hero-frecuentes"><span>Más buscados:</span>
@foreach($destacados->take(4) as $t)<a href="{{ $ficha($t) }}">{{ \Illuminate\Support\Str::limit($t->nombre, 38) }}</a>@endforeach</p>
@endif
<ul class="hero-datos">
  <li><strong>{{ $totalTramites }}</strong> {{ $totalTramites === 1 ? 'trámite disponible' : 'trámites disponibles' }} en línea</li>
  <li><strong>{{ $direcciones->count() }}</strong> {{ $direcciones->count() === 1 ? 'dirección municipal' : 'direcciones municipales' }}</li>
  <li>Ingreso seguro con <strong>ClaveÚnica</strong></li>
</ul>
@endsection
@section('contenido')
@if($destacados->isNotEmpty())
<section aria-labelledby="pop-t" class="portal-seccion">
  <div class="portal-seccion-cab"><h2 id="pop-t">Trámites más solicitados</h2><a href="{{ route('catalogo') }}">Ver todos los trámites y servicios</a></div>
  <div class="portal-tarjetas">
  @foreach($destacados as $t)
    <article class="portal-tarjeta"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-clipboard"/></svg></span>
      <h3><a href="{{ $ficha($t) }}">{{ $t->nombre }}</a></h3>
      <p class="chips"><span class="chip">{{ $t->propietaria?->nombre }}</span><span class="chip chip-claro">Respuesta en {{ $t->plazo_dias }} días{{ $t->plazo_habiles ? ' hábiles' : '' }}</span></p>
      @if($t->descripcion)<p class="portal-desc">{{ \Illuminate\Support\Str::limit($t->descripcion, 120) }}</p>@endif
      <a class="btn btn-sm btn-outline-primary" href="{{ $ficha($t) }}">Ver trámite<span class="sr-only"> {{ $t->nombre }}</span></a>
    </article>
  @endforeach
  </div>
</section>
@endif

@if($direcciones->isNotEmpty())
<section aria-labelledby="dir-t" class="portal-seccion">
  <div class="portal-seccion-cab"><h2 id="dir-t">Explore por dirección municipal</h2></div>
  <ul class="portal-direcciones">
  @foreach($direcciones as $d)
    <li><a href="{{ route('catalogo', ['direccion' => $d->id]) }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-{{ $iconos[$d->codigo] ?? 'building' }}"/></svg></span>
      <span><strong>{{ $d->nombre }}</strong><small>{{ $d->publicados_count }} {{ $d->publicados_count === 1 ? 'trámite' : 'trámites' }}</small></span></a></li>
  @endforeach
  </ul>
</section>
@endif

<section aria-labelledby="como-t" class="portal-seccion">
  <div class="portal-seccion-cab"><h2 id="como-t">¿Cómo funciona?</h2></div>
  <ol class="portal-pasos">
    <li><span class="paso-n" aria-hidden="true">1</span><strong>Busque su trámite</strong><span>Use el buscador o explore por dirección y revise qué documentos necesita.</span></li>
    <li><span class="paso-n" aria-hidden="true">2</span><strong>Ingrese con ClaveÚnica</strong><span>Su identidad la verifica ClaveÚnica; sus datos no se pueden modificar.</span></li>
    <li><span class="paso-n" aria-hidden="true">3</span><strong>Complete y adjunte</strong><span>Un formulario por pasos con revisión final antes de enviar.</span></li>
    <li><span class="paso-n" aria-hidden="true">4</span><strong>Reciba su folio y siga su avance</strong><span>Descargue su comprobante y vea el estado en «Mis solicitudes».</span></li>
  </ol>
</section>

<section aria-labelledby="serv-t" class="portal-seccion">
  <div class="portal-seccion-cab"><h2 id="serv-t">Otros servicios para el vecino</h2></div>
  <div class="portal-servicios">
    <a href="{{ route('ingreso.via','ciudadano') }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-folder"/></svg></span><strong>Mis solicitudes</strong><small>Ingrese con ClaveÚnica y siga el estado de lo que ha presentado.</small></a>
    <a href="{{ route('validar.form') }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-check-circle"/></svg></span><strong>Validar un documento</strong><small>Compruebe su autenticidad con el código SDD.</small></a>
    <a href="{{ route('publico.listado') }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-file-text"/></svg></span><strong>Documentos públicos</strong><small>Decretos, ordenanzas y otros documentos vigentes.</small></a>
    <a href="{{ route('ayuda') }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-help"/></svg></span><strong>Ayuda y manuales</strong><small>Guías paso a paso y declaración de accesibilidad.</small></a>
  </div>
</section>

<aside class="portal-aviso" aria-label="Atención presencial"><strong>¿Prefiere atención presencial?</strong> Todos los trámites también pueden hacerse en las oficinas municipales. Si tiene dificultades para usar el sistema, solicite ayuda al personal.</aside>
<p class="mt-3 small"><a href="{{ route('ingreso.via','funcionario') }}">Acceso funcionarios</a></p>
@endsection
