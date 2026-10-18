@extends('layouts.app')
@section('titulo', $d->folio() ?? 'Decreto de pago (borrador)')
@section('contenido')
<p><a href="{{ route('app.pagos') }}">← Decretos de pago</a></p>
<h1>{{ $d->folio() ?? 'Decreto de pago (borrador)' }}</h1>
<p><span class="estado estado-{{ $fase === 'anulado' ? 'fallida' : ($fase === 'borrador' ? 'pendiente' : 'vigente') }}">{{ $fases[$fase] }}</span>@if($d->estado==='anulado') · {{ $d->anulado_motivo }}@endif</p>
<dl class="row">
 <dt class="col-sm-3">Proveedor</dt><dd class="col-sm-9">{{ $d->proveedor_nombre }}@if($d->proveedor_run) · RUT {{ $d->proveedor_run }}@endif</dd>
 <dt class="col-sm-3">Concepto</dt><dd class="col-sm-9">{{ $d->concepto }}</dd>
 <dt class="col-sm-3">Monto</dt><dd class="col-sm-9">${{ number_format($d->monto, 0, ',', '.') }} <small>({{ \App\Support\MontoEnLetras::pesos($d->monto) }})</small> · egresado ${{ number_format($d->egresado(), 0, ',', '.') }} · saldo por egresar ${{ number_format(max(0, $saldo), 0, ',', '.') }}</dd>
 <dt class="col-sm-3">Respaldos</dt><dd class="col-sm-9">OC {{ $d->orden_compra ?: '—' }} · Factura {{ $d->factura ?: '—' }} · Imputación {{ $d->imputacion ?: '—' }}@if($d->fecha) · Fecha de pago {{ $d->fecha->format('d-m-Y') }}@endif</dd>
 <dt class="col-sm-3">Expediente de pago</dt><dd class="col-sm-9">{{ $d->expediente->codigo }} · {{ $d->expediente->unidad?->nombre }} @if($indicePago !== null)· <a href="{{ route('app.expediente', $d->expediente) }}">abrir</a>@endif</dd>
 <dt class="col-sm-3">Proceso de compra</dt><dd class="col-sm-9">@if($d->compra){{ $d->compra->codigo }}@if($indiceCompra !== null) · <a href="{{ route('app.expediente', $d->compra) }}">abrir</a>@endif @else No asociado @endif</dd>
</dl>

<h2 class="h4">Contenido de los expedientes</h2>
<div class="row">
@foreach([['Expediente de pago', $d->expediente->codigo, $indicePago], ['Proceso de compra', $d->compra?->codigo, $indiceCompra]] as [$tt, $cod, $ind])
<div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-body"><h3 class="h6">{{ $tt }} {{ $cod }}</h3>
 @if($ind === null)<p class="mb-0 small">{{ $cod ? 'No tiene acceso a este expediente (solo ve los datos del decreto).' : 'No asociado.' }}</p>
 @elseif(count($ind)===0)<p class="mb-0 small">Sin documentos ni archivos.</p>
 @else<ul class="small mb-0">@foreach(array_slice($ind, 0, 25) as $i)<li>{{ $i['tipo'] }}: @if(! empty($i['restringido']))<em>{{ $i['titulo'] }}</em>@else{{ $i['titulo'] }} {{ $i['numero'] ?? '' }}@endif</li>@endforeach @if(count($ind) > 25)<li>… y {{ count($ind) - 25 }} más.</li>@endif</ul>@endif</div></div></div>
@endforeach</div>

<h2 class="h4" id="decreto">PDF del decreto de pago</h2>
@if($d->documento || $d->archivo)
 <p>@if($d->origen==='cargado')PDF <strong>cargado</strong> (N° {{ $d->numero_externo }}@if($d->fecha), {{ $d->fecha->format('d-m-Y') }}@endif).@else Decreto <strong>generado</strong> con plantilla: documento «{{ $d->documento->titulo }}» ({{ $d->documento->estado }}).@endif
  <a class="btn btn-sm btn-outline-primary ml-2" href="{{ route('app.pagos.pdf', $d) }}" target="_blank" rel="noopener">Ver PDF</a>@if($d->documento && $puedeG)<a class="btn btn-sm btn-outline-primary ml-1" href="{{ route('app.documento', $d->documento) }}">Abrir documento{{ $d->documento->estado==='borrador' ? ' (editar)' : '' }}</a>@endif</p>
 @if($d->documento)<ol class="small">@foreach($d->documento->pasos as $p)<li>{{ $p->tipo==='firma' ? 'Firma' : 'Visación' }}: {{ $p->usuario->nombreCompleto() }} — {{ $p->estado }}@if($p->firma_tipo) ({{ $p->firma_tipo }})@endif</li>@endforeach</ol>@endif
 @if($puedeG && $d->documento?->estado==='borrador')
  <form method="post" action="{{ route('app.pagos.enviar_firma', $d) }}" class="mb-3">@csrf
  @if($flujoErr)<div class="alert alert-warning" role="status">{{ $flujoErr }} @if(auth()->user()->tienePermiso('decretopago.configurar'))<a href="{{ route('app.pagos.config') }}">Configurar firmantes</a>@endif</div>@else
  <p class="small">Se enviará a: @foreach($firmantes as $f){{ $f->orden }}. {{ $f->funcion==='firma' ? 'firma' : 'visa' }} {{ $f->usuario->nombreCompleto() }} ({{ $f->cargo }})@unless($loop->last); @endunless @endforeach.</p>@endif
  <button class="btn btn-primary" @disabled($flujoErr)>Enviar a visación y firma</button></form>@endif
