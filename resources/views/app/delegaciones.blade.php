@extends('layouts.app')
@section('titulo', 'Delegaciones de firma')
@section('contenido')
<h1>Delegaciones de firma</h1>
<table class="table table-sm"><thead><tr><th scope="col">Tipo</th><th scope="col">Delegado(a)</th><th scope="col">Decreto</th><th scope="col">Vigencia</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@foreach($filas as $f)<tr><td>{{ $f->tipo->nombre }}</td><td>{{ $f->delegado->nombreCompleto() }}</td><td>{{ $f->decreto_referencia }}</td><td>{{ $f->vigente_desde->format('d-m-Y') }} a {{ $f->vigente_hasta->format('d-m-Y') }}</td><td>{{ $f->activo?'activa':'revocada' }}</td>
<td>@if(auth()->user()->rolBase()==='alcalde' && $f->activo)<form method="post" action="{{ route('app.delegaciones.revocar',$f) }}">@csrf<button class="btn btn-sm btn-outline-danger">Revocar</button></form>@endif</td></tr>@endforeach</tbody></table>
@if(auth()->user()->rolBase()==='alcalde')
<form method="post" action="{{ route('app.delegaciones.crear') }}" class="card card-body col-lg-8">@csrf<h2 class="h5">Nueva delegación (solo el alcalde)</h2>
<div class="form-row"><div class="form-group col-md-6"><label for="d_t">Tipo de documento</label><select class="form-control" id="d_t" name="tipo_documento_id">@foreach($tipos as $t)<option value="{{ $t->id }}">{{ $t->nombre }}</option>@endforeach</select></div>
<div class="form-group col-md-6"><label for="d_u">Delegado(a)</label><select class="form-control" id="d_u" name="delegado_id">@foreach($usuarios as $u)<option value="{{ $u->id }}">{{ $u->nombreCompleto() }}</option>@endforeach</select></div></div>
<div class="form-group"><label for="d_r">Decreto de referencia</label><input class="form-control" id="d_r" name="decreto_referencia" required maxlength="120"></div>
<div class="form-row"><div class="form-group col-md-6"><label for="d_d">Vigente desde</label><input class="form-control" id="d_d" type="date" name="vigente_desde" required></div><div class="form-group col-md-6"><label for="d_h">Vigente hasta</label><input class="form-control" id="d_h" type="date" name="vigente_hasta" required></div></div>
<div><button class="btn btn-primary">Registrar delegación</button></div></form>@endif
@endsection
