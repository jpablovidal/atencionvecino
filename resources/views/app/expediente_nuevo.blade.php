@extends('layouts.app')
@section('titulo', 'Iniciar un expediente')
@section('contenido')
<nav aria-label="Ruta de navegación"><a href="{{ route('app.bandeja') }}">&larr; Volver a la bandeja</a></nav>
<h1>Iniciar un expediente de oficio</h1>
<p class="lead-sub">Para comenzar un procedimiento administrativo sin una solicitud previa del vecino. Quedará a su cargo, en <strong>{{ $unidad->nombre ?? 'su unidad' }}</strong>. Después podrá registrar las partes (vecinos, funcionarios, personas externas u organismos públicos de DocDigital), redactar los documentos (decreto, reglamento, oficio, etc.) y, al concluir, notificarlos.</p>
@unless($puede)<div class="alert alert-info" role="status">Su cuenta no está asignada a una unidad municipal, así que no puede iniciar expedientes. Pida a la administración que le asigne una unidad.</div>@else
<form method="post" action="{{ route('app.expediente.crear') }}" class="card card-body mb-4">@csrf
  <div class="form-group"><label for="asunto">Asunto del procedimiento</label><input class="form-control" id="asunto" name="asunto" required minlength="5" maxlength="190" value="{{ old('asunto') }}" aria-describedby="asunto-ayuda"><small id="asunto-ayuda" class="form-text text-muted">Una línea, entre 5 y 190 caracteres. Ej.: «Modificación de la ordenanza de ferias libres».</small></div>
  <div class="form-group"><label for="descripcion">Descripción (opcional)</label><textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="1000">{{ old('descripcion') }}</textarea></div>
  <div class="form-row">
    <div class="form-group col-md-5"><label for="resultado">Con qué se espera concluir</label><input class="form-control" id="resultado" name="resultado" maxlength="100" list="resultados" value="{{ old('resultado') }}" placeholder="Decreto, reglamento, oficio…"><datalist id="resultados">@foreach($tipos as $t)<option value="{{ $t }}">@endforeach<option value="Reglamento"><option value="Ordenanza"></datalist></div>
    <div class="form-group col-md-7"><label for="fundamento">Fundamento legal o antecedente (opcional)</label><input class="form-control" id="fundamento" name="fundamento" maxlength="500" value="{{ old('fundamento') }}"></div>
  </div>
  <div><button class="btn btn-primary">Iniciar expediente</button></div>
</form>
@endunless
@endsection
