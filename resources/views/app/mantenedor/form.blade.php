@extends('layouts.app')
@section('titulo', $def['titulo'])
@section('contenido')
<h1>{{ $def['titulo'] }}: {{ $fila ? 'editar' : 'nuevo' }}</h1>
<form method="post" action="{{ $fila ? route('app.admin.actualizar',[$entidad,$fila->id]) : route('app.admin.guardar',$entidad) }}" class="col-lg-9 px-0">@csrf
@foreach($def['campos'] as $k => [$label,$tipo])
@php $val = $k==='anexos_txt' ? $anexosTxt : old($k, $fila->$k ?? null); @endphp
<div class="form-group">
@if($tipo==='check')
  <div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="{{ $k }}" name="{{ $k }}" value="1" @checked($val)><label class="custom-control-label" for="{{ $k }}">{{ $label }}</label></div>
@else
  <label for="{{ $k }}">{{ $label }}</label>
  @if($tipo==='textarea')<textarea class="form-control" id="{{ $k }}" name="{{ $k }}" rows="4">{{ $val }}</textarea>
  @elseif($tipo==='editor')<textarea id="{{ $k }}" name="{{ $k }}" data-editor rows="14">{{ $val }}</textarea>
  @elseif(str_starts_with($tipo,'select:'))<select class="form-control" id="{{ $k }}" name="{{ $k }}">@foreach(explode(',',substr($tipo,7)) as $o)<option @selected($val===$o)>{{ $o }}</option>@endforeach</select>
  @elseif($tipo==='unidad')<select class="form-control" id="{{ $k }}" name="{{ $k }}"><option value="">—</option>@foreach($unidades as $u)<option value="{{ $u->id }}" @selected($val==$u->id)>{{ $u->nombre }}</option>@endforeach</select>
  @elseif($tipo==='tipo')<select class="form-control" id="{{ $k }}" name="{{ $k }}">@foreach($tipos as $t)<option value="{{ $t->id }}" @selected($val==$t->id)>{{ $t->nombre }}</option>@endforeach</select>
  @elseif($tipo==='unidades')@foreach($unidades as $u)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="u{{ $u->id }}" name="unidades[]" value="{{ $u->id }}" @checked(in_array($u->id,old('unidades',$sel)))><label class="custom-control-label" for="u{{ $u->id }}">{{ $u->nombre }}</label></div>@endforeach
  @else<input class="form-control" id="{{ $k }}" name="{{ $k }}" type="{{ $tipo }}" value="{{ $val }}">@endif
@endif
</div>
@endforeach
<button class="btn btn-primary">Guardar</button> <a class="btn btn-link" href="{{ route('app.admin.index',$entidad) }}">Cancelar</a>
</form>
@endsection
@push('scripts')@if($entidad==='plantillas')<script src="{{ asset('assets/tinymce/tinymce.min.js') }}"></script>@endif @endpush
