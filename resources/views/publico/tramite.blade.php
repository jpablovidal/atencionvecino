@extends('layouts.app')
@section('titulo', $tramite->nombre)
@section('contenido')
@php $dir = $tramite->propietaria?->nombre ?? $tramite->unidades->pluck('nombre')->first(); @endphp
<nav aria-label="Ruta de navegación" class="portal-migas"><ol><li><a href="{{ route('inicio') }}">Inicio</a></li><li><a href="{{ route('catalogo') }}">Trámites y servicios</a></li>@if($tramite->unidad_propietaria_id)<li><a href="{{ route('catalogo', ['direccion' => $tramite->unidad_propietaria_id]) }}">{{ $dir }}</a></li>@endif<li aria-current="page">{{ \Illuminate\Support\Str::limit($tramite->nombre, 50) }}</li></ol></nav>
<h1>{{ $tramite->nombre }}</h1>
<p class="chips"><span class="chip">{{ $dir }}</span><span class="chip chip-claro">Respuesta en {{ $tramite->plazo_dias }} días{{ $tramite->plazo_habiles ? ' hábiles' : '' }}</span><span class="chip chip-claro">100 % en línea con ClaveÚnica</span></p>
<div class="row mt-3"><div class="col-lg-8">
  @if($tramite->descripcion)<section class="portal-bloque" aria-labelledby="f-que"><h2 id="f-que" class="h5">¿Qué es?</h2><p style="white-space:pre-line">{{ $tramite->descripcion }}</p></section>@endif
  <section class="portal-bloque" aria-labelledby="f-nec"><h2 id="f-nec" class="h5">¿Qué necesito?</h2>
    <ul class="portal-docs">@forelse($tramite->anexos as $a)<li><span class="chip {{ $a->obligatorio ? '' : 'chip-claro' }}">{{ $a->obligatorio ? 'Obligatorio' : 'Opcional' }}</span> <strong>{{ $a->nombre }}</strong>@if($a->descripcion)<br><small class="text-muted">{{ $a->descripcion }}</small>@endif</li>@empty<li>Este trámite no requiere documentos adjuntos.</li>@endforelse</ul>
    <p class="small text-muted mb-0">Formatos admitidos: PDF, JPG, PNG o TIFF, hasta 10 MB por archivo. También necesita su <strong>ClaveÚnica</strong>.</p></section>
  @if($tramite->instrucciones)<section class="portal-bloque" aria-labelledby="f-ins"><h2 id="f-ins" class="h5">Instrucciones</h2><p style="white-space:pre-line">{{ $tramite->instrucciones }}</p></section>@endif
  <section class="portal-bloque" aria-labelledby="f-como"><h2 id="f-como" class="h5">¿Cómo lo hago?</h2>
    <ol class="portal-pasos portal-pasos-v"><li><span class="paso-n" aria-hidden="true">1</span><span>Presione <strong>Realizar este trámite en línea</strong> e ingrese con su ClaveÚnica.</span></li><li><span class="paso-n" aria-hidden="true">2</span><span>Complete el formulario por pasos y adjunte los documentos.</span></li><li><span class="paso-n" aria-hidden="true">3</span><span>Revise y envíe: recibirá su <strong>folio</strong> y un comprobante.</span></li><li><span class="paso-n" aria-hidden="true">4</span><span>Siga el estado en <strong>Mis solicitudes</strong>. Si faltan antecedentes, se le avisará y tendrá un plazo para responder.</span></li></ol></section>
  <p class="small text-muted">Última actualización: {{ ($tramite->publicado_en ?? $tramite->updated_at)?->format('d-m-Y') }}.</p>
</div>
<aside class="col-lg-4" aria-label="Realizar este trámite"><div class="portal-lateral">
  <h2 class="h5">Realice este trámite</h2>
  <dl class="small mb-3"><dt>Dirección responsable</dt><dd>{{ $tramite->unidades->pluck('nombre')->implode(', ') ?: $dir }}</dd><dt>Plazo de respuesta</dt><dd>{{ $tramite->plazo_dias }} días{{ $tramite->plazo_habiles ? ' hábiles' : '' }}</dd></dl>
  <a class="btn btn-primary btn-block" href="{{ route('catalogo.iniciar', $tramite->id) }}">Realizar este trámite en línea</a>
  <p class="small text-muted mt-2 mb-0">Si no ha iniciado sesión, se le pedirá ingresar con ClaveÚnica y volverá directamente a este trámite.</p>
</div>
@if($relacionados->isNotEmpty())<div class="portal-lateral mt-3"><h2 class="h6">Otros trámites de esta dirección</h2><ul class="list-unstyled small mb-0">@foreach($relacionados as $r)<li class="mb-1"><a href="{{ route('catalogo.ficha', [$r->id, \Illuminate\Support\Str::slug($r->nombre)]) }}">{{ $r->nombre }}</a></li>@endforeach</ul></div>@endif
</aside></div>
@endsection
