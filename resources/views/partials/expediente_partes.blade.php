{{-- Partes del expediente y notificación de los documentos a cada una por su canal. Variables: $e, $puedeOperar, $partes, $docsVigentes, $ocultos --}}
<h2 class="h4 mt-4" id="partes">Partes y notificación</h2>
<p class="small text-muted">Personas u organismos a quienes interesa o se dirige el procedimiento. Al concluir (expediente cerrado) puede notificarles los documentos: a vecinos por Casilla Única, correo o entrega física; a funcionarios con un aviso interno que les queda como acción pendiente; a personas externas por correo o entrega física; y a organismos públicos por <strong>DocDigital</strong>.</p>
@if($partes->isEmpty())<p>Aún no hay partes registradas.</p>
@else
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Partes del expediente</caption><thead><tr><th scope="col">Parte</th><th scope="col">Tipo</th><th scope="col">Rol</th><th scope="col">Canal</th><th scope="col">Notificaciones</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($partes as $p)<tr><td>{{ $p->nombre }}@if($p->tipo==='ciudadano' && $p->run)<br><small class="text-muted">{{ \App\Support\Run::enmascarar($p->run) }}</small>@endif @if($p->tipo==='organismo' && $p->personasDd())<br><small class="text-muted">Personas: {{ collect($p->personasDd())->pluck('nombre')->implode(', ') }}</small>@endif @if($p->correo)<br><small class="text-muted">{{ $p->correo }}</small>@endif</td>
<td>{{ \App\Models\ExpedienteParte::TIPOS[$p->tipo] }}</td><td>{{ \App\Models\ExpedienteParte::ROLES[$p->rol] ?? $p->rol }}</td><td>{{ \App\Models\ExpedienteParte::CANALES[$p->tipo][$p->canal] ?? $p->canal }}</td>
<td>@php $ns = $e->notificaciones->where('parte_id', $p->id); @endphp @forelse($ns as $n)<div><small>{{ $n->documento?->numeroFormateado() }}: <span class="estado estado-{{ $n->estado }}">{{ $n->estado }}</span></small></div>@empty <span class="text-muted">—</span> @endforelse</td>
<td>@if($puedeOperar && $ns->isEmpty())<form method="post" action="{{ route('app.expediente.partes.quitar', [$e, $p]) }}" data-confirm="¿Quitar a esta parte del expediente?">@csrf<button class="btn btn-sm btn-outline-secondary">Quitar<span class="sr-only"> a {{ $p->nombre }}</span></button></form>@endif</td></tr>@endforeach</tbody></table></div>
@endif