@elseif($puedeG)
 <div class="row"><div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-body"><h3 class="h6">Generar con plantilla</h3>
  <form method="post" action="{{ route('app.pagos.generar', $d) }}">@csrf
  <div class="form-group"><label for="plantilla_id">Plantilla del decreto de pago</label><select class="form-control" id="plantilla_id" name="plantilla_id" required><option value="">Seleccione…</option>@foreach($plantillas as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach</select></div>
  @if($plantillas->isEmpty())<p class="small text-muted">No hay plantillas del tipo «Decreto de pago». Cree una en <a href="{{ route('app.admin.plantillas') }}">Plantillas</a> o ejecute <code>php artisan plantillas:ejemplos</code>.</p>@endif
  <button class="btn btn-primary" @disabled($plantillas->isEmpty())>Generar decreto</button></form></div></div></div>
 <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-body"><h3 class="h6">Cargar el PDF ya emitido</h3>
  <form method="post" action="{{ route('app.pagos.cargar', $d) }}" enctype="multipart/form-data">@csrf
  <div class="form-group"><label for="archivo">PDF del decreto (hasta 10 MB)</label><input class="form-control-file" type="file" id="archivo" name="archivo" accept="application/pdf,.pdf" required></div>
  <div class="form-row"><div class="form-group col-md-6"><label for="numero">N° del decreto</label><input class="form-control" id="numero" name="numero" required maxlength="40"></div><div class="form-group col-md-6"><label for="fecha_decreto">Fecha del decreto</label><input class="form-control" type="date" id="fecha_decreto" name="fecha_decreto" required></div></div>
  <button class="btn btn-outline-primary">Cargar decreto</button></form></div></div></div></div>
@else<p>Aún no hay PDF del decreto.</p>@endif

<h2 class="h4" id="egresos">Egresos de Tesorería</h2>
@if($d->egresos->isEmpty())<p>Sin egresos.</p>@else
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Egresos del decreto</caption><thead><tr><th scope="col">N°</th><th scope="col">Fecha</th><th scope="col">Medio</th><th scope="col" class="text-right">Monto</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Abrir</span></th></tr></thead><tbody>
@foreach($d->egresos as $e)<tr><td>{{ $e->documento?->numeroFormateado() ?? '#'.$e->id }}</td><td>{{ $e->fecha->format('d-m-Y') }}</td><td>{{ $medios[$e->medio_pago] }}</td><td class="text-right">${{ number_format($e->monto, 0, ',', '.') }}</td><td>{{ str_replace('_',' ',$e->fase()) }}</td><td><a href="{{ route('app.pagos.egreso', $e) }}">Abrir<span class="sr-only"> egreso {{ $e->id }}</span></a></td></tr>@endforeach</tbody></table></div>@endif
@if($puedeE && $d->emitido() && $saldo > 0)
<form method="post" action="{{ route('app.pagos.egreso.crear', $d) }}" class="card card-body mb-3">@csrf
 <h3 class="h5">Generar egreso</h3>
 <div class="form-row"><div class="form-group col-md-3"><label for="e_fecha">Fecha</label><input class="form-control" type="date" id="e_fecha" name="fecha" required max="{{ now('America/Santiago')->format('Y-m-d') }}" value="{{ old('fecha', now('America/Santiago')->format('Y-m-d')) }}"></div>
 <div class="form-group col-md-3"><label for="e_medio">Medio de pago</label><select class="form-control" id="e_medio" name="medio_pago" required>@foreach($medios as $k=>$v)<option value="{{ $k }}" @selected(old('medio_pago')===$k)>{{ $v }}</option>@endforeach</select></div>
 <div class="form-group col-md-3"><label for="e_monto">Monto (saldo ${{ number_format($saldo, 0, ',', '.') }})</label><input class="form-control" id="e_monto" name="monto" inputmode="numeric" pattern="[0-9]+" required maxlength="14" value="{{ old('monto', $saldo) }}"></div>
 <div class="form-group col-md-3"><label for="e_ref">N° de transferencia o cheque</label><input class="form-control" id="e_ref" name="referencia" maxlength="80" value="{{ old('referencia') }}"></div></div>
 <div class="form-group"><label for="e_cta">Cuenta o banco (opcional)</label><input class="form-control" id="e_cta" name="cuenta" maxlength="120" value="{{ old('cuenta') }}"></div>
 <div class="form-group"><label for="e_det">Detalle del egreso</label><textarea class="form-control" id="e_det" name="detalle" rows="3" required maxlength="1000">{{ old('detalle') }}</textarea></div>
 <div><button class="btn btn-primary">Generar egreso y enviar a firma</button></div></form>
@endif

@if($puedeG && $d->estado==='abierto')
<form method="post" action="{{ route('app.pagos.anular', $d) }}" class="form-inline mt-4">@csrf<label class="sr-only" for="an">Motivo de la anulación</label><input class="form-control mr-2" id="an" name="motivo" required maxlength="300" placeholder="Motivo para anular el decreto"><button class="btn btn-outline-secondary">Anular decreto de pago</button></form>
@endif
@endsection
