@extends('layouts.app')
@section('titulo', 'Datos personales (DPO)')
@section('contenido')
<h1>Tratamiento de datos personales</h1>
<p class="lead-sub">Solicitudes según lo que el titular indicó en el último paso del formulario (Política de Privacidad, Decreto Alcaldicio N° 2590). Quien <strong>no autoriza</strong> el tratamiento igual puede ingresar su solicitud: el sistema solo la marca para que el Oficial de Protección de Datos (DPO) lo sepa. Por protección de datos solo se muestra el RUN parcialmente enmascarado.</p>
<div class="row mb-3">@foreach($kpis as $k)<div class="col-sm-4 mb-2"><div class="card h-100"><div class="card-body"><div class="h3 mb-0">{{ $k['valor'] }}</div><div class="small">{{ $k['etiqueta'] }}</div></div></div></div>@endforeach</div>
<form method="get" class="form-inline mb-3" aria-label="Filtrar solicitudes">
 <label class="mr-2" for="fe">Mostrar</label><select class="form-control mr-3" id="fe" name="estado">@foreach(['sin_consentimiento'=>'No autorizaron','con_consentimiento'=>'Autorizaron','sin_dato'=>'Sin dato'] as $v=>$l)<option value="{{ $v }}" @selected($f['estado']===$v)>{{ $l }}</option>@endforeach</select>
 <label class="mr-2" for="ff">N° de solicitud</label><input class="form-control mr-3" id="ff" name="folio" value="{{ $f['folio'] ?? '' }}" maxlength="30">
 <label class="mr-2" for="fd">Desde</label><input class="form-control mr-3" type="date" id="fd" name="desde" value="{{ $f['desde'] ?? '' }}">
 <label class="mr-2" for="fh">Hasta</label><input class="form-control mr-3" type="date" id="fh" name="hasta" value="{{ $f['hasta'] ?? '' }}">
 <button class="btn btn-primary">Filtrar</button></form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Solicitudes por consentimiento de datos personales</caption>
<thead><tr><th scope="col">Solicitud</th><th scope="col">Ingreso</th><th scope="col">Titular</th><th scope="col">Trámite</th><th scope="col">Dirección responsable</th><th scope="col">Estado</th><th scope="col">Consentimiento</th></tr></thead><tbody>
@forelse($filas as $s)<tr>
 <td>@if($s->expediente)<a href="{{ route('app.expediente', $s->expediente) }}">{{ $s->folio }}</a>@else{{ $s->folio }}@endif</td>
 <td>{{ $s->created_at?->timezone('America/Santiago')->format('d-m-Y H:i') }}</td>
 <td>{{ $enmascarar($s->run) }}</td>
 <td>{{ $s->tramite?->nombre }}</td>
 <td>{{ $s->expediente?->unidad?->nombre ?? '—' }}</td>
 <td>{{ str_replace('_', ' ', $s->estado) }}</td>
 <td>@if($s->consentimiento_datos === false)<span class="estado estado-fallida">No autoriza</span>@elseif($s->consentimiento_datos)<span class="estado estado-vigente">Autoriza</span>@else<span class="estado">Sin dato</span>@endif @if($s->consentimiento_en)<span class="small text-muted">{{ $s->consentimiento_en->timezone('America/Santiago')->format('d-m-Y H:i') }}</span>@endif</td>
</tr>@empty<tr><td colspan="7" class="text-muted">No hay solicitudes con este criterio.</td></tr>@endforelse
</tbody></table></div>
{{ $filas->links() }}
@endsection
