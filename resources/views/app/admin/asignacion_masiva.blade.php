@extends('layouts.app')
@section('titulo', 'Asignación masiva de personas')
@section('contenido')
<h1>Asignación masiva de personas</h1>
<p class="lead-sub">Estas son <strong>todas las personas</strong> registradas en el sistema, incluidas las cargadas desde el directorio. Márquelas, elija qué desea cambiar y aplique: dirección, rol principal, roles adicionales, permisos (las pantallas del menú de cada funcionario(a) siguen a sus permisos) o estado. Cada persona se valida por separado con las reglas de seguridad; las que no se pueden modificar se omiten y se indica por qué.</p>
@if(session('omitidos'))
<div class="alert alert-warning" role="status"><strong>Personas omitidas</strong><ul class="mb-0">@foreach(session('omitidos') as $o)<li>{{ $o['nombre'] }}: {{ $o['motivo'] }}</li>@endforeach</ul></div>
@endif
<form method="get" class="masiva-filtros" aria-label="Filtrar personas">
 <input type="hidden" name="por" value="{{ $f['por'] ?? 50 }}">
 <div class="form-row">
  <div class="form-group col-md-3"><label for="fq">Persona, RUN, correo o cargo</label><input class="form-control" id="fq" name="q" value="{{ $f['q'] ?? '' }}" maxlength="80"></div>
  <div class="form-group col-md-2"><label for="fu">Dirección</label><select class="form-control" id="fu" name="unidad"><option value="">Todas</option><option value="sin" @selected(($f['unidad'] ?? '')==='sin')>Sin dirección</option>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected((string)($f['unidad'] ?? '')===(string)$un->id)>{{ $un->nombre }}</option>@endforeach</select></div>
  <div class="form-group col-md-2"><label for="fr">Rol</label><select class="form-control" id="fr" name="rol"><option value="">Todos</option>@foreach($roles as $r)<option value="{{ $r->clave }}" @selected(($f['rol'] ?? '')===$r->clave)>{{ $r->nombre }}</option>@endforeach</select></div>
  <div class="form-group col-md-2"><label for="fe">Estado</label><select class="form-control" id="fe" name="estado"><option value="">Todos</option><option value="activos" @selected(($f['estado'] ?? '')==='activos')>Activos</option><option value="inactivos" @selected(($f['estado'] ?? '')==='inactivos')>Inactivos</option></select></div>
  <div class="form-group col-md-2"><label for="fo">Origen</label><select class="form-control" id="fo" name="origen"><option value="">Todos</option><option value="directorio" @selected(($f['origen'] ?? '')==='directorio')>Directorio</option><option value="manual" @selected(($f['origen'] ?? '')==='manual')>Creados a mano</option></select></div>
  <div class="form-group col-md-1 d-flex align-items-end"><button class="btn btn-primary btn-block">Filtrar</button></div>
 </div>
</form>