@if($puedeOperar)
<details class="mb-3"><summary class="h6">Agregar una parte</summary>
<form method="post" action="{{ route('app.expediente.partes.agregar', $e) }}" class="card card-body mt-2">@csrf
  <div class="form-row">
    <div class="form-group col-md-4"><label for="pt_tipo">Tipo de parte</label><select class="form-control" id="pt_tipo" name="tipo" data-alterna-parte>@foreach(\App\Models\ExpedienteParte::TIPOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
    <div class="form-group col-md-4"><label for="pt_rol">Rol</label><select class="form-control" id="pt_rol" name="rol">@foreach(\App\Models\ExpedienteParte::ROLES as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
    <div class="form-group col-md-4"><label for="pt_canal">Cómo se le notificará</label><select class="form-control" id="pt_canal" name="canal">@foreach(\App\Models\ExpedienteParte::CANALES as $tipo => $cs)@foreach($cs as $k => $t)<option value="{{ $k }}" data-tipo="{{ $tipo }}">{{ $t }}</option>@endforeach @endforeach</select></div>
  </div>
  <div data-parte="ciudadano externo"><div class="form-row"><div class="form-group col-md-4"><label for="pt_nombre">Nombre completo o razón social</label><input class="form-control" id="pt_nombre" name="nombre" maxlength="190"></div>
    <div class="form-group col-md-4"><label for="pt_run">RUN (obligatorio para vecinos)</label><input class="form-control" id="pt_run" name="run" maxlength="14" placeholder="12.345.678-5"></div>
    <div class="form-group col-md-4"><label for="pt_correo">Correo electrónico</label><input class="form-control" id="pt_correo" type="email" name="correo" maxlength="190"></div></div></div>
  <div data-parte="funcionario" hidden><div class="form-group"><label for="pt_func">Funcionario(a)</label><select class="form-control" id="pt_func" name="usuario_id"><option value="">Elija…</option>@foreach($funcionarios as $f)<option value="{{ $f->id }}">{{ $f->nombreCompleto() }}@if($f->cargo) — {{ $f->cargo }}@endif</option>@endforeach</select></div></div>
  <div data-parte="organismo" hidden><div class="form-row"><div class="form-group col-md-6"><label for="pt_ent_q">Organismo en DocDigital</label><input class="form-control" id="pt_ent_q" type="search" autocomplete="off" maxlength="100" placeholder="Escriba al menos 2 letras" data-buscar-dd="entidad" data-url="{{ route('app.docdigital.directorio.buscar') }}" data-salida="pt_ent_id" data-elegida="pt_ent_elegida" data-limpiar="pt_per_elegidas" data-lista="pt_ent_lista" aria-controls="pt_ent_lista"><input type="hidden" id="pt_ent_id" name="dd_entidad_id"><ul class="list-group mt-1" id="pt_ent_lista" role="listbox" aria-label="Organismos encontrados"></ul><p class="small mb-0" id="pt_ent_elegida" aria-live="polite"></p></div>
    <div class="form-group col-md-6"><label for="pt_per_q">Personas de ese organismo (opcional)</label><input class="form-control" id="pt_per_q" type="search" autocomplete="off" maxlength="100" data-buscar-dd="persona" data-url="{{ route('app.docdigital.directorio.buscar') }}" data-lista="pt_per_lista" data-entidad="pt_ent_id" data-multiple="dd_usuarios[]" data-elegidas="pt_per_elegidas" aria-controls="pt_per_lista"><ul class="list-group mt-1" id="pt_per_lista" role="listbox" aria-label="Personas encontradas"></ul><ul class="list-inline mb-0" id="pt_per_elegidas" aria-live="polite"></ul></div></div></div>
  <div class="form-group"><label for="pt_notas">Notas (opcional)</label><input class="form-control" id="pt_notas" name="notas" maxlength="250"></div>
  <div><button class="btn btn-primary">Agregar parte</button></div>
</form></details>
@endif

@if($puedeOperar && $partes->isNotEmpty() && auth()->user()->tienePermiso('notificaciones.gestionar'))
@if($e->estado==='cerrado')
<form method="post" action="{{ route('app.expediente.partes.notificar', $e) }}" class="card card-body mb-3">@csrf
  <h3 class="h6">Notificar documentos a las partes</h3>
  <fieldset class="form-group"><legend class="small">Partes</legend>@foreach($partes as $p)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="np_{{ $p->id }}" name="partes[]" value="{{ $p->id }}"><label class="custom-control-label" for="np_{{ $p->id }}">{{ $p->nombre }} <small class="text-muted">({{ \App\Models\ExpedienteParte::CANALES[$p->tipo][$p->canal] ?? $p->canal }})</small></label></div>@endforeach</fieldset>
  <fieldset class="form-group"><legend class="small">Documentos vigentes</legend>@forelse($docsVigentes as $d)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="nd_{{ $d->id }}" name="documentos[]" value="{{ $d->id }}"><label class="custom-control-label" for="nd_{{ $d->id }}">{{ $d->numeroFormateado() }} — {{ $d->titulo }}</label></div>@empty<p class="mb-0 text-muted">No hay documentos vigentes.</p>@endforelse</fieldset>
  <div><button class="btn btn-primary">Notificar</button></div>
</form>
@else<p class="small text-muted">Cierre el expediente para notificar los documentos a las partes.</p>@endif
@endif
@unless($e->solicitud)
<h3 class="h5 mt-3" id="notificaciones">Notificaciones</h3>
@if($e->notificaciones->isEmpty())<p>Aún no se han enviado notificaciones.</p>@else
@include('partials.expediente_notificaciones', ['e' => $e, 'puedeOperar' => $puedeOperar])
@endif
@endunless
