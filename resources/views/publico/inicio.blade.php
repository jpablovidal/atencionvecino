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
<p class="mt-2 mb-0"><button type="button" class="btn btn-sm btn-outline-light" data-recorrido>Hacer el recorrido guiado de esta página</button></p>
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
  <ol class="list-unstyled portal-faq" id="faqAcordeon">
    <li class="border-bottom border-accent">
      <button class="btn text-left w-100 d-flex align-items-start justify-content-between py-3 px-3" id="faqEnc1" type="button" data-toggle="collapse" data-target="#faqCuerpo1" aria-controls="faqCuerpo1" aria-expanded="true"><span class="h5 mb-0">¿Necesito ClaveÚnica para hacer un trámite?</span><span class="toggler-icon cl cl-m-arrow-down" aria-hidden="true"></span></button>
      <div class="collapse show" id="faqCuerpo1" aria-labelledby="faqEnc1" data-parent="#faqAcordeon"><div class="pr-5 pb-3 pl-3"><p class="mb-0">Sí. Su identidad la verifica ClaveÚnica y no puede modificarse. Puede revisar los requisitos de cualquier trámite sin ingresar.</p></div></div>
    </li>
    <li class="border-bottom border-accent">
      <button class="btn text-left w-100 d-flex align-items-start justify-content-between py-3 px-3" id="faqEnc2" type="button" data-toggle="collapse" data-target="#faqCuerpo2" aria-controls="faqCuerpo2" aria-expanded="false"><span class="h5 mb-0">¿Cómo sé en qué estado está mi solicitud?</span><span class="toggler-icon cl cl-m-arrow-down" aria-hidden="true"></span></button>
      <div class="collapse" id="faqCuerpo2" aria-labelledby="faqEnc2" data-parent="#faqAcordeon"><div class="pr-5 pb-3 pl-3"><p class="mb-0">Al enviar recibirá un folio y un comprobante. En «Mis solicitudes» ve cada movimiento con su fecha y la dirección municipal responsable, y recibe aviso por cada cambio.</p></div></div>
    </li>
    <li class="border-bottom border-accent">
      <button class="btn text-left w-100 d-flex align-items-start justify-content-between py-3 px-3" id="faqEnc3" type="button" data-toggle="collapse" data-target="#faqCuerpo3" aria-controls="faqCuerpo3" aria-expanded="false"><span class="h5 mb-0">¿Puedo hacer mi trámite de forma presencial?</span><span class="toggler-icon cl cl-m-arrow-down" aria-hidden="true"></span></button>
      <div class="collapse" id="faqCuerpo3" aria-labelledby="faqEnc3" data-parent="#faqAcordeon"><div class="pr-5 pb-3 pl-3"><p class="mb-0">Sí. Todos los trámites pueden hacerse también en las oficinas municipales.</p></div></div>
    </li>
    <li class="border-bottom border-accent">
      <button class="btn text-left w-100 d-flex align-items-start justify-content-between py-3 px-3" id="faqEnc4" type="button" data-toggle="collapse" data-target="#faqCuerpo4" aria-controls="faqCuerpo4" aria-expanded="false"><span class="h5 mb-0">¿Cuánto demora la respuesta?</span><span class="toggler-icon cl cl-m-arrow-down" aria-hidden="true"></span></button>
      <div class="collapse" id="faqCuerpo4" aria-labelledby="faqEnc4" data-parent="#faqAcordeon"><div class="pr-5 pb-3 pl-3"><p class="mb-0">Cada trámite indica su plazo de respuesta en días (hábiles o corridos). Los feriados registrados por el municipio no cuentan como días administrativos.</p></div></div>
    </li>
  </ol>
</section>

<section aria-labelledby="serv-t" class="portal-seccion">
  <div class="portal-seccion-cab"><h2 id="serv-t">Otros servicios para el vecino</h2></div>
  <div class="row">
    <div class="col-md-6"><a class="banner mb-3" href="{{ route('login') }}"><div class="line"></div><span class="banner-icon cl cl-briefcase" aria-hidden="true"></span><h3 class="banner-text flex-column h5 mb-0"><span class="font-weight-bold">Mis solicitudes</span><span class="small">Ingrese con ClaveÚnica y siga el estado de lo que ha presentado.</span></h3></a></div>
    <div class="col-md-6"><a class="banner mb-3" href="{{ route('validar.form') }}"><div class="line"></div><span class="banner-icon cl cl-document-verified" aria-hidden="true"></span><h3 class="banner-text flex-column h5 mb-0"><span class="font-weight-bold">Validar un documento</span><span class="small">Compruebe su autenticidad con el código SDD.</span></h3></a></div>
    <div class="col-md-6"><a class="banner mb-3" href="{{ route('publico.listado') }}"><div class="line"></div><span class="banner-icon cl cl-authority" aria-hidden="true"></span><h3 class="banner-text flex-column h5 mb-0"><span class="font-weight-bold">Documentos públicos</span><span class="small">Decretos, ordenanzas y otros documentos vigentes.</span></h3></a></div>
    <div class="col-md-6"><a class="banner mb-3" href="{{ route('ayuda.manual', 'ciudadano') }}"><div class="line"></div><span class="banner-icon cl cl-reading" aria-hidden="true"></span><h3 class="banner-text flex-column h5 mb-0"><span class="font-weight-bold">Manual del ciudadano</span><span class="small">Guía paso a paso para hacer sus trámites y declaración de accesibilidad.</span></h3></a></div>
  </div>
</section>

<aside class="portal-aviso" aria-label="Atención presencial"><strong>¿Prefiere atención presencial?</strong> Todos los trámites también pueden hacerse en las oficinas municipales. Si tiene dificultades para usar el sistema, solicite ayuda al personal.</aside>
@endsection
