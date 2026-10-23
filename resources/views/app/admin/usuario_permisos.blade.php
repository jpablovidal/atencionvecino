@extends('layouts.app')
@section('titulo', 'Permisos de '.$u->nombreCompleto())
@section('contenido')
<nav aria-label="Usted está en" class="portal-migas"><span class="portal-migas-et">Usted está en:</span><ol><li><a href="{{ route('app.admin.permisos') }}">Matriz de permisos</a></li><li aria-current="page">{{ $u->nombreCompleto() }}</li></ol></nav>
<h1>Roles y permisos de {{ $u->nombreCompleto() }}</h1>
<p class="lead-sub">{{ $u->run }}@if($u->cargo) · {{ $u->cargo }}@endif · rol principal: <strong>{{ $nombres[$u->rol] ?? $u->rol }}</strong>@if($u->esSuperadmin()) · <span class="badge badge-dark">superadministrador(a)</span>@endif @unless($u->activo) · <em>cuenta inactiva</em>@endunless
 · <a href="{{ route('app.admin.usuarios.editar', $u) }}">Editar datos y rol principal</a></p>
<p>Permisos efectivos: <strong>{{ $resumen['efectivos'] }}</strong> de {{ count(\App\Support\Permisos::catalogo()) }}@if($resumen['concedidos'] || $resumen['denegados']) · {{ $resumen['concedidos'] }} concedidos y {{ $resumen['denegados'] }} denegados individualmente @endif</p>
@if(! empty($dd))<p>En DocDigital: <strong>{{ $dd->entidad_nombre ?: 'sin entidad' }}</strong> · @forelse($dd->rolesEtiquetas() as $re)<span class="estado">{{ $re }}</span> @empty sin rol de gestión @endforelse <span class="small text-muted">(informativo: los permisos de este sistema son independientes)</span></p>@endif
@if($propio)<div class="alert alert-warning" role="note">Está editando <strong>sus propios</strong> permisos: puede agregarse o quitarse permisos dentro de lo que permite su rol; el cambio queda registrado en la auditoría y rige de inmediato. Siempre debe quedar al menos una persona con «Roles y permisos».</div>@endif
@if($u->esSuperadmin())<div class="alert alert-info" role="note">Un(a) superadministrador(a) tiene todos los permisos del catálogo; los ajustes individuales se guardan pero no restringen esa condición. Para quitarla use la ficha del usuario.</div>@endif
<form method="post" action="{{ route('app.admin.permisos.guardar', $u) }}">@csrf
<section class="card mb-4"><div class="card-body">
 <h2 class="h5">Roles</h2>
 <p class="mb-2">Rol principal: <strong>{{ $nombres[$u->rol] ?? $u->rol }}</strong>, que fija el rol base ({{ $u->rolBase() }}) y sus reglas de seguridad.</p>
 @if($disponibles->isEmpty())
  <p class="small text-muted mb-0">@if(in_array($u->rolBase(), \App\Services\Admin\PermisosUsuarioService::BASES_AISLADAS, true))Los roles de administración y de auditoría no se combinan con otros roles (separación de funciones).@else No hay otros roles disponibles.@endif</p>
 @else
  <fieldset><legend class="h6">Roles adicionales</legend>
  <p class="small text-muted">Los permisos de la persona son la unión de los de todos sus roles. No se pueden agregar roles de administración ni de auditoría.</p>
  <div class="row">@foreach($disponibles as $r)<div class="col-md-6"><div class="custom-control custom-checkbox mb-1"><input class="custom-control-input" type="checkbox" id="re-{{ $r->id }}" name="rol_extra[]" value="{{ $r->clave }}" @checked(in_array($r->clave, old('rol_extra', $adicionales), true))><label class="custom-control-label" for="re-{{ $r->id }}">{{ $r->nombre }}@unless($r->sistema) <span class="small text-muted">(personalizado)</span>@endunless</label></div></div>@endforeach</div></fieldset>
 @endif
</div></section>
<section class="card mb-4"><div class="card-body">
 <h2 class="h5">Matriz de permisos</h2>
 <p class="small text-muted">«Efectivo» es lo que la persona puede hacer hoy. En «Ajuste individual» puede <strong>conceder</strong> cualquier permiso que sus roles no dan (la asignación es personalizada y <strong>no se limita al rol</strong>) o <strong>denegar</strong> uno que sí dan. Los permisos marcados «reservado» solo los tiene el rol de administración del sistema. Los cambios se aplican al guardar.</p>
 @foreach($matriz as $grupo => $filas)
 <h3 class="h6 mt-3">{{ $grupo }}</h3>
 <div class="table-responsive"><table class="table table-sm mb-2"><caption class="sr-only">Permisos de {{ $grupo }}</caption>
 <thead><tr><th scope="col">Permiso</th><th scope="col">Efectivo</th><th scope="col">Origen</th><th scope="col">Ajuste individual</th></tr></thead><tbody>
 @foreach($filas as $p)<tr>
  <td><label class="mb-0 font-weight-normal" for="p-{{ $loop->parent->index }}-{{ $loop->index }}">{{ $p['etiqueta'] }}</label><br><code class="small">{{ $p['clave'] }}</code>@if($p['reservado']) <span class="badge badge-light">reservado</span>@endif</td>
  <td>@if($p['efectivo'])<span class="estado estado-vigente">Sí</span>@else<span class="estado">No</span>@endif</td>
  <td class="small">{{ $p['origen'] }}</td>
  <td><select class="form-control form-control-sm" id="p-{{ $loop->parent->index }}-{{ $loop->index }}" name="perm[{{ $p['clave'] }}]">
   <option value="">Según sus roles</option>
   <option value="conceder" @selected(old('perm.'.$p['clave'], $p['individual']) === 'conceder') @disabled(! $p['concedible'] && $p['individual'] !== 'conceder')>Conceder</option>
   <option value="denegar" @selected(old('perm.'.$p['clave'], $p['individual']) === 'denegar')>Denegar</option></select></td>
 </tr>@endforeach</tbody></table></div>
 @endforeach
</div></section>
<button class="btn btn-primary">Guardar roles y permisos</button> <a class="btn btn-link" href="{{ route('app.admin.permisos') }}">Volver a la matriz</a>
</form>
@endsection
