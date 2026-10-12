@extends('layouts.app')
@section('titulo', 'Usuarios conectados')
@section('contenido')
<h1>Usuarios conectados</h1>
<p class="text-muted">Sesiones con actividad en los últimos {{ $minutos }} minutos. Los RUN de ciudadanos se muestran enmascarados.</p>
<p role="status">{{ $filas->count() }} {{ $filas->count() === 1 ? 'sesión activa' : 'sesiones activas' }}.</p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Sesiones activas</caption><thead><tr><th scope="col">Tipo</th><th scope="col">Usuario</th><th scope="col">Rol</th><th scope="col">IP</th><th scope="col">Navegador</th><th scope="col">Inicio</th><th scope="col">Última actividad</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($filas as $s)<tr><td>{{ $s['tipo'] }}</td><td>{{ $s['nombre'] }}@if($s['propia']) <span class="badge badge-info">su sesión</span>@endif</td><td>{{ $s['rol'] ?? '—' }}</td><td>{{ $s['ip'] }}</td><td><small>{{ $s['agente'] }}</small></td><td>{{ $s['inicio'] }}</td><td>{{ $s['actividad'] }}</td>
<td>@if($puedeCerrar && ! $s['propia'])<form method="post" action="{{ route('app.sesiones.cerrar', $s['id']) }}" data-confirm="¿Cerrar la sesión de {{ $s['nombre'] }}?">@csrf<button class="btn btn-sm btn-outline-danger">Cerrar sesión<span class="sr-only"> de {{ $s['nombre'] }} (ref. {{ $s['ref'] }})</span></button></form>@endif</td></tr>
@empty<tr><td colspan="8">No hay sesiones activas.</td></tr>@endforelse</tbody></table></div>
@endsection
