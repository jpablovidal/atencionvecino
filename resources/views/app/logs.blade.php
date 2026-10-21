@extends('layouts.app')
@section('titulo', 'Registro de logs')
@section('contenido')
<h1>Registro de logs</h1>
<p class="text-muted">Bitácora de auditoría con cadena HMAC (append-only). Los RUN se muestran enmascarados. La verificación de integridad está en <a href="{{ route('app.seguridad') }}">Seguridad</a>.</p>
<form method="get" action="{{ route('app.logs') }}" class="card card-body mb-3" aria-label="Filtros del registro">
  <div class="form-row">
    <div class="form-group col-md-3"><label for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="{{ $f['desde'] }}"></div>
    <div class="form-group col-md-3"><label for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="{{ $f['hasta'] }}"></div>
    <div class="form-group col-md-3"><label for="accion">Acción (empieza con)</label><input class="form-control" id="accion" name="accion" list="acciones" maxlength="80" value="{{ $f['accion'] }}"><datalist id="acciones">@foreach($acciones as $a)<option value="{{ $a }}">@endforeach</datalist></div>
    <div class="form-group col-md-3"><label for="rol">Rol del actor</label><input class="form-control" id="rol" name="rol" maxlength="30" value="{{ $f['rol'] }}"></div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-3"><label for="run">RUN del actor (exacto)</label><input class="form-control" id="run" name="run" maxlength="15" value="{{ $f['run'] === '??' ? '' : $f['run'] }}"></div>
    <div class="form-group col-md-3"><label for="entidad">Entidad</label><input class="form-control" id="entidad" name="entidad" maxlength="60" value="{{ $f['entidad'] }}"></div>
    <div class="form-group col-md-3"><label for="ip">Dirección IP</label><input class="form-control" id="ip" name="ip" maxlength="45" value="{{ $f['ip'] }}"></div>
    <div class="form-group col-md-3"><label for="sensible">Sensibilidad</label><select class="form-control" id="sensible" name="sensible"><option value="">Todos</option><option value="1" @selected($f['sensible']==='1')>Solo sensibles</option><option value="0" @selected($f['sensible']==='0')>No sensibles</option></select></div>
  </div>
  <div><button class="btn btn-primary">Filtrar</button> <a class="btn btn-link" href="{{ route('app.logs') }}">Limpiar</a>
    @if($puedeExportar)<a class="btn btn-outline-primary" href="{{ route('app.logs.exportar', request()->query()) }}">Exportar CSV</a>@endif
    @if($puedeVerApp)<a class="btn btn-outline-secondary" href="{{ route('app.logs.aplicacion') }}">Log de aplicación</a>@endif</div>
</form>
<p role="status">{{ $filas->total() }} {{ $filas->total() === 1 ? 'registro' : 'registros' }}.</p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Registros de auditoría, más recientes primero</caption><thead><tr><th scope="col">#</th><th scope="col">Fecha</th><th scope="col">Actor</th><th scope="col">Acción</th><th scope="col">Entidad</th><th scope="col">IP</th><th scope="col">Detalle</th></tr></thead><tbody>
@forelse($filas as $v)<tr><td>{{ $v->id }}</td><td>{{ substr((string) $v->getRawOriginal('ts'), 0, 19) }}<br><small class="text-muted">UTC {{ $v->tsUtc() }}</small></td><td>{{ $v->actor_rol ?? '—' }}@if($v->actor_run)<br><small>{{ \App\Support\Run::enmascarar($v->actor_run) }}</small>@endif</td><td>{{ $v->accion }}@if($v->sensible) <span class="badge badge-warning">sensible</span>@endif</td><td>{{ $v->entidad }} {{ $v->entidad_id }}</td><td>{{ $v->ip }}</td><td><small class="text-break">{{ \Illuminate\Support\Str::limit((string) $v->detalle, 140) }}</small></td></tr>
@empty<tr><td colspan="7">Sin registros para los filtros indicados.</td></tr>@endforelse</tbody></table></div>
{{ $filas->links() }}
@endsection
