@extends('layouts.app')
@section('titulo', $tramite->nombre)
@section('contenido')
@php
  $dir = $tramite->propietaria?->nombre ?? $tramite->unidades->pluck('nombre')->first();
  $ficha = fn ($t) => route('catalogo.ficha', [$t->id, \Illuminate\Support\Str::slug($t->nombre)]);
  $actualizado = ($tramite->publicado_en ?? $tramite->updated_at)?->locale('es')->translatedFormat('d \d\e F, Y');
  $otras = $tramite->unidades->where('id', '!=', $tramite->unidad_propietaria_id)->pluck('nombre');
  $plazo = $tramite->plazo_dias.' días'.($tramite->plazo_habiles ? ' hábiles' : '');
@endphp
<nav aria-label="Usted está en" class="portal-migas"><span class="portal-migas-et">Usted está en:</span><ol><li><a href="{{ route('inicio') }}">Inicio</a></li><li><a href="{{ route('catalogo') }}">Trámites y servicios</a></li>@if($tramite->unidad_propietaria_id)<li><a href="{{ route('catalogo', ['direccion' => $tramite->unidad_propietaria_id]) }}">{{ $dir }}</a></li>@endif<li aria-current="page">{{ \Illuminate\Support\Str::limit($tramite->nombre, 50) }}</li></ol></nav>
<h1>{{ $tramite->nombre }}</h1>
<p class="portal-actualizado">Última actualización: {{ $actualizado }}</p>

<section class="portal-bloque" aria-labelledby="f-como">
  <h2 id="f-como" class="h5">¿Cómo se puede realizar este trámite?</h2>
  <ul class="portal-canales">
    <li><svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-check-circle"/></svg><span>Online</span><span class="sr-only">: disponible</span></li>
    <li><svg class="sgd-ico" aria-hidden="true" focusable="false"><use href="#i-check-circle"/></svg><span>Presencial</span><span class="sr-only">: disponible en las oficinas municipales</span></li>
  </ul>
  <p class="mb-2"><a class="btn btn-primary" href="{{ route('catalogo.iniciar', $tramite->id) }}">Realizar este trámite en línea</a></p>
  <p class="small text-muted mb-0">Si no ha iniciado sesión, se le pedirá ingresar con <strong>ClaveÚnica</strong> y volverá directamente a este trámite.</p>
</section>

@if($tramite->descripcion)
<section class="portal-bloque" aria-labelledby="f-desc"><h2 id="f-desc" class="h5">Descripción:</h2><p style="white-space:pre-line">{{ $tramite->descripcion }}</p></section>
@endif

<div class="portal-acordeon">
  <details open><summary>¿Qué documentos debe presentar?</summary><div class="portal-acordeon-cuerpo">
    <ul class="portal-docs">@forelse($tramite->anexos as $a)<li><span class="chip {{ $a->obligatorio ? '' : 'chip-claro' }}">{{ $a->obligatorio ? 'Obligatorio' : 'Opcional' }}</span> <strong>{{ $a->nombre }}</strong>@if($a->descripcion)<br><small class="text-muted">{{ $a->descripcion }}</small>@endif</li>@empty<li>Este trámite no requiere documentos adjuntos.</li>@endforelse</ul>
    <p class="small text-muted mb-0">Formatos admitidos: {{ \App\Support\UploadValidator::descripcionFormatos() }}, hasta {{ round(\App\Services\Admin\ArchivoConfigService::maxBytes('ciudadano') / 1048576) }} MB por archivo. También necesita su <strong>ClaveÚnica</strong>.</p></div></details>
  @if($tramite->instrucciones)<details><summary>Instrucciones</summary><div class="portal-acordeon-cuerpo"><p class="mb-0" style="white-space:pre-line">{{ $tramite->instrucciones }}</p></div></details>@endif
  <details><summary>¿Cuánto demora y quién lo atiende?</summary><div class="portal-acordeon-cuerpo">
    <dl class="mb-0"><dt>Plazo de respuesta</dt><dd>{{ $plazo }}</dd><dt>Dirección responsable</dt><dd>{{ $tramite->unidades->pluck('nombre')->implode(', ') ?: $dir }}</dd></dl></div></details>
  <details><summary>¿Cómo lo hago en línea?</summary><div class="portal-acordeon-cuerpo">
    <ol class="portal-pasos portal-pasos-v"><li><span class="paso-n" aria-hidden="true">1</span><span>Presione <strong>Realizar este trámite en línea</strong> e ingrese con su ClaveÚnica.</span></li><li><span class="paso-n" aria-hidden="true">2</span><span>Complete el formulario por pasos y adjunte los documentos.</span></li><li><span class="paso-n" aria-hidden="true">3</span><span>Revise y envíe: recibirá su <strong>folio</strong> y un comprobante.</span></li><li><span class="paso-n" aria-hidden="true">4</span><span>Siga el estado en <strong>Mis solicitudes</strong>. Si faltan antecedentes, se le avisará y tendrá un plazo para responder.</span></li></ol></div></details>
</div>

<div class="portal-etiquetas">
  <p class="mb-1">Publicado en:</p>
  <p><a class="portal-tag" href="{{ route('catalogo', ['direccion' => $tramite->unidad_propietaria_id]) }}">#{{ $dir }}</a></p>
  @if($otras->isNotEmpty())<p class="mb-1">También lo atienden:</p><p>@foreach($otras as $o)<span class="portal-tag portal-tag-2">#{{ $o }}</span> @endforeach</p>@endif
  @if($relacionados->isNotEmpty())
  <p class="mb-2">Trámites relacionados:</p>
  <div class="portal-relacionados">@foreach($relacionados as $r)
    <article class="portal-rel"><p class="small text-muted mb-1">Publicado por {{ $dir }}</p><h3 class="h6"><a href="{{ $ficha($r) }}">{{ $r->nombre }}</a></h3>@if($r->descripcion)<p class="mb-0">{{ \Illuminate\Support\Str::limit($r->descripcion, 110) }}</p>@endif</article>
  @endforeach</div>
  @endif
</div>
@endsection
