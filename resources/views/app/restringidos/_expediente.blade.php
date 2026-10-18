@php $nombre = fn ($u) => trim(($u->nombres ?? '').' '.($u->apellidos ?? '')); @endphp
<section class="card mb-4" aria-labelledby="rs-t"><div class="card-body">
<h2 class="h5" id="rs-t">Acceso restringido (protección de datos personales)</h2>
@if($e->restringido)
 <p class="mb-2"><span class="chip chip-claro">Expediente restringido</span> Solo ven su contenido las personas autorizadas. Motivo: {{ $e->restringido_motivo }}</p>
@else
 <p class="mb-2">Este expediente no está restringido. Puede restringirlo completo o marcar documentos y archivos específicos; quien no esté autorizado no verá su contenido y cada intento quedará registrado.</p>
@endif
@if(! $r['autorizado'] && $e->restringido)
 <p class="small text-muted mb-0">Usted no está autorizado(a) a gestionar esta restricción.</p>
@elseif($r['gestiona'])
 @if($r['autorizaciones']->isNotEmpty())
 <h3 class="h6">Personas autorizadas</h3>
 <ul>@foreach($r['autorizaciones'] as $a)<li>{{ $nombre($a->usuario) }}@if($a->usuario?->cargo), {{ $a->usuario->cargo }}@endif
  — {{ $a->archivo_id ? 'archivo #'.$a->archivo_id : ($a->documento_id ? 'documento #'.$a->documento_id : 'todo el expediente') }}; motivo: {{ $a->motivo }}@if($a->vence_en); hasta {{ \Illuminate\Support\Carbon::parse($a->vence_en)->format('d-m-Y') }}@endif
  <form method="post" action="{{ route('app.restringidos.revocar', $a->id) }}" class="d-inline" data-confirm="¿Revocar esta autorización?">@csrf<button class="btn btn-sm btn-outline-secondary">Revocar<span class="sr-only"> a {{ $nombre($a->usuario) }}</span></button></form></li>@endforeach</ul>
 @endif
 <div class="row">
 <div class="col-lg-6 mb-3"><h3 class="h6">{{ $e->restringido ? 'Levantar o ampliar' : 'Restringir' }}</h3>
  @if(! $e->restringido)
  <form method="post" action="{{ route('app.restringidos.restringir', $e) }}">@csrf
   <div class="form-group"><label for="rs-m">Motivo (obligatorio)</label><textarea class="form-control" id="rs-m" name="motivo" rows="2" maxlength="600" required></textarea></div>
   <button class="btn btn-primary">Restringir expediente</button></form>
  @else
  <form method="post" action="{{ route('app.restringidos.liberar', $e) }}" data-confirm="¿Levantar la restricción del expediente?">@csrf<button class="btn btn-outline-primary">Levantar restricción del expediente</button></form>
  @endif
 </div>
 <div class="col-lg-6 mb-3"><h3 class="h6">Autorizar a una persona</h3>
  <form method="post" action="{{ route('app.restringidos.autorizar', $e) }}">@csrf
   <div class="form-group"><label for="rs-u">Persona (necesidad de conocer)</label><select class="form-control" id="rs-u" name="usuario_id" required><option value="">Seleccione…</option>@foreach($r['candidatos'] as $c)<option value="{{ $c->id }}">{{ $nombre($c) }}@if($c->cargo) — {{ $c->cargo }}@endif</option>@endforeach</select></div>
   <div class="form-group"><label for="rs-am">Motivo (obligatorio)</label><input class="form-control" id="rs-am" name="motivo" maxlength="600" required></div>
   <div class="form-group"><label for="rs-v">Vence el (máximo 1 año)</label><input class="form-control" type="date" id="rs-v" name="vence_en"></div>
   <button class="btn btn-outline-primary">Autorizar</button></form>
 </div></div>
 <p class="small text-muted mb-0">Para restringir un documento o archivo puntual use los botones de cada ítem en su sección. Administración del sistema no ve el contenido restringido; solo la bitácora de accesos.</p>
@endif
</div></section>
