@extends('layouts.app')
@section('titulo', 'Mis firmas')
@section('contenido')
<h1>Mis firmas y visaciones</h1>
<p class="lead-sub">Documentos en los que <strong>ya le toca</strong> firmar o visar. Puede resolver <strong>uno o varios</strong> a la vez, o <strong>todos</strong> los pendientes (hasta {{ $lote }} por lote). Cada documento sigue las mismas reglas que la firma individual; abra los documentos para revisarlos antes de firmar.</p>
@if($resultado)
<section class="card mb-4" aria-labelledby="res-lote"><div class="card-body"><h2 class="h5" id="res-lote">Resultado del lote</h2>
<div class="table-responsive"><table class="table table-sm mb-0"><caption class="sr-only">Resultado por documento</caption><thead><tr><th scope="col">Documento</th><th scope="col">Resultado</th></tr></thead><tbody>
@foreach($resultado as $r)<tr><td>{{ $r['documento'] }}</td><td><span class="estado {{ $r['ok'] ? 'estado-vigente' : 'estado-fallida' }}">{{ $r['ok'] ? 'Listo' : 'No procesado' }}</span> {{ $r['mensaje'] }}</td></tr>@endforeach</tbody></table></div></div></section>
@endif

<h2 class="h4" id="para-firmar">Para firmar ({{ $firmas->count() }})</h2>
@if(! $puedeFirmar && $firmas->isNotEmpty())<div class="alert alert-warning" role="status">Su cuenta no está habilitada para firmar: pida a la administración que la habilite.</div>@endif
@if($firmas->isEmpty())<p>No tiene documentos por firmar.</p>@else
<form method="post" action="{{ route('app.firmas.firmar') }}" data-seleccion data-max="{{ $lote }}" class="mb-4" autocomplete="off">@csrf
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Documentos por firmar</caption>
<thead><tr><th scope="col"><input type="checkbox" data-todas aria-label="Seleccionar todos los documentos de esta lista"><span class="sr-only">Seleccionar</span></th><th scope="col">Documento</th><th scope="col">Tipo</th><th scope="col">Expediente</th><th scope="col">Firma</th><th scope="col">Espera</th></tr></thead><tbody>
@foreach($firmas as $i) @php $d = $i['documento']; @endphp
<tr><td><input type="checkbox" name="pasos[]" value="{{ $i['paso']->id }}" data-fila @disabled($i['oculto']) @if($i['oculto']) data-bloqueada @endif aria-label="Seleccionar {{ $i['oculto'] ? 'documento restringido' : $d->titulo }}"></td>
<td>@if($i['oculto'])<em>Documento restringido</em> <span class="chip chip-claro">sin autorización</span>@else<a href="{{ route('app.documento', $d) }}">{{ $d->titulo }}</a>@endif</td><td>{{ $d->tipo?->nombre }}</td><td>{{ $d->expediente?->codigo }}</td>
<td>{{ $i['avanzada'] && $i['simple'] ? 'Avanzada o simple' : ($i['simple'] ? 'Simple' : 'Avanzada') }}</td><td class="text-nowrap">{{ $i['dias'] }} d @if($i['dias'] > 3)<span aria-label="Más de 3 días">⚠</span>@endif</td></tr>@endforeach</tbody></table></div>
<p class="small mb-2" data-contador role="status" aria-live="polite">Ningún documento seleccionado</p>
<div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="f_todos" name="todos" value="1"><label class="custom-control-label" for="f_todos">Firmar <strong>todos los pendientes</strong> de esta lista (el primer tramo de hasta {{ $lote }}; repita si quedan más)</label></div>
<fieldset class="form-group"><legend class="h6">Tipo de firma</legend>
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="f_tav" name="tipo_firma" value="avanzada" checked><label class="custom-control-label" for="f_tav">Avanzada (FirmaGob, con código OTP)</label></div>
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="f_tsi" name="tipo_firma" value="simple"><label class="custom-control-label" for="f_tsi">Simple (solo en los documentos que la admiten)</label></div></fieldset>
<div class="form-group col-md-4 px-0"><label for="f_otp">Código OTP de 6 dígitos <span class="small text-muted">(firma avanzada)</span></label><input class="form-control" id="f_otp" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code"></div>
<div class="custom-control custom-checkbox mb-1"><input class="custom-control-input" type="checkbox" id="f_rev" name="reviso" value="1" required><label class="custom-control-label" for="f_rev">Revisé los documentos que voy a firmar.</label></div>
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="f_ok" name="confirmo" value="1"><label class="custom-control-label" for="f_ok">Para la firma simple: manifiesto mi voluntad de firmarlos (debe haber ingresado en los últimos 30 minutos).</label></div>
<button class="btn btn-success" data-enviar @disabled(! $puedeFirmar)>Firmar lo seleccionado</button>
<p class="small text-muted mt-2 mb-0">Con firma avanzada el lote se detiene si el servicio de firma rechaza un documento, para no repetir un código posiblemente inválido; lo ya firmado queda firmado. Si el servicio exige un código por documento, repita con un código nuevo para el resto.</p></form>
@endif