<form method="post" action="{{ route('app.admin.asignacion.aplicar') }}" id="masiva" data-masiva data-max="{{ $maximo }}">@csrf
 @foreach(['q','rol','unidad','estado','origen'] as $k)@if(! empty($f[$k]))<input type="hidden" name="{{ $k }}" value="{{ $f[$k] }}">@endif @endforeach
 <section class="card mb-3 masiva-panel" aria-labelledby="mp-t"><div class="card-body">
  <h2 class="h5" id="mp-t">Qué desea cambiar</h2>
  <p class="mb-2" aria-live="polite"><span class="badge badge-primary" data-masiva-cuenta>0 marcadas</span> <span class="small text-muted">de {{ $coincidencias }} que coinciden con el filtro (máximo {{ $maximo }} por vez).</span></p>
  <p class="mb-3"><button type="button" class="btn btn-sm btn-outline-primary" data-masiva-marcar="pagina">Marcar las {{ $pag->count() }} de esta página</button>
   @if($coincidencias > $pag->count())<button type="button" class="btn btn-sm btn-outline-primary" data-masiva-marcar="filtro">Marcar las {{ min($coincidencias, $maximo) }} que coinciden con el filtro</button>@endif
   <button type="button" class="btn btn-sm btn-outline-secondary" data-masiva-marcar="ninguna">Desmarcar todo</button></p>
  <div class="form-row">
   <div class="form-group col-md-4"><label for="accion">Acción</label>
    <select class="form-control" id="accion" name="accion" data-masiva-accion required>
     <option value="">Elija una acción…</option>
     <option value="unidad">Asignar a una dirección municipal</option>
     <option value="rol">Cambiar el rol principal</option>
     <option value="roles_agregar">Agregar roles adicionales</option>
     <option value="roles_quitar">Quitar roles adicionales</option>
     <option value="permisos">Ajustar funcionalidades (permisos)</option>
     <option value="estado">Activar o desactivar</option>
    </select></div>
   <div class="form-group col-md-8"><span class="d-block mb-1" id="alc-l">Aplicar a</span>
    <div class="custom-control custom-radio custom-control-inline"><input type="radio" class="custom-control-input" id="alc-m" name="alcance" value="marcados" checked><label class="custom-control-label" for="alc-m">Las personas marcadas</label></div>
    <div class="custom-control custom-radio custom-control-inline"><input type="radio" class="custom-control-input" id="alc-f" name="alcance" value="filtro"><label class="custom-control-label" for="alc-f">Todas las que coinciden con el filtro ({{ $coincidencias }})</label></div></div>
  </div>

  <div class="masiva-opcion" data-accion-panel="unidad" hidden>
   <div class="form-row">
    <div class="form-group col-md-4"><label for="m-un">Dirección</label><select class="form-control" id="m-un" name="unidad_id" data-masiva-unidad><option value="">(sin dirección)</option>@foreach($unidades as $un)<option value="{{ $un->id }}">{{ $un->nombre }}</option>@endforeach</select></div>
    <div class="form-group col-md-4"><label for="m-dep">Departamento (opcional)</label><select class="form-control" id="m-dep" name="departamento_id" data-masiva-hijo="departamento"><option value="">—</option>@foreach($internas->where('tipo','departamento') as $i)<option value="{{ $i->id }}" data-unidad="{{ $i->unidad_id }}" hidden>{{ $i->nombre }}</option>@endforeach</select></div>
    <div class="form-group col-md-4"><label for="m-sub">Unidad (opcional)</label><select class="form-control" id="m-sub" name="subunidad_id" data-masiva-hijo="unidad"><option value="">—</option>@foreach($internas->where('tipo','unidad') as $i)<option value="{{ $i->id }}" data-unidad="{{ $i->unidad_id }}" hidden>{{ $i->nombre }}</option>@endforeach</select></div>
   </div>
   <p class="small text-muted mb-0">Si deja departamento y unidad en blanco, se limpian los que tuviera cada persona (pertenecen a la dirección anterior).</p>
  </div>

  <div class="masiva-opcion" data-accion-panel="rol" hidden>
   <div class="form-group col-md-5 px-0"><label for="m-rol">Nuevo rol principal</label><select class="form-control" id="m-rol" name="rol_principal"><option value="">Elija…</option>@foreach($roles as $r)<option value="{{ $r->clave }}">{{ $r->nombre }}</option>@endforeach</select></div>
   <p class="small text-muted mb-0">Cambiar el rol cierra las sesiones de cada persona. No se aplica a usted, a superadministradores(as), ni al último administrador activo.</p>
  </div>

  @foreach(['roles_agregar' => 'Roles que se agregarán', 'roles_quitar' => 'Roles que se quitarán'] as $acc => $tit)
  <div class="masiva-opcion" data-accion-panel="{{ $acc }}" hidden>
   <fieldset><legend class="h6">{{ $tit }}</legend>
   <div class="row">@foreach($roles->whereNotIn('rol_base', \App\Services\Admin\PermisosUsuarioService::BASES_AISLADAS) as $r)<div class="col-md-4"><div class="custom-control custom-checkbox mb-1"><input class="custom-control-input" type="checkbox" id="{{ $acc }}-{{ $r->id }}" name="roles[]" value="{{ $r->clave }}" disabled><label class="custom-control-label" for="{{ $acc }}-{{ $r->id }}">{{ $r->nombre }}</label></div></div>@endforeach</div></fieldset>
   <p class="small text-muted mb-0">Los roles de administración y auditoría no se combinan con otros (separación de funciones).</p>
  </div>
  @endforeach

  <div class="masiva-opcion" data-accion-panel="permisos" hidden>
   <p class="small text-muted">Para cada funcionalidad elija «Conceder», «Denegar» o «Quitar el ajuste». Lo no marcado no se toca. Puede conceder <strong>cualquier</strong> funcionalidad a cada persona, aunque su rol no la incluya (se registra «fuera de rol» en la bitácora); solo las reservadas al sistema no se pueden conceder.</p>
   <div class="form-group col-md-4 px-0"><label for="m-pbus">Buscar funcionalidad</label><input class="form-control" id="m-pbus" type="search" data-masiva-buscar autocomplete="off" placeholder="Escriba para filtrar…"></div>
   @foreach($grupos as $g => $perms)
   <details class="masiva-grupo" open><summary>{{ $g }}</summary>
    @foreach($perms as $clave => $et)
    <div class="masiva-perm" data-perm-texto="{{ \Illuminate\Support\Str::lower($et.' '.$clave) }}"><span class="masiva-perm-et">{{ $et }}</span>
     <span class="masiva-perm-op" role="radiogroup" aria-label="{{ $et }}">
      <label><input type="radio" name="perm[{{ $clave }}]" value="" checked disabled> <span>Sin cambio</span></label>
      <label><input type="radio" name="perm[{{ $clave }}]" value="conceder" disabled> <span>Conceder</span></label>
      <label><input type="radio" name="perm[{{ $clave }}]" value="denegar" disabled> <span>Denegar</span></label>
      <label><input type="radio" name="perm[{{ $clave }}]" value="limpiar" disabled> <span>Quitar el ajuste</span></label></span></div>
    @endforeach
   </details>
   @endforeach
  </div>

  <div class="masiva-opcion" data-accion-panel="estado" hidden>
   <div class="form-group col-md-4 px-0"><label for="m-act">Estado</label><select class="form-control" id="m-act" name="activo"><option value="1">Activar</option><option value="0">Desactivar (cierra sus sesiones)</option></select></div>
  </div>

  <button class="btn btn-primary mt-2" data-masiva-aplicar disabled>Aplicar a la selección</button>
 </div></section>

 <div class="table-responsive"><table class="table table-sm masiva-tabla"><caption class="sr-only">Personas del sistema</caption>
 <thead><tr><th scope="col"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="m-todos" data-masiva-todos><label class="custom-control-label" for="m-todos"><span class="sr-only">Marcar todas las de esta página</span></label></div></th><th scope="col">Persona</th><th scope="col">Dirección</th><th scope="col">Rol principal</th><th scope="col">Otros roles</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Detalle</span></th></tr></thead><tbody>
 @forelse($pag as $u)
 <tr><td><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="u-{{ $u->id }}" name="ids[]" value="{{ $u->id }}" data-masiva-fila><label class="custom-control-label" for="u-{{ $u->id }}"><span class="sr-only">Marcar a {{ $u->nombreCompleto() }}</span></label></div></td>
  <td>{{ $u->nombreCompleto() }}<br><span class="small text-muted">{{ $u->run }}@if($u->cargo) · {{ $u->cargo }}@endif @if($u->directorio) · <span class="badge badge-light">directorio</span>@endif @if($u->esSuperadmin()) <span class="badge badge-dark">superadmin</span>@endif</span></td>
  <td>{{ $u->unidad?->nombre ?? '—' }}</td><td>{{ $nombres[$u->rol] ?? $u->rol }}</td>
  <td>@forelse($u->rolesAdicionales() as $c)<span class="badge badge-secondary">{{ $nombres[$c] ?? $c }}</span> @empty<span class="text-muted">—</span>@endforelse</td>
  <td>{!! $u->activo ? '<span class="estado">activo</span>' : '<em>inactivo</em>' !!}</td>
  <td><a class="btn btn-sm btn-outline-primary" href="{{ route('app.admin.permisos.usuario', $u) }}">Detalle<span class="sr-only"> de {{ $u->nombreCompleto() }}</span></a></td></tr>
 @empty<tr><td colspan="7" class="text-muted">No hay personas con este criterio.</td></tr>@endforelse
 </tbody></table></div>
</form>
<form method="get" class="form-inline mb-2" aria-label="Personas por página">@foreach(['q','rol','unidad','estado','origen'] as $k)@if(! empty($f[$k]))<input type="hidden" name="{{ $k }}" value="{{ $f[$k] }}">@endif @endforeach
 <label class="mr-2 small" for="por">Personas por página</label><select class="form-control form-control-sm mr-2" id="por" name="por">@foreach([25,50,100,200] as $n)<option value="{{ $n }}" @selected((int)($f['por'] ?? 50)===$n)>{{ $n }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Mostrar</button></form>
{{ $pag->links() }}
@push('scripts')<script src="{{ asset('assets/js/asignacion_masiva.js') }}"></script>@endpush
@endsection
