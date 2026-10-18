@extends('layouts.app')
@section('titulo', 'Decretos de pago')
@section('contenido')
<h1>Decretos de pago</h1>
<p class="lead-sub">Expediente de pago y proceso de compra, decreto de pago (generado con plantilla o cargado) y egreso de Tesorería.</p>
<div class="row mb-3">@foreach($fases as $k=>$v)<div class="col-6 col-lg mb-2"><div class="card h-100"><div class="card-body"><div class="h3 mb-0">{{ $porFase[$k] ?? 0 }}</div><div class="small">{{ $v }}</div></div></div></div>@endforeach</div>
<div class="mb-3">@if(auth()->user()->tienePermiso('decretopago.gestionar'))<a class="btn btn-primary mr-2" href="{{ route('app.pagos.nuevo') }}">Nuevo decreto de pago</a>@endif @if(auth()->user()->tienePermiso('decretopago.configurar'))<a class="btn btn-outline-primary" href="{{ route('app.pagos.config') }}">Correlativo y firmantes</a>@endif</div>
<form method="get" class="form-inline mb-3" aria-label="Filtrar decretos de pago">
 <label class="mr-2" for="pf">Estado</label><select class="form-control mr-3" id="pf" name="fase"><option value="">Todos</option>@foreach($fases as $k=>$v)<option value="{{ $k }}" @selected($f['fase']===$k)>{{ $v }}</option>@endforeach</select>
 <label class="mr-2" for="pq">Proveedor, RUT, factura, OC o expediente</label><input class="form-control mr-3" id="pq" name="q" maxlength="60" value="{{ $f['q'] }}"><button class="btn btn-outline-primary">Filtrar</button></form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Decretos de pago</caption>
<thead><tr><th scope="col">N°</th><th scope="col">Proveedor</th><th scope="col">Expediente de pago</th><th scope="col" class="text-right">Monto</th><th scope="col" class="text-right">Egresado</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Abrir</span></th></tr></thead><tbody>
@forelse($lista as $x)<tr><td>{{ $x->folio() ?? 'borrador #'.$x->id }}</td><td>{{ $x->proveedor_nombre }}</td><td>{{ $x->expediente->codigo }}</td><td class="text-right">${{ number_format($x->monto, 0, ',', '.') }}</td><td class="text-right">${{ number_format($x->egresado(), 0, ',', '.') }}</td>
<td><span class="estado estado-{{ $x->fase() === 'anulado' ? 'fallida' : ($x->fase() === 'borrador' ? 'pendiente' : 'vigente') }}">{{ $fases[$x->fase()] }}</span></td><td><a href="{{ route('app.pagos.ver', $x) }}">Abrir<span class="sr-only"> decreto de {{ $x->proveedor_nombre }}</span></a></td></tr>
@empty<tr><td colspan="7">No hay decretos de pago con estos filtros.</td></tr>@endforelse</tbody></table></div>
@endsection
