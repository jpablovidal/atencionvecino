@extends('layouts.app')
@section('titulo', 'Nuevo decreto de pago')
@section('contenido')
<p><a href="{{ route('app.pagos') }}">← Decretos de pago</a></p>
<h1>Nuevo decreto de pago</h1>
<form method="post" action="{{ route('app.pagos.crear') }}" class="mb-4" data-pasos>@csrf
<fieldset data-paso="Expedientes"><legend class="h5">1. Expediente de pago y proceso de compra</legend>
 <div class="form-group"><label for="expediente_id">Expediente de pago (abierto, que usted opera)</label><select class="form-control" id="expediente_id" name="expediente_id" required><option value="">Seleccione…</option>@foreach($abiertos as $x)<option value="{{ $x->id }}" @selected(old('expediente_id', $preexp)==$x->id)>{{ $x->codigo }} — {{ $x->solicitud?->tramite?->nombre ?? 'Comunicación recibida' }} ({{ $x->unidad?->nombre }})</option>@endforeach</select></div>
 <div class="form-group"><label for="compra_codigo">Código del expediente del proceso de compra (opcional)</label><input class="form-control" id="compra_codigo" name="compra_codigo" maxlength="30" value="{{ old('compra_codigo') }}" placeholder="EXP-2026-000123"><small class="form-text text-muted">Se asocia al expediente de pago para consultar el proceso de compra. Solo expedientes que usted puede ver.</small></div></fieldset>
<fieldset data-paso="Proveedor y monto"><legend class="h5">2. Proveedor y monto</legend>
 <div class="form-row"><div class="form-group col-md-8"><label for="proveedor_nombre">Proveedor</label><input class="form-control" id="proveedor_nombre" name="proveedor_nombre" required maxlength="160" value="{{ old('proveedor_nombre') }}"></div>
 <div class="form-group col-md-4"><label for="proveedor_run">RUT del proveedor (opcional)</label><input class="form-control" id="proveedor_run" name="proveedor_run" maxlength="14" value="{{ old('proveedor_run') }}" placeholder="76.123.456-7"></div></div>
 <div class="form-group"><label for="concepto">Concepto del pago</label><textarea class="form-control" id="concepto" name="concepto" rows="3" required maxlength="500">{{ old('concepto') }}</textarea></div>
 <div class="form-row"><div class="form-group col-md-4"><label for="monto">Monto en pesos (entero)</label><input class="form-control" id="monto" name="monto" inputmode="numeric" pattern="[0-9]+" required maxlength="14" value="{{ old('monto') }}"></div>
 <div class="form-group col-md-4"><label for="fecha">Fecha del pago (opcional)</label><input class="form-control" type="date" id="fecha" name="fecha" value="{{ old('fecha') }}"></div></div></fieldset>
<fieldset data-paso="Respaldos"><legend class="h5">3. Respaldos e imputación</legend>
 <div class="form-row"><div class="form-group col-md-4"><label for="orden_compra">Orden de compra (opcional)</label><input class="form-control" id="orden_compra" name="orden_compra" maxlength="60" value="{{ old('orden_compra') }}"></div>
 <div class="form-group col-md-4"><label for="factura">Factura o boleta (opcional)</label><input class="form-control" id="factura" name="factura" maxlength="60" value="{{ old('factura') }}"></div>
 <div class="form-group col-md-4"><label for="imputacion">Imputación presupuestaria (opcional)</label><input class="form-control" id="imputacion" name="imputacion" maxlength="160" value="{{ old('imputacion') }}"></div></div></fieldset>
<button class="btn btn-primary">Crear decreto de pago</button></form>
@endsection
