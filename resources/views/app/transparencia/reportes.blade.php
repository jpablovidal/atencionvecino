@extends('layouts.app')
@section('titulo', 'Reportes de transparencia')
@section('contenido')
<h1>Reportes de transparencia</h1>
<p class="lead-sub">Reportes de transparencia pasiva y activa. <strong>No incluyen datos personales</strong> del solicitante (solo folio).</p>
@include('app.transparencia._nav')
<form method="get" action="{{ route('app.transparencia.reportes') }}" class="card card-body mb-3"><div class="form-row">
 <div class="form-group col-md-3"><label for="r-tipo">Reporte</label><select class="form-control" id="r-tipo" name="tipo"><option value="pasiva" @selected($tipo==='pasiva')>Transparencia pasiva (solicitudes)</option><option value="activa" @selected($tipo==='activa')>Transparencia activa (publicaciones)</option></select></div>
 <div class="form-group col-md-3"><label for="r-desde">Ingresadas desde</label><input class="form-control" type="date" id="r-desde" name="desde" value="{{ $f['desde'] ?? '' }}"></div>
 <div class="form-group col-md-3"><label for="r-hasta">Ingresadas hasta</label><input class="form-control @error('hasta') is-invalid @enderror" type="date" id="r-hasta" name="hasta" value="{{ $f['hasta'] ?? '' }}">@error('hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
 <div class="form-group col-md-3"><label for="r-unidad">Dirección requerida</label><select class="form-control" id="r-unidad" name="unidad_id"><option value="">Todas</option>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected(($f['unidad_id'] ?? null)==$un->id)>{{ $un->nombre }}</option>@endforeach</select></div>
</div><div><button class="btn btn-primary">Generar</button>
 <a class="btn btn-outline-primary" href="{{ route('app.transparencia.reportes', array_filter(['tipo'=>$tipo,'desde'=>$f['desde'] ?? null,'hasta'=>$f['hasta'] ?? null,'unidad_id'=>$f['unidad_id'] ?? null]) + ['formato'=>'csv']) }}">Descargar CSV</a>
 <button type="button" class="btn btn-outline-secondary" data-print>Imprimir</button></div></form>
@if($pasiva)
@php $t = $pasiva['totales']; @endphp
@include('partials.kpis', ['kpis' => [
 ['icono'=>'folder','valor'=>$t['total'],'etiqueta'=>'Solicitudes de acceso a la información','nivel'=>'ok'],
 ['icono'=>'check-circle','valor'=>$t['respondidas_en_plazo'],'etiqueta'=>'Respondidas en plazo','nivel'=>'ok'],
 ['icono'=>'alert','valor'=>$t['respondidas_fuera_de_plazo'],'etiqueta'=>'Respondidas fuera de plazo','nivel'=>$t['respondidas_fuera_de_plazo']>0?'aviso':'ok'],
 ['icono'=>'send','valor'=>$t['derivadas'],'etiqueta'=>'Derivadas a otro órgano','nivel'=>'info'],
 ['icono'=>'clock','valor'=>$t['prorrogadas'],'etiqueta'=>'Con prórroga','nivel'=>'info'],
 ['icono'=>'alert','valor'=>$t['en_curso_vencidas'],'etiqueta'=>'En curso con plazo vencido','nivel'=>$t['en_curso_vencidas']>0?'aviso':'ok'],
]])
<p>En curso: <strong>{{ $t['en_curso'] }}</strong> · Devueltas (plazo suspendido): <strong>{{ $t['devueltas'] }}</strong> · Requerimientos pendientes: <strong>{{ $t['requerimientos_pendientes'] }}</strong> (vencidos: <strong>{{ $t['requerimientos_vencidos'] }}</strong>) · Promedio de días hábiles de respuesta: <strong>{{ $t['dias_habiles_promedio'] !== null ? number_format($t['dias_habiles_promedio'],1,',','.') : '—' }}</strong></p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Detalle de solicitudes</caption><thead><tr><th scope="col">Folio</th><th scope="col">Ingreso</th><th scope="col">Etapa</th><th scope="col">Responsable</th><th scope="col">Vence</th><th scope="col">Prórroga</th><th scope="col">Término</th><th scope="col">Días hábiles</th><th scope="col">Resultado</th><th scope="col">Req.</th></tr></thead><tbody>
@forelse($pasiva['filas'] as $x)<tr><td>{{ $x['folio'] }}</td><td>{{ $x['ingreso'] }}</td><td>{{ $x['etapa'] }}</td><td>{{ $x['responsable'] }}</td><td>{{ $x['vence'] }}</td><td>{{ $x['prorrogada'] }}</td><td>{{ $x['termino'] }}</td><td>{{ $x['dias_habiles'] }}</td><td>{{ $x['resultado'] }}{{ $x['derivada_a'] ? ': '.$x['derivada_a'] : '' }}</td><td>{{ $x['requerimientos_pendientes'] }}/{{ $x['requerimientos'] }}</td></tr>
@empty<tr><td colspan="10">No hay solicitudes en el periodo.</td></tr>@endforelse</tbody></table></div>
@endif
@if($activa)
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Transparencia activa</caption><thead><tr><th scope="col">Información</th><th scope="col">Dirección</th><th scope="col">Periodo</th><th scope="col">Estado</th><th scope="col">Límite</th><th scope="col">Publicada el</th></tr></thead><tbody>
@foreach($activa as $x)<tr><td>{{ $x['nombre'] }}</td><td>{{ $x['direccion'] }}</td><td>{{ $x['periodo'] }}</td><td>{{ ['al_dia'=>'Al día','por_vencer'=>'Por vencer','pendiente'=>'Pendiente','vencida'=>'Vencida'][$x['estado']] }}</td><td>{{ $x['limite'] }}</td><td>{{ $x['publicado_en'] ?: '—' }}</td></tr>@endforeach</tbody></table></div>
@endif
<p class="small text-muted">Los reportes se calculan al momento de generarlos y cada descarga queda en la bitácora de auditoría. Los días hábiles consideran los feriados registrados en <em>Plazos y feriados</em>.</p>
@endsection
