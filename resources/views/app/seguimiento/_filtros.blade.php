<form method="get" action="{{ $accion }}" class="card card-body mb-3" role="search" aria-label="Filtros de búsqueda">
@if(!empty($vista))<input type="hidden" name="vista" value="{{ $vista }}">@endif
<div class="form-row">
  <div class="form-group col-md-3"><label for="f-run">RUN del solicitante</label><input class="form-control @error('run') is-invalid @enderror" id="f-run" name="run" value="{{ $f['run'] ?? old('run') }}" maxlength="15" placeholder="12.345.678-5" autocomplete="off">@error('run')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
  <div class="form-group col-md-3"><label for="f-folio">Folio</label><input class="form-control" id="f-folio" name="folio" value="{{ $f['folio'] ?? '' }}" maxlength="30" placeholder="SD-2026-XXXXXX"></div>
  <div class="form-group col-md-3"><label for="f-desde">Ingresada desde</label><input class="form-control" type="date" id="f-desde" name="desde" value="{{ $f['desde'] ?? '' }}"></div>
  <div class="form-group col-md-3"><label for="f-hasta">Ingresada hasta</label><input class="form-control @error('hasta') is-invalid @enderror" type="date" id="f-hasta" name="hasta" value="{{ $f['hasta'] ?? '' }}">@error('hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
</div>
<div class="form-row">
  <div class="form-group col-md-3"><label for="f-unidad">Dirección municipal</label><select class="form-control" id="f-unidad" name="unidad_id"><option value="">Todas</option>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected(($f['unidad_id'] ?? null) == $un->id)>{{ $un->nombre }}</option>@endforeach</select></div>
  <div class="form-group col-md-3"><label for="f-tramite">Trámite</label><select class="form-control" id="f-tramite" name="tramite_id"><option value="">Todos</option>@foreach($tramites as $t)<option value="{{ $t->id }}" @selected(($f['tramite_id'] ?? null) == $t->id)>{{ \Illuminate\Support\Str::limit($t->nombre, 50) }}</option>@endforeach</select></div>
  <div class="form-group col-md-3"><label for="f-etapa">Etapa</label><select class="form-control" id="f-etapa" name="etapa"><option value="">Todas</option>@foreach($etapas as $k=>$v)<option value="{{ $k }}" @selected(($f['etapa'] ?? null) === $k)>{{ $v }}</option>@endforeach</select></div>
  <div class="form-group col-md-3"><label for="f-plazo">Plazo</label><select class="form-control" id="f-plazo" name="plazo"><option value="">Todos</option>@foreach($plazos as $k=>$v)<option value="{{ $k }}" @selected(($f['plazo'] ?? null) === $k)>{{ $v }}</option>@endforeach</select></div>
</div>
<div><button class="btn btn-primary">Buscar</button> <a class="btn btn-link" href="{{ $accion }}{{ !empty($vista) ? '?vista='.$vista : '' }}">Limpiar filtros</a></div>
</form>
