@extends('layouts.app')
@section('titulo', $i ? 'Editar iniciativa' : 'Nueva iniciativa')
@section('contenido')
<p><a href="{{ route('app.admin.cumplimiento') }}#pla">&larr; Cumplimiento normativo</a></p>
<h1>{{ $i ? 'Editar iniciativa de mejora' : 'Nueva iniciativa de mejora' }}</h1>
<form method="post" action="{{ $i ? route('app.admin.cumplimiento.iniciativa.actualizar', $i) : route('app.admin.cumplimiento.iniciativa.guardar') }}" class="col-lg-10 px-0">@csrf
<div class="form-row"><div class="form-group col-md-6"><label for="pl">Plataforma</label><select class="form-control" id="pl" name="plataforma_id" required>@foreach($plataformas as $p)<option value="{{ $p->id }}" @selected((int) old('plataforma_id', $i->plataforma_id ?? 0) === $p->id)>{{ $p->nombre }}</option>@endforeach</select></div>
<div class="form-group col-md-4"><label for="et">Etapa del ciclo</label><select class="form-control" id="et" name="etapa">@foreach(\App\Models\CalidadIniciativa::ETAPAS as $k => $l)<option value="{{ $k }}" @selected((int) old('etapa', $i->etapa ?? 1) === $k)>{{ $k }}. {{ $l }}</option>@endforeach</select></div>
<div class="form-group col-md-2"><label for="es">Estado</label><select class="form-control" id="es" name="estado">@foreach(\App\Models\CalidadIniciativa::ESTADOS as $k => $l)<option value="{{ $k }}" @selected(old('estado', $i->estado ?? 'planificada') === $k)>{{ $l }}</option>@endforeach</select></div></div>
<div class="form-group"><label for="de">Iniciativa de mejora (qué se hará)</label><textarea class="form-control" id="de" name="descripcion" rows="2" required minlength="5" maxlength="2000">{{ old('descripcion', $i->descripcion ?? '') }}</textarea></div>
<div class="form-group"><label for="ob">Objetivo esperado</label><textarea class="form-control" id="ob" name="objetivo" rows="2" maxlength="1000">{{ old('objetivo', $i->objetivo ?? '') }}</textarea></div>
<div class="form-row"><div class="form-group col-md-4"><label for="sc">Subcaracterística de calidad</label><input class="form-control" id="sc" name="subcaracteristica" maxlength="120" value="{{ old('subcaracteristica', $i->subcaracteristica ?? '') }}" placeholder="Según la guía técnica"></div>
<div class="form-group col-md-8"><label for="me">Métrica</label><input class="form-control" id="me" name="metrica" maxlength="190" value="{{ old('metrica', $i->metrica ?? '') }}"></div></div>
<fieldset class="mb-2"><legend class="h6">Umbrales</legend><div class="form-row">
<div class="form-group col-md-4"><label for="ue">Exitoso</label><input class="form-control" id="ue" name="umbral_exitoso" maxlength="120" value="{{ old('umbral_exitoso', $i->umbral_exitoso ?? '') }}"></div>
<div class="form-group col-md-4"><label for="ua">Aceptable</label><input class="form-control" id="ua" name="umbral_aceptable" maxlength="120" value="{{ old('umbral_aceptable', $i->umbral_aceptable ?? '') }}"></div>
<div class="form-group col-md-4"><label for="ui">Insuficiente</label><input class="form-control" id="ui" name="umbral_insuficiente" maxlength="120" value="{{ old('umbral_insuficiente', $i->umbral_insuficiente ?? '') }}"></div></div></fieldset>
<div class="form-row"><div class="form-group col-md-5"><label for="rs">Responsable (área o persona)</label><input class="form-control" id="rs" name="responsable" maxlength="120" value="{{ old('responsable', $i->responsable ?? '') }}"></div>
<div class="form-group col-md-3"><label for="pz">Plazo</label><input class="form-control" type="date" id="pz" name="plazo" value="{{ old('plazo', $i?->plazo?->format('Y-m-d')) }}"></div>
<div class="form-group col-md-4"><label for="co">Costo estimado (pesos)</label><input class="form-control" type="number" min="0" id="co" name="costo_estimado" value="{{ old('costo_estimado', $i->costo_estimado ?? '') }}"></div></div>
<div class="form-group"><label for="ri">Riesgos asociados</label><textarea class="form-control" id="ri" name="riesgos" rows="2" maxlength="2000">{{ old('riesgos', $i->riesgos ?? '') }}</textarea></div>
<div class="form-group"><label for="re">Resultado de la evaluación (medición y análisis)</label><textarea class="form-control" id="re" name="resultado" rows="3" maxlength="3000">{{ old('resultado', $i->resultado ?? '') }}</textarea></div>
<button class="btn btn-primary">Guardar</button> <a class="btn btn-link" href="{{ route('app.admin.cumplimiento') }}">Cancelar</a></form>
@endsection
