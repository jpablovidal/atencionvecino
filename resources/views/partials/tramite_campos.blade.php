{{-- Campos propios del formulario de un trámite. Variables: $campos (colección de TramiteCampo), $hoy (Y-m-d, hora de Chile) --}}
@php $svcCampos = app(\App\Services\TramiteCamposService::class); @endphp
@foreach($campos as $c)
@php
  $k = $c->clave; $id = 'campo-'.$k; $ayuda = $c->ayuda ? $id.'-ayuda' : null; $v = old('campos.'.$k); $req = $c->obligatorio;
  $lim = fn ($x) => $x === 'hoy' ? $hoy : $x;
@endphp
@if($c->tipo === 'titulo')
<h3 class="h5 mt-4 mb-2">{{ $c->etiqueta }}</h3>
@elseif($c->tipo === 'calculo')
<div class="form-group border rounded p-2 bg-light" data-campo="{{ $k }}" data-tipo="calculo" data-formula="{{ $c->conf('formula') }}" data-decimales="{{ (int) $c->conf('decimales', 0) }}" data-prefijo="{{ $c->conf('prefijo') }}" data-sufijo="{{ $c->conf('sufijo') }}">
  <span class="font-weight-bold" id="{{ $id }}-et">{{ $c->etiqueta }}</span>: <output class="h5 mb-0" data-resultado aria-live="polite" aria-labelledby="{{ $id }}-et">—</output>
  @if($c->ayuda)<small class="form-text text-muted">{{ $c->ayuda }} (se calcula solo)</small>@else<small class="form-text text-muted">Se calcula solo con los datos que usted ingresa.</small>@endif
</div>
@else
<div class="form-group" data-campo="{{ $k }}" data-tipo="{{ $c->tipo }}">
  @if($c->tipo === 'si_no')
  <fieldset><legend class="h6 mb-1">{{ $c->etiqueta }}@if($req) <span class="text-danger">(obligatorio)</span>@endif</legend>
    @foreach(['1' => 'Sí', '0' => 'No'] as $val => $et)<div class="custom-control custom-radio custom-control-inline"><input class="custom-control-input" type="radio" id="{{ $id }}-{{ $val }}" name="campos[{{ $k }}]" value="{{ $val }}" @checked((string) $v === (string) $val) @if($req && $val === '1') required @endif @if($ayuda) aria-describedby="{{ $ayuda }}" @endif><label class="custom-control-label" for="{{ $id }}-{{ $val }}">{{ $et }}</label></div>@endforeach
  </fieldset>
  @elseif($c->tipo === 'multiple')
  <fieldset><legend class="h6 mb-1">{{ $c->etiqueta }}@if($req) <span class="text-danger">(obligatorio)</span>@endif</legend>
    @foreach($svcCampos->opcionesDe((array) $c->config) as $n => $o)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="{{ $id }}-{{ $n }}" name="campos[{{ $k }}][]" value="{{ $o['valor'] }}" @checked(in_array($o['valor'], (array) $v, true)) @if($ayuda) aria-describedby="{{ $ayuda }}" @endif><label class="custom-control-label" for="{{ $id }}-{{ $n }}">{{ $o['etiqueta'] }}</label></div>@endforeach
  </fieldset>
  @else
  <label for="{{ $id }}">{{ $c->etiqueta }}@if($req) <span class="text-danger">(obligatorio)</span>@endif</label>
  @if($c->tipo === 'texto')
  <input class="form-control @error('campos.'.$k) is-invalid @enderror" id="{{ $id }}" name="campos[{{ $k }}]" maxlength="{{ (int) $c->conf('maxlen', 190) }}" value="{{ $v }}" @required($req) @if($ayuda) aria-describedby="{{ $ayuda }}" @endif>
  @elseif($c->tipo === 'parrafo')
  <textarea class="form-control @error('campos.'.$k) is-invalid @enderror" id="{{ $id }}" name="campos[{{ $k }}]" rows="4" maxlength="{{ (int) $c->conf('maxlen', 2000) }}" @required($req) @if($ayuda) aria-describedby="{{ $ayuda }}" @endif>{{ $v }}</textarea>
  @elseif($c->tipo === 'numero')
  @php $dec = (int) $c->conf('decimales', 0); @endphp
  <div class="input-group">@if($c->conf('prefijo'))<div class="input-group-prepend"><span class="input-group-text">{{ $c->conf('prefijo') }}</span></div>@endif
    <input class="form-control @error('campos.'.$k) is-invalid @enderror" type="number" id="{{ $id }}" name="campos[{{ $k }}]" step="{{ $dec > 0 ? '0.'.str_repeat('0', $dec - 1).'1' : '1' }}" @if($c->conf('min') !== null) min="{{ $c->conf('min') }}" @endif @if($c->conf('max') !== null) max="{{ $c->conf('max') }}" @endif value="{{ $v }}" @required($req) inputmode="decimal" @if($ayuda) aria-describedby="{{ $ayuda }}" @endif>
    @if($c->conf('sufijo'))<div class="input-group-append"><span class="input-group-text">{{ $c->conf('sufijo') }}</span></div>@endif</div>
  @elseif($c->tipo === 'fecha')
  <input class="form-control @error('campos.'.$k) is-invalid @enderror" type="date" id="{{ $id }}" name="campos[{{ $k }}]" @if($c->conf('fecha_min')) min="{{ $lim($c->conf('fecha_min')) }}" @endif @if($c->conf('fecha_max')) max="{{ $lim($c->conf('fecha_max')) }}" @endif value="{{ $v }}" @required($req) @if($ayuda) aria-describedby="{{ $ayuda }}" @endif>
  @else {{-- lista --}}
  <select class="form-control @error('campos.'.$k) is-invalid @enderror" id="{{ $id }}" name="campos[{{ $k }}]" @required($req) @if($ayuda) aria-describedby="{{ $ayuda }}" @endif><option value="">Elija una opción…</option>
    @foreach($svcCampos->opcionesDe((array) $c->config) as $o)<option value="{{ $o['valor'] }}" data-valor="{{ $o['valor'] }}" @selected((string) $v === (string) $o['valor'])>{{ $o['etiqueta'] }}</option>@endforeach</select>
  @endif
  @endif
  @if($c->ayuda)<small class="form-text text-muted" id="{{ $id }}-ayuda">{{ $c->ayuda }}</small>@endif
  @error('campos.'.$k)<div class="invalid-feedback d-block" role="alert">{{ $message }}</div>@enderror
</div>
@endif
@endforeach
