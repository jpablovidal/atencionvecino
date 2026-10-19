@extends('layouts.app')
@section('titulo', 'Áreas del municipio')
@section('contenido')
<h1>Áreas del municipio</h1>
<p class="lead-sub">Señale qué áreas tiene su municipio. Cada usuario puede pertenecer a <strong>una o más áreas</strong> (se asigna en la ficha del usuario) y cada unidad a una. Las áreas <strong>organizan</strong> listados, codificación e informes; <strong>no restringen</strong> el acceso a los expedientes, que sigue regido por la unidad, el rol, los permisos y los accesos restringidos.</p>
<form method="post" action="{{ route('app.admin.areas.guardar') }}">@csrf
<div class="table-responsive"><table class="table"><caption class="sr-only">Áreas</caption>
<thead><tr><th scope="col">Área</th><th scope="col">Habilitada</th><th scope="col">Código de institución en el Codificador (opcional)</th></tr></thead><tbody>
@foreach($areas as $a)
<tr><th scope="row">{{ $a->nombre }}</th>
<td><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="h-{{ $a->codigo }}" name="area[{{ $a->codigo }}][habilitada]" value="1" @checked(old('area.'.$a->codigo.'.habilitada', $a->habilitada)) @disabled($a->codigo==='municipal')><label class="custom-control-label" for="h-{{ $a->codigo }}"><span class="sr-only">Habilitar {{ $a->nombre }}</span>@if($a->codigo==='municipal') Siempre habilitada @else Habilitada @endif</label></div></td>
<td><label class="sr-only" for="c-{{ $a->codigo }}">Código de institución de {{ $a->nombre }}</label><input class="form-control" id="c-{{ $a->codigo }}" name="area[{{ $a->codigo }}][institucion_codigo]" maxlength="30" value="{{ old('area.'.$a->codigo.'.institucion_codigo', $a->institucion_codigo) }}" placeholder="Vacío = el del municipio"></td></tr>
@endforeach
</tbody></table></div>
<h2 class="h5 mt-4">Unidades por área</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Área de cada unidad</caption><thead><tr><th scope="col">Unidad</th><th scope="col">Área</th></tr></thead><tbody>
@foreach($unidades as $un)<tr><th scope="row">{{ $un->nombre }}</th><td><label class="sr-only" for="u{{ $un->id }}">Área de {{ $un->nombre }}</label><select class="form-control form-control-sm" id="u{{ $un->id }}" name="unidad[{{ $un->id }}]"><option value="">Sin área</option>@foreach($habilitadas as $a)<option value="{{ $a->id }}" @selected((int) old('unidad.'.$un->id, $un->area_id) === $a->id)>{{ $a->nombre }}</option>@endforeach</select></td></tr>@endforeach
</tbody></table></div>
<button class="btn btn-primary">Guardar áreas</button></form>
@endsection
