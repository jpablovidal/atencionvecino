@php $u = auth()->user(); $tabs = [
  ['app.transparencia', 'Consultas', 'transparencia.ver', 'app/transparencia'],
  ['app.transparencia.requerimientos', 'Requerimientos', 'transparencia.ver,transparencia.responder', 'app/transparencia/requerimientos*'],
  ['app.transparencia.activa', 'Transparencia activa', 'transparencia.ver', 'app/transparencia/activa*'],
  ['app.transparencia.plazos', 'Plazos y feriados', 'transparencia.ver', 'app/transparencia/plazos*'],
  ['app.transparencia.reportes', 'Reportes', 'transparencia.reportes', 'app/transparencia/reportes*'],
]; @endphp
<nav aria-label="Secciones de transparencia" class="mb-3"><ul class="nav nav-pills">
@foreach($tabs as [$ruta, $txt, $perm, $patron])
@if(collect(explode(',', $perm))->contains(fn ($p) => $u->tienePermiso($p)))
<li class="nav-item"><a class="nav-link {{ request()->is($patron) ? 'active' : '' }}" @if(request()->is($patron)) aria-current="page" @endif href="{{ route($ruta) }}">{{ $txt }}</a></li>
@endif
@endforeach
</ul></nav>
