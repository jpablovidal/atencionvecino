@php $perm = $turno->firma_permitida ?: 'avanzada'; $pre = $pre ?? 'f'; @endphp
<form method="post" action="{{ $accion }}" class="card card-body mb-4" autocomplete="off">@csrf
<h3 class="h6">Es su turno de firmar @if($perm==='avanzada')(firma electrónica avanzada FirmaGob)@elseif($perm==='simple')(firma electrónica simple)@else(firma electrónica simple o avanzada, a su elección)@endif</h3>
@if($perm==='ambas')
<fieldset class="form-group"><legend class="h6">Tipo de firma</legend>
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="{{ $pre }}_tav" name="tipo_firma" value="avanzada" checked><label class="custom-control-label" for="{{ $pre }}_tav">Avanzada (FirmaGob, con código OTP)</label></div>
<div class="custom-control custom-radio"><input class="custom-control-input" type="radio" id="{{ $pre }}_tsi" name="tipo_firma" value="simple"><label class="custom-control-label" for="{{ $pre }}_tsi">Simple (confirmación en el sistema)</label></div></fieldset>
@else<input type="hidden" name="tipo_firma" value="{{ $perm }}">@endif
@if($perm!=='simple')<div class="form-group col-md-4 px-0"><label for="{{ $pre }}_otp">Código OTP de 6 dígitos @if($perm==='ambas')<span class="small text-muted">(solo firma avanzada)</span>@endif</label><input class="form-control" id="{{ $pre }}_otp" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" @if($perm==='avanzada') required @endif autocomplete="one-time-code"></div>@endif
@if($perm!=='avanzada')<div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="{{ $pre }}_ok" name="confirmo" value="1"><label class="custom-control-label" for="{{ $pre }}_ok">Para la firma simple: confirmo que revisé el documento y manifiesto mi voluntad de firmarlo.</label></div>@endif
<div><button class="btn btn-success">Firmar</button></div></form>
