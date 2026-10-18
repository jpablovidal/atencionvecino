@extends('layouts.app')
@section('titulo', 'Configuración del decreto de pago')
@section('contenido')
@if(auth()->user()->tienePermiso('decretopago.ver'))<p><a href="{{ route('app.pagos') }}">← Decretos de pago</a></p>@endif
<h1>Correlativo y firmantes</h1>
<p class="lead-sub">Cada documento (decreto de pago y egreso) tiene su <strong>correlativo propio</strong> y su lista de <strong>visadores y firmantes</strong> en orden. La plantilla del decreto se administra en <a href="{{ route('app.admin.plantillas') }}">Plantillas</a>. Cada cambio queda en la auditoría.</p>
@foreach($docs as $clave => $x) @php $t = $x['tipo']; @endphp
<section class="card mb-4" aria-labelledby="h-{{ $clave }}"><div class="card-body">
<h2 class="h4" id="h-{{ $clave }}">{{ $x['nombre'] }}</h2>
<h3 class="h6">Correlativo — próximo folio: <strong>{{ $x['proximo'] }}</strong></h3>
<form method="post" action="{{ route('app.pagos.config.correlativo', $clave) }}" class="mb-2">@csrf
<div class="form-row"><div class="form-group col-md-4"><label for="ff-{{ $clave }}">Formato</label><input class="form-control" id="ff-{{ $clave }}" name="folio_formato" value="{{ $t->folio_formato ?: \App\Services\FolioService::FORMATO_DEFECTO }}" maxlength="80" required><small class="form-text text-muted">Campos: {PREFIJO} {TIPO} {N} {AAAA} {AA}</small></div>
<div class="form-group col-md-2"><label for="fd-{{ $clave }}">Dígitos</label><input class="form-control" type="number" min="0" max="8" id="fd-{{ $clave }}" name="folio_digitos" value="{{ $t->folio_digitos }}" required></div>
<div class="form-group col-md-2"><label for="fi-{{ $clave }}">Número inicial</label><input class="form-control" type="number" min="1" id="fi-{{ $clave }}" name="folio_inicio" value="{{ $t->folio_inicio }}" required></div>
<div class="form-group col-md-4 d-flex align-items-end"><div class="custom-control custom-checkbox"><input type="hidden" name="folio_reinicio_anual" value="0"><input class="custom-control-input" type="checkbox" id="fr-{{ $clave }}" name="folio_reinicio_anual" value="1" @checked($t->folio_reinicio_anual)><label class="custom-control-label" for="fr-{{ $clave }}">Reiniciar cada año</label></div></div></div>
<button class="btn btn-primary">Guardar correlativo</button></form>
<form method="post" action="{{ route('app.pagos.config.ajustar', $clave) }}" class="form-inline mb-4">@csrf
<span class="mr-2 small">Último emitido: @forelse($x['contadores'] as $c){{ $c['anio'] ?: 'continuo' }}: {{ $c['ultimo'] }}@unless($loop->last), @endunless @empty ninguno @endforelse. Subir contador (solo hacia arriba):</span>
<label class="sr-only" for="aa-{{ $clave }}">Año (0 si es continuo)</label><input class="form-control form-control-sm mr-1" style="width:6rem" type="number" min="0" id="aa-{{ $clave }}" name="anio" value="{{ $t->folio_reinicio_anual ? $anio : 0 }}" required>
<label class="sr-only" for="au-{{ $clave }}">Nuevo último número</label><input class="form-control form-control-sm mr-1" style="width:8rem" type="number" min="0" id="au-{{ $clave }}" name="ultimo" placeholder="Último" required>
<label class="sr-only" for="am-{{ $clave }}">Motivo</label><input class="form-control form-control-sm mr-1" id="am-{{ $clave }}" name="motivo" placeholder="Motivo" required minlength="5" maxlength="255"><button class="btn btn-sm btn-outline-secondary">Ajustar</button></form>

<h3 class="h6">Visadores y firmantes (en orden)</h3>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Participantes del {{ $x['nombre'] }}</caption><thead><tr><th scope="col">Orden</th><th scope="col">Persona</th><th scope="col">Cargo</th><th scope="col">Función</th><th scope="col">Firma</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($x['firmantes'] as $f)<tr><td>{{ $f->orden }}</td><td>{{ $f->usuario->nombreCompleto() }}</td><td>{{ $f->cargo }}</td><td>{{ $funciones[$f->funcion] }}</td><td>{{ $f->funcion==='firma' ? $firmas[$f->firma_tipo] : '—' }}</td><td>{{ $f->activo ? 'Activo' : 'Inactivo' }}</td>
<td class="text-nowrap">@foreach(['subir'=>'Subir','bajar'=>'Bajar','alternar'=>($f->activo ? 'Desactivar' : 'Activar'),'quitar'=>'Quitar'] as $ac=>$et)<form method="post" action="{{ route('app.pagos.config.accion', [$f, $ac]) }}" class="d-inline" @if($ac==='quitar') data-confirm="¿Quitar a {{ $f->usuario->nombreCompleto() }} de la lista?" @endif>@csrf<button class="btn btn-sm btn-outline-secondary">{{ $et }}<span class="sr-only"> a {{ $f->usuario->nombreCompleto() }}</span></button></form> @endforeach</td></tr>
@empty<tr><td colspan="7">Aún no hay participantes configurados.</td></tr>@endforelse</tbody></table></div>
<form method="post" action="{{ route('app.pagos.config.firmante', $clave) }}" class="form-row">@csrf
<div class="form-group col-md-3"><label for="u-{{ $clave }}">Persona</label><select class="form-control" id="u-{{ $clave }}" name="usuario_id" required><option value="">Seleccione…</option>@foreach($usuarios as $u)<option value="{{ $u->id }}">{{ $u->nombreCompleto() }}@if($u->cargo) — {{ $u->cargo }}@endif</option>@endforeach</select></div>
<div class="form-group col-md-3"><label for="c-{{ $clave }}">Cargo en el documento</label><input class="form-control" id="c-{{ $clave }}" name="cargo" required maxlength="120" placeholder="Tesorero(a) Municipal"></div>
<div class="form-group col-md-2"><label for="fu-{{ $clave }}">Función</label><select class="form-control" id="fu-{{ $clave }}" name="funcion">@foreach($funciones as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
<div class="form-group col-md-3"><label for="ft-{{ $clave }}">Tipo de firma</label><select class="form-control" id="ft-{{ $clave }}" name="firma_tipo">@foreach($firmas as $k=>$v)@if($k==='avanzada' || $t->permite_firma_simple)<option value="{{ $k }}">{{ $v }}</option>@endif @endforeach</select></div>
<div class="form-group col-md-1 d-flex align-items-end"><button class="btn btn-primary">Agregar</button></div></form>
<p class="small text-muted mb-0">Cantidad de firmantes exigida por el tipo de documento: {{ $t->firmas_agil }} (flujo ágil) o {{ $t->firmas_clasico }} (clásico). @if(! $t->permite_firma_simple)Este documento solo admite firma avanzada.@else Admite firma electrónica simple (Ley 19.799; su valor para egresos es <strong>A VALIDAR</strong>).@endif</p>
</div></section>
@endforeach
@endsection
