<h1>{{ $tramite->nombre }}</h1>
@if($tramite->descripcion)<p style="white-space:pre-line">{{ $tramite->descripcion }}</p>@endif
@if($tramite->instrucciones)<h2 class="h5">Instrucciones</h2><p style="white-space:pre-line">{{ $tramite->instrucciones }}</p>@endif
<p class="small text-muted">Plazo de respuesta: {{ $tramite->plazo_dias }} días. Dirección responsable: {{ $tramite->unidades->pluck('nombre')->implode(', ') }}.</p>
<h2 class="h5">Documentos que deberá adjuntar</h2>
<ul>@forelse($tramite->anexos as $a)<li>{{ $a->nombre }} — {{ $a->obligatorio ? 'obligatorio' : 'opcional' }}@if($a->descripcion)<br><small class="text-muted">{{ $a->descripcion }}</small>@endif</li>@empty<li>Este trámite no requiere documentos.</li>@endforelse</ul>
