@extends('layouts.app')
@section('titulo', 'Pruebas y producción')
@section('contenido')
<h1>Pruebas y producción</h1>
@if(! $pruebas)
<div class="alert alert-success" role="status"><strong>El sistema está en PRODUCCIÓN.</strong>
@if($registro) Desde el {{ \Illuminate\Support\Carbon::parse($registro['en'])->timezone('America/Santiago')->format('d-m-Y H:i') }}. @endif
Este cambio no se puede revertir desde el panel.</div>
@if($registro && ! empty($registro['borrado']))<h2 class="h5">Qué se borró al pasar a producción</h2>
<ul>@foreach($registro['borrado'] as $t => $n)<li><code>{{ $t }}</code>: {{ $n }}</li>@endforeach</ul>@endif
@else
<p class="lead-sub">El sistema está en <strong>ambiente de pruebas</strong>. Aquí puede cargar procesos simulados para ver cómo funciona y, cuando esté listo, pasar a producción.</p>

<section class="card mb-4" aria-labelledby="sim"><div class="card-body"><h2 class="h5" id="sim">Datos y procesos simulados</h2>
<p>Crea vecinos ficticios con solicitudes, expedientes en distintos estados, oficios con varias versiones, visación y firma, archivos con versiones, una respuesta final notificada, una solicitud devuelta y un decreto de pago con su egreso. Usa los mismos servicios que la operación real. <strong>Todo se borra al pasar a producción.</strong></p>
@if($simulada)<p class="mb-0"><span class="chip">Ya cargados</span> Revise la bandeja, los expedientes, «Decretos de pago» y «Tablero de pagos».</p>
@else<form method="post" action="{{ route('app.admin.produccion.simular') }}" data-confirm="¿Cargar datos simulados en este ambiente de pruebas?">@csrf<button class="btn btn-primary">Cargar datos simulados</button></form>@endif
@if($resultado)<ul class="mt-3 mb-0">@foreach($resultado as $l)<li>{{ $l }}</li>@endforeach</ul>@endif
</div></section>

<section class="card mb-4" aria-labelledby="req"><div class="card-body"><h2 class="h5" id="req">Requisitos para pasar a producción</h2>
<ul class="list-unstyled">@foreach($requisitos as $q)<li class="mb-2">@if($q['ok'])<span class="chip" aria-hidden="true">Listo</span><span class="sr-only">Cumplido:</span>@else<span class="chip chip-claro" aria-hidden="true">Falta</span><span class="sr-only">Pendiente:</span>@endif {{ $q['etiqueta'] }}@if(! $q['ok'] && $q['detalle'])<br><small class="text-muted">{{ $q['detalle'] }}</small>@endif</li>@endforeach</ul>
</div></section>

<section class="card mb-4 border-danger" aria-labelledby="pas"><div class="card-body"><h2 class="h5 text-danger" id="pas">Pasar a producción (irreversible)</h2>
<p>Al confirmar se <strong>borran todos los procesos y expedientes</strong> creados en pruebas, con sus archivos del repositorio cifrado; se reinician los correlativos y se desactivan los usuarios de demostración. Se conservan la configuración (usuarios reales, roles, unidades, trámites, plantillas, firmantes, feriados, apariencia) y la <strong>bitácora de auditoría</strong>.</p>
@if($conteos)<p class="mb-1">Se borrarán ahora:</p><ul>@foreach($conteos as $t => $n)<li><code>{{ $t }}</code>: {{ $n }}</li>@endforeach</ul>@else<p>No hay procesos de prueba que borrar.</p>@endif
@if($puede)
<form method="post" action="{{ route('app.admin.produccion.pasar') }}">@csrf
 <div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="resp" name="respaldo" value="1" required><label class="custom-control-label" for="resp">Respaldé la base de datos y la carpeta de archivos si quiero conservar una copia de los datos de prueba.</label></div>
 <div class="form-group"><label for="frase">Escriba exactamente: <code>{{ $frase }}</code></label><input class="form-control" id="frase" name="frase" autocomplete="off" required maxlength="100"></div>
 <div class="form-group"><label for="cod">Código actual del segundo factor</label><input class="form-control" style="max-width:12rem" id="cod" name="codigo" inputmode="numeric" autocomplete="one-time-code" required maxlength="20"></div>
 <button class="btn btn-danger">Borrar datos de prueba y pasar a producción</button></form>
@else<p class="mb-0"><strong>Complete primero los requisitos de arriba.</strong> El botón aparece cuando todos están cumplidos.</p>@endif
</div></section>
@endif
@endsection
