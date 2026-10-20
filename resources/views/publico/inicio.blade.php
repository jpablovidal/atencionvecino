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
  <div class="portal-seccion-cab"><h2 id="pop-t">Lo más buscado</h2><a href="{{ route('catalogo') }}">Ver todos los trámites y servicios</a></div>
  <ul class="portal-masbuscado">
  @foreach($destacados as $t)
    <li><a href="{{ $ficha($t) }}"><span class="portal-ico portal-ico-g" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-{{ $iconos[$t->propietaria?->codigo] ?? 'clipboard' }}"/></svg></span><span>{{ $t->nombre }}<small>{{ $t->propietaria?->nombre }}</small></span></a></li>
  @endforeach
  </ul>
</section>
@endif

@if($direcciones->isNotEmpty())
<section aria-labelledby="dir-t" class="portal-seccion">
  <div class="portal-seccion-cab"><h2 id="dir-t">Servicios municipales por temas</h2></div>
  <ul class="portal-temas">
  @foreach($direcciones as $d)
    <li><h3 class="h5"><a href="{{ route('catalogo', ['direccion' => $d->id]) }}">{{ $d->nombre }}</a></h3>
      @php $ej = $ejemplos[$d->id] ?? []; $txt = $ej ? implode(', ', $ej).($d->publicados_count > count($ej) ? ', entre otros.' : '.') : $d->publicados_count.($d->publicados_count === 1 ? ' trámite.' : ' trámites.'); @endphp
      <p class="mb-0">{{ $txt }}</p></li>
  @endforeach
  </ul>
  <p class="portal-todos"><a href="{{ route('catalogo') }}">Conoce todos los servicios municipales por temas</a></p>
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

<section aria-labelledby="faq-t" class="portal-seccion">
  <div class="portal-seccion-cab"><h2 id="faq-t">Preguntas frecuentes</h2></div>
  <div class="portal-acordeon">
    <details><summary>¿Necesito ClaveÚnica para hacer un trámite?</summary><div class="portal-acordeon-cuerpo"><p class="mb-0">Sí. Su identidad la verifica ClaveÚnica y no puede modificarse. Puede revisar los requisitos de cualquier trámite sin ingresar.</p></div></details>
    <details><summary>¿Cómo sé en qué estado está mi solicitud?</summary><div class="portal-acordeon-cuerpo"><p class="mb-0">Al enviar recibirá un folio y un comprobante. En «Mis solicitudes» ve cada movimiento con su fecha y la dirección municipal responsable, y recibe aviso por cada cambio.</p></div></details>
    <details><summary>¿Puedo hacer mi trámite de forma presencial?</summary><div class="portal-acordeon-cuerpo"><p class="mb-0">Sí. Todos los trámites pueden hacerse también en las oficinas municipales.</p></div></details>
    <details><summary>¿Cuánto demora la respuesta?</summary><div class="portal-acordeon-cuerpo"><p class="mb-0">Cada trámite indica su plazo de respuesta en días (hábiles o corridos). Los feriados registrados por el municipio no cuentan como días administrativos.</p></div></details>
  </div>
</section>

<section aria-labelledby="serv-t" class="portal-seccion">
  <div class="portal-seccion-cab"><h2 id="serv-t">Otros servicios para el vecino</h2></div>
  <div class="portal-servicios">
    <a href="{{ route('login') }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-folder"/></svg></span><strong>Mis solicitudes</strong><small>Ingrese con ClaveÚnica y siga el estado de lo que ha presentado.</small></a>
    <a href="{{ route('validar.form') }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-check-circle"/></svg></span><strong>Validar un documento</strong><small>Compruebe su autenticidad con el código SDD.</small></a>
    <a href="{{ route('publico.listado') }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-file-text"/></svg></span><strong>Documentos públicos</strong><small>Decretos, ordenanzas y otros documentos vigentes.</small></a>
    <a href="{{ route('ayuda') }}"><span class="portal-ico" aria-hidden="true"><svg class="sgd-ico" focusable="false"><use href="#i-help"/></svg></span><strong>Ayuda y manuales</strong><small>Guías paso a paso y declaración de accesibilidad.</small></a>
  </div>
</section>

<aside class="portal-aviso" aria-label="Atención presencial"><strong>¿Prefiere atención presencial?</strong> Todos los trámites también pueden hacerse en las oficinas municipales. Si tiene dificultades para usar el sistema, solicite ayuda al personal.</aside>
@endsection
