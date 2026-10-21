@extends('layouts.app')
@section('titulo', $p ? 'Editar plataforma' : 'Nueva plataforma')
@section('contenido')
<p><a href="{{ route('app.admin.cumplimiento') }}#cat">&larr; Cumplimiento normativo</a></p>
<h1>{{ $p ? 'Editar plataforma' : 'Nueva plataforma' }}</h1>
<form method="post" action="{{ $p ? route('app.admin.cumplimiento.plataforma.actualizar', $p) : route('app.admin.cumplimiento.plataforma.guardar') }}" class="col-lg-9 px-0">@csrf
<div class="form-row"><div class="form-group col-md-8"><label for="n">Nombre</label><input class="form-control" id="n" name="nombre" required minlength="3" maxlength="150" value="{{ old('nombre', $p->nombre ?? '') }}"></div>
<div class="form-group col-md-4"><label for="t">Tipo</label><select class="form-control" id="t" name="tipo"><option value="propia" @selected(old('tipo', $p->tipo ?? 'propia')==='propia')>Propia del municipio</option><option value="externa" @selected(old('tipo', $p->tipo ?? '')==='externa')>Externa (otro órgano o proveedor)</option></select></div></div>
<div class="form-group"><label for="d">Descripción</label><textarea class="form-control" id="d" name="descripcion" rows="2" maxlength="1000">{{ old('descripcion', $p->descripcion ?? '') }}</textarea></div>
<div class="form-row"><div class="form-group col-md-6"><label for="r">Responsable</label><input class="form-control" id="r" name="responsable" maxlength="120" value="{{ old('responsable', $p->responsable ?? '') }}"></div>
<div class="form-group col-md-3"><label for="lf">Fecha de la línea de base</label><input class="form-control" type="date" id="lf" name="linea_base_fecha" value="{{ old('linea_base_fecha', $p?->linea_base_fecha?->format('Y-m-d')) }}"></div>
<div class="form-group col-md-3"><label for="vu">Vida útil estimada hasta</label><input class="form-control" type="date" id="vu" name="vida_util_hasta" value="{{ old('vida_util_hasta', $p?->vida_util_hasta?->format('Y-m-d')) }}"></div></div>
<div class="form-group"><label for="lb">Línea de base (estado de la plataforma cuando entra al ciclo de calidad)</label><textarea class="form-control" id="lb" name="linea_base" rows="3" maxlength="2000">{{ old('linea_base', $p->linea_base ?? '') }}</textarea></div>
<div class="custom-control custom-checkbox mb-1"><input class="custom-control-input" type="checkbox" id="pr" name="priorizada" value="1" @checked(old('priorizada', $p->priorizada ?? false))><label class="custom-control-label" for="pr">Priorizada para el Plan de Mejora Continua</label></div>
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="ac" name="activa" value="1" @checked(old('activa', $p->activa ?? true))><label class="custom-control-label" for="ac">Activa en el catálogo</label></div>
<button class="btn btn-primary">Guardar</button> <a class="btn btn-link" href="{{ route('app.admin.cumplimiento') }}">Cancelar</a></form>
@endsection
