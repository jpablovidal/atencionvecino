@php
  $dirT = $tramite->propietaria?->nombre ?? $tramite->unidades->pluck('nombre')->first();
  $actT = ($tramite->publicado_en ?? $tramite->updated_at)?->locale('es')->translatedFormat('d \d\e F, Y');
@endphp
<h1>{{ $tramite->nombre }}</h1>
<p class="portal-actualizado">Última actualización: {{ $actT }}</p>
@if($tramite->descripcion)<section class="portal-bloque" aria-labelledby="tf-desc"><h2 id="tf-desc" class="h5">Descripción:</h2><p style="white-space:pre-line">{{ $tramite->descripcion }}</p></section>@endif
<div class="portal-acordeon">
  <details open><summary>¿Qué documentos debe adjuntar?</summary><div class="portal-acordeon-cuerpo">
    <ul class="portal-docs">@forelse($tramite->anexos as $a)<li><span class="chip {{ $a->obligatorio ? '' : 'chip-claro' }}">{{ $a->obligatorio ? 'Obligatorio' : 'Opcional' }}</span> <strong>{{ $a->nombre }}</strong>@if($a->descripcion)<br><small class="text-muted">{{ $a->descripcion }}</small>@endif</li>@empty<li>Este trámite no requiere documentos.</li>@endforelse</ul></div></details>
  @if($tramite->instrucciones)<details><summary>Instrucciones</summary><div class="portal-acordeon-cuerpo"><p class="mb-0" style="white-space:pre-line">{{ $tramite->instrucciones }}</p></div></details>@endif
  <details><summary>¿Cuánto demora y quién lo atiende?</summary><div class="portal-acordeon-cuerpo"><dl class="mb-0"><dt>Plazo de respuesta</dt><dd>{{ $tramite->plazo_dias }} días{{ $tramite->plazo_habiles ? ' hábiles' : '' }}</dd><dt>Dirección responsable</dt><dd>{{ $tramite->unidades->pluck('nombre')->implode(', ') ?: $dirT }}</dd></dl></div></details>
</div>
