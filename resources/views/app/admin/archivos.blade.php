@extends('layouts.app')
@section('titulo', 'Archivos: pesos y formatos')
@section('contenido')
<h1>Archivos: pesos máximos y formatos</h1>
<p class="lead-sub">Defina qué formatos admite el sistema y el peso máximo por archivo. Todo archivo se valida <strong>por su contenido</strong> (no por la extensión ni por lo que declare el navegador). PDF siempre está permitido porque el sistema genera y firma PDF.</p>
<div class="alert alert-info" role="note">Límite del servidor PHP: <strong>{{ $ini['upload_max_filesize'] }}</strong> por archivo y <strong>{{ $ini['post_max_size'] }}</strong> por envío. Aunque configure más, el peso efectivo no superará <strong>{{ $limitePhpMb }} MB</strong> (en cPanel se sube en «Select PHP Version › Options»). Techo del sistema: {{ $techo }} MB.</div>
<form method="post" action="{{ route('app.admin.archivos.guardar') }}">@csrf
<fieldset class="mb-4"><legend class="h5">Formatos permitidos</legend>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Formatos de archivo</caption><thead><tr><th scope="col">Formato</th><th scope="col">Permitido</th><th scope="col">Controles al subir</th></tr></thead><tbody>
@foreach($formatos as $k => $f)<tr><th scope="row">{{ $f['etiqueta'] }}</th>
<td><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="f-{{ $k }}" name="formatos[{{ $k }}]" value="1" @checked(old('formatos.'.$k, $cfg['formatos'][$k] ?? false)) @disabled($k==='pdf')><label class="custom-control-label" for="f-{{ $k }}"><span class="sr-only">Permitir {{ $f['etiqueta'] }}</span>{{ $k==='pdf' ? 'Siempre' : 'Sí' }}</label></div></td>
<td class="small">{{ $f['riesgo'] }}</td></tr>@endforeach
</tbody></table></div></fieldset>
<fieldset class="mb-4"><legend class="h5">Peso máximo por archivo (MB)</legend>
@foreach($ambitos as $k => $txt)<div class="form-group"><label for="mb-{{ $k }}">{{ $txt }}</label><input class="form-control" style="max-width:10rem" type="number" min="1" max="{{ $techo }}" id="mb-{{ $k }}" name="max_mb[{{ $k }}]" value="{{ old('max_mb.'.$k, $cfg['max_mb'][$k]) }}" required></div>@endforeach
<div class="form-group"><label for="ma">Máximo de archivos por solicitud</label><input class="form-control" style="max-width:10rem" type="number" min="1" max="30" id="ma" name="max_archivos" value="{{ old('max_archivos', $cfg['max_archivos']) }}" required></div></fieldset>
<button class="btn btn-primary">Guardar</button></form>
<p class="small text-muted mt-3">Cada cambio queda en la bitácora de auditoría. Los archivos ya cargados no se modifican.</p>
@endsection
