@extends('layouts.app')
@section('titulo', 'APIs e integraciones')
@section('contenido')
<h1>APIs e integraciones</h1>
<p>Cada integración toma su configuración del archivo <code>.env</code> o, si usted la activa aquí, de la base de datos (prevalece la de BD). Los <strong>secretos</strong> nunca se muestran
@if($secretosBd) y se guardan cifrados en la BD (<code>SGD_API_SECRETOS_EN_BD=1</code>)@else; el almacenamiento de secretos en BD está <strong>desactivado</strong>: defínalos en <code>.env</code>@endif.</p>
<table class="table table-sm"><caption class="sr-only">Integraciones</caption><thead><tr><th scope="col">Integración</th><th scope="col">Origen</th><th scope="col">Modo</th><th scope="col">Campos sin definir</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($filas as $f)<tr><td><strong>{{ $f['etiqueta'] }}</strong><br><small class="text-muted">{{ $f['descripcion'] }}</small></td><td>{{ $f['origen'] }}</td><td>{{ is_bool($f['modo']) ? ($f['modo'] ? 'sí' : 'no') : $f['modo'] }}</td><td>{{ $f['faltan'] }} de {{ $f['total'] }}</td>
<td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.api.editar',$f['clave']) }}">Configurar</a></td></tr>@endforeach</tbody></table>
@endsection
