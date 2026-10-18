@extends('layouts.app')
@section('titulo', $doc?->numeroFormateado() ?? 'Egreso (en firma)')
@section('contenido')
<p><a href="{{ route('app.pagos.ver', $d) }}">← {{ $d->folio() ?? 'Decreto de pago #'.$d->id }}</a></p>
<h1>{{ $doc?->numeroFormateado() ?? 'Egreso (en firma)' }}</h1>
<p><span class="estado estado-{{ $e->fase()==='anulado' ? 'fallida' : ($e->fase()==='emitido' ? 'vigente' : 'pendiente') }}">{{ str_replace('_',' ',$e->fase()) }}</span>@if($e->estado==='anulado') · {{ $e->anulado_motivo }}@endif</p>
<dl class="row">
 <dt class="col-sm-3">Fecha</dt><dd class="col-sm-9">{{ $e->fecha->format('d-m-Y') }}</dd>
 <dt class="col-sm-3">Medio de pago</dt><dd class="col-sm-9">{{ $medios[$e->medio_pago] }}@if($e->referencia) · {{ $e->referencia }}@endif @if($e->cuenta) · {{ $e->cuenta }}@endif</dd>
 <dt class="col-sm-3">Monto</dt><dd class="col-sm-9">${{ number_format($e->monto, 0, ',', '.') }} <small>({{ \App\Support\MontoEnLetras::pesos($e->monto) }})</small></dd>
 <dt class="col-sm-3">Detalle</dt><dd class="col-sm-9">{!! nl2br(e($e->detalle)) !!}</dd>
 <dt class="col-sm-3">Proveedor</dt><dd class="col-sm-9">{{ $d->proveedor_nombre }} · {{ $d->folio() ?? 'decreto #'.$d->id }} · expediente {{ $d->expediente->codigo }}</dd>
 <dt class="col-sm-3">Generado por</dt><dd class="col-sm-9">{{ $e->creador?->nombreCompleto() }}</dd>
</dl>
@if($doc)<p><a class="btn btn-outline-primary" href="{{ route('app.pagos.egreso.pdf', $e) }}" target="_blank" rel="noopener">Ver PDF del egreso</a></p>
<h2 class="h5">Firmas</h2><ol>@foreach($doc->pasos as $p)<li>{{ $p->tipo==='firma' ? 'Firma' : 'Visación' }}: {{ $p->usuario->nombreCompleto() }} — {{ $p->estado }}@if($p->firma_tipo) · firma {{ $p->firma_tipo }}@else · permitida: {{ $p->firma_permitida }}@endif</li>@endforeach</ol>@endif
@if($miTurno && $e->estado==='abierto')@include('partials.firmar_form', ['accion' => route('app.pagos.egreso.firmar', $e), 'turno' => $turno, 'pre' => 'eg'])@endif
@if($puedeE && $e->estado==='abierto' && $e->fase()==='en_firma')<form method="post" action="{{ route('app.pagos.egreso.anular', $e) }}" class="form-inline">@csrf<label class="sr-only" for="an">Motivo de la anulación</label><input class="form-control mr-2" id="an" name="motivo" required maxlength="300" placeholder="Motivo para anular el egreso"><button class="btn btn-outline-secondary">Anular egreso</button></form>@endif
@endsection
