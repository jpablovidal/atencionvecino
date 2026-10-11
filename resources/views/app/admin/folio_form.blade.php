@extends('layouts.app')
@section('titulo', 'Folios: '.$t->nombre)
@section('contenido')
<p><a href="{{ route('app.admin.folios') }}">&larr; Folios</a></p>
<h1>Folios: {{ $t->nombre }}</h1>
<form method="post" action="{{ route('app.admin.folios.actualizar',$t) }}" class="col-lg-8 px-0 mb-4">@csrf
<div class="form-group"><label for="folio_formato">Formato del folio</label>
<input class="form-control" id="folio_formato" name="folio_formato" value="{{ old('folio_formato',$t->folio_formato) }}" required maxlength="80" aria-describedby="ayuda-formato">
<small id="ayuda-formato" class="form-text text-muted">Campos: <code>{PREFIJO}</code> (prefijo del tipo: «{{ $t->prefijo }}»), <code>{TIPO}</code> (código «{{ $t->codigo }}»), <code>{N}</code> (número correlativo; obligatorio, una vez), <code>{AAAA}</code> y <code>{AA}</code> (año). Con reinicio anual debe incluir el año.</small></div>
<div class="form-row"><div class="form-group col-md-4"><label for="folio_digitos">Dígitos de relleno (0 = sin relleno)</label><input class="form-control" type="number" id="folio_digitos" name="folio_digitos" min="0" max="8" value="{{ old('folio_digitos',$t->folio_digitos) }}" required></div>
<div class="form-group col-md-4"><label for="folio_inicio">Primer número de cada contador</label><input class="form-control" type="number" id="folio_inicio" name="folio_inicio" min="1" value="{{ old('folio_inicio',$t->folio_inicio) }}" required></div></div>
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="folio_reinicio_anual" name="folio_reinicio_anual" value="1" @checked(old('folio_reinicio_anual',$t->folio_reinicio_anual))><label class="custom-control-label" for="folio_reinicio_anual">Reiniciar la numeración cada 1 de enero (hora de Chile)</label></div>
<p>Ejemplos con la configuración actual: <strong>{{ $ejemplo[0] }}</strong>, <strong>{{ $ejemplo[1] }}</strong>, <strong>{{ $ejemplo[2] }}</strong>.</p>
<button class="btn btn-primary">Guardar configuración</button></form>

<h2 class="h4">Contadores actuales</h2>
<table class="table table-sm col-lg-6 px-0"><caption class="sr-only">Contadores</caption><thead><tr><th scope="col">Año</th><th scope="col">Último número emitido</th></tr></thead><tbody>
@forelse($contadores as $c)<tr><td>{{ $c['anio'] === 0 ? 'continuo' : $c['anio'] }}</td><td>{{ $c['ultimo'] }}</td></tr>@empty<tr><td colspan="2">Aún no se han emitido folios.</td></tr>@endforelse</tbody></table>

<h2 class="h4 mt-4">Ajustar contador</h2>
<p class="small text-muted">Solo permite <strong>subir</strong> el contador (por ejemplo, al migrar documentos de otro sistema). Nunca puede disminuir: se generarían folios duplicados. Queda registrado en la bitácora con el motivo.</p>
<form method="post" action="{{ route('app.admin.folios.ajustar',$t) }}" class="form-row col-lg-10 px-0">@csrf
<div class="form-group col-md-2"><label for="aj_anio">Año</label><input class="form-control" type="number" id="aj_anio" name="anio" min="0" value="{{ $t->folio_reinicio_anual ? $anio : 0 }}" required aria-describedby="aj-ayuda"></div>
<div class="form-group col-md-3"><label for="aj_ultimo">Nuevo último número</label><input class="form-control" type="number" id="aj_ultimo" name="ultimo" min="0" required></div>
<div class="form-group col-md-5"><label for="aj_motivo">Motivo</label><input class="form-control" id="aj_motivo" name="motivo" required minlength="5" maxlength="255"></div>
<div class="form-group col-md-2 align-self-end"><button class="btn btn-outline-primary">Ajustar</button></div></form>
<small id="aj-ayuda" class="form-text text-muted">Año 0 = contador continuo (tipos sin reinicio anual).</small>
@endsection