<h2 class="h4" id="para-visar">Para visar ({{ $visaciones->count() }})</h2>
@if($visaciones->isEmpty())<p>No tiene documentos por visar.</p>@else
<form method="post" action="{{ route('app.firmas.visar') }}" data-seleccion data-max="{{ $lote }}" class="mb-4">@csrf
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Documentos por visar</caption>
<thead><tr><th scope="col"><input type="checkbox" data-todas aria-label="Seleccionar todos los documentos de esta lista"><span class="sr-only">Seleccionar</span></th><th scope="col">Documento</th><th scope="col">Tipo</th><th scope="col">Expediente</th><th scope="col">Espera</th></tr></thead><tbody>
@foreach($visaciones as $i) @php $d = $i['documento']; @endphp
<tr><td><input type="checkbox" name="pasos[]" value="{{ $i['paso']->id }}" data-fila @disabled($i['oculto']) @if($i['oculto']) data-bloqueada @endif aria-label="Seleccionar {{ $i['oculto'] ? 'documento restringido' : $d->titulo }}"></td>
<td>@if($i['oculto'])<em>Documento restringido</em> <span class="chip chip-claro">sin autorización</span>@else<a href="{{ route('app.documento', $d) }}">{{ $d->titulo }}</a>@endif</td><td>{{ $d->tipo?->nombre }}</td><td>{{ $d->expediente?->codigo }}</td><td class="text-nowrap">{{ $i['dias'] }} d @if($i['dias'] > 3)<span aria-label="Más de 3 días">⚠</span>@endif</td></tr>@endforeach</tbody></table></div>
<p class="small mb-2" data-contador role="status" aria-live="polite">Ningún documento seleccionado</p>
<div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="v_todos" name="todos" value="1"><label class="custom-control-label" for="v_todos">Visar <strong>todos los pendientes</strong> de esta lista (el primer tramo de hasta {{ $lote }})</label></div>
<div class="form-group"><label for="v_com">Comentario (opcional, se agrega a cada visación)</label><input class="form-control" id="v_com" name="comentario" maxlength="1000"></div>
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="v_rev" name="reviso" value="1" required><label class="custom-control-label" for="v_rev">Revisé los documentos que voy a visar.</label></div>
<button class="btn btn-success" data-enviar>Visar lo seleccionado</button>
<p class="small text-muted mt-2 mb-0">Solo se aprueba por lote. Para <strong>rechazar</strong> un documento ábralo y rechácelo con su comentario.</p></form>
@endif
@push('scripts')<script src="{{ asset('assets/js/firmas.js') }}?v={{ substr((string) @md5_file(public_path('assets/js/firmas.js')), 0, 8) }}" defer></script>@endpush
@endsection
