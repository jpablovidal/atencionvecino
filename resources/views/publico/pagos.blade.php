@extends('layouts.app')
@section('titulo', 'Pago a proveedores')
@section('contenido')
<h1>Consulta de pago a proveedores</h1>
<p class="lead-sub">Conozca la situación de pago de su factura en la I. Municipalidad de Santo Domingo. Ingrese el <strong>RUT de su empresa</strong>, el <strong>número</strong> y la <strong>fecha</strong> de la factura: los tres datos deben coincidir.</p>
<form method="post" action="{{ route('proveedores.pagos.consultar') }}" class="card card-body mb-4" novalidate>@csrf
 <div class="form-row">
  <div class="form-group col-md-4"><label for="rut">RUT de la empresa</label><input class="form-control" id="rut" name="rut" required maxlength="14" autocomplete="off" inputmode="text" placeholder="76.123.456-7" value="{{ $entrada['rut'] }}" aria-describedby="rut-ayuda"><small id="rut-ayuda" class="form-text text-muted">Con dígito verificador.</small></div>
  <div class="form-group col-md-4"><label for="factura">Número de factura</label><input class="form-control" id="factura" name="factura" required maxlength="40" autocomplete="off" value="{{ $entrada['factura'] }}"></div>
  <div class="form-group col-md-4"><label for="fecha">Fecha de la factura</label><input class="form-control" type="date" id="fecha" name="fecha" required max="{{ now('America/Santiago')->format('Y-m-d') }}" value="{{ $entrada['fecha'] }}"></div></div>
 <div><button class="btn btn-primary">Consultar</button></div></form>
@if($error)<div class="alert alert-danger" role="alert" tabindex="-1" id="resultado-pago">{{ $error }}</div>@endif
@if($resultado !== null)
 <section aria-labelledby="res-t" id="resultado-pago" tabindex="-1"><h2 class="h4" id="res-t">Resultado</h2>
 @forelse($resultado as $x)
  <div class="card mb-3"><div class="card-body">
   <p class="mb-1"><span class="estado estado-{{ $x['clave']==='pagado' ? 'vigente' : ($x['clave']==='anulado' ? 'fallida' : 'pendiente') }}">{{ $x['estado'] }}</span></p>
   <p class="lead mb-2" role="status">{{ $x['mensaje'] }}</p>
   <dl class="row mb-0">
    <dt class="col-sm-4">Proveedor</dt><dd class="col-sm-8">{{ $x['proveedor'] }}</dd>
    <dt class="col-sm-4">Factura</dt><dd class="col-sm-8">{{ $x['factura'] }} del {{ $x['factura_fecha'] }}@if($x['dias'] !== null && $x['clave'] !== 'pagado' && $x['clave'] !== 'anulado') · han pasado {{ $x['dias'] }} días @endif</dd>
    @if($x['decreto'])<dt class="col-sm-4">Decreto de pago</dt><dd class="col-sm-8">{{ $x['decreto'] }}</dd>@endif
    <dt class="col-sm-4">Monto del decreto</dt><dd class="col-sm-8">${{ number_format($x['monto'], 0, ',', '.') }}</dd>
    @if($x['pagado'] > 0)<dt class="col-sm-4">Monto pagado</dt><dd class="col-sm-8">${{ number_format($x['pagado'], 0, ',', '.') }}</dd>@endif
   </dl>
   @if($x['pagos'])<h3 class="h6 mt-3">Pagos realizados</h3>
   <div class="table-responsive"><table class="table table-sm mb-0"><caption class="sr-only">Pagos de la factura {{ $x['factura'] }}</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Medio de pago</th><th scope="col" class="text-right">Monto</th><th scope="col">N° de transferencia o cheque</th><th scope="col">Egreso</th></tr></thead><tbody>
   @foreach($x['pagos'] as $p)<tr><td>{{ $p['fecha'] }}</td><td>{{ $p['medio'] }}</td><td class="text-right">${{ number_format($p['monto'], 0, ',', '.') }}</td><td>{{ $p['referencia'] ?: '—' }}</td><td>{{ $p['egreso'] ?: '—' }}</td></tr>@endforeach</tbody></table></div>@endif
  </div></div>
 @empty
  <div class="alert alert-info" role="status">No encontramos información de pago con esos datos. Revise el RUT, el número y la fecha de la factura. Si los datos son correctos, es posible que la factura aún no haya sido ingresada al proceso de pago: consulte en la Dirección de Administración y Finanzas.</div>
 @endforelse
 </section>@endif
<p class="small text-muted">Esta información es referencial y refleja el estado del proceso de pago en el sistema municipal. Por seguridad, la consulta exige los tres datos de la factura y no muestra datos bancarios.</p>
@endsection
