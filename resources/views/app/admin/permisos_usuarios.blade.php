@extends('layouts.app')
@section('titulo', 'Matriz de permisos por usuario')
@section('contenido')
<h1>Matriz de permisos por usuario</h1>
<p class="lead-sub">Cada persona tiene un <strong>rol principal</strong> y puede tener <strong>roles adicionales</strong> y permisos concedidos o denegados individualmente. Elija una persona para ver su detalle y modificarlo. <a href="{{ route('app.admin.permisos.csv') }}" class="btn btn-sm btn-outline-primary ml-2">Descargar la matriz completa (CSV)</a></p>
<form method="get" class="form-inline mb-3" aria-label="Filtrar usuarios">
 <label class="mr-2" for="fq">Persona o cargo</label><input class="form-control mr-3" id="fq" name="q" value="{{ $f['q'] ?? '' }}" maxlength="80">
 <label class="mr-2" for="fr">Rol</label><select class="form-control mr-3" id="fr" name="rol"><option value="">Todos</option>@foreach($roles as $r)<option value="{{ $r->clave }}" @selected(($f['rol'] ?? '')===$r->clave)>{{ $r->nombre }}</option>@endforeach</select>
 <label class="mr-2" for="fe">Estado</label><select class="form-control mr-3" id="fe" name="estado"><option value="">Todos</option><option value="activos" @selected(($f['estado'] ?? '')==='activos')>Activos</option><option value="inactivos" @selected(($f['estado'] ?? '')==='inactivos')>Inactivos</option></select>
 <button class="btn btn-primary">Filtrar</button></form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Roles y permisos por usuario</caption>
<thead><tr><th scope="col">Usuario</th><th scope="col">Rol principal</th><th scope="col">Roles adicionales</th><th scope="col">Permisos efectivos</th><th scope="col">Ajustes individuales</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead><tbody>
@forelse($filas as ['u' => $u, 'r' => $r])<tr>
 <td>{{ $u->nombreCompleto() }}<br><span class="small text-muted">{{ $u->run }}@if($u->cargo) · {{ $u->cargo }}@endif @unless($u->activo) · <em>inactivo</em>@endunless</span></td>
 <td>{{ $nombres[$u->rol] ?? $u->rol }}@if($u->esSuperadmin()) <span class="badge badge-dark">superadmin</span>@endif</td>
 <td>@forelse($r['adicionales'] as $c)<span class="badge badge-secondary">{{ $nombres[$c] ?? $c }}</span> @empty<span class="text-muted">—</span>@endforelse</td>
 <td>{{ $r['efectivos'] }} de {{ $total }}</td>
 <td>@if($r['concedidos'] || $r['denegados'])<span class="small">+{{ $r['concedidos'] }} concedidos · −{{ $r['denegados'] }} denegados</span>@else<span class="text-muted">—</span>@endif</td>
 <td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.permisos.usuario', $u) }}">Ver detalle<span class="sr-only"> de {{ $u->nombreCompleto() }}</span></a></td>
</tr>@empty<tr><td colspan="6" class="text-muted">No hay usuarios con este criterio.</td></tr>@endforelse
</tbody></table></div>
{{ $pag->links() }}
@endsection
