@extends('layouts.app')
@section('titulo', 'Validación de comprobante')
@section('contenido')
<h1>Validación de comprobante de ingreso</h1>
@if($ok)<div class="alert alert-success" role="status">El comprobante del folio <strong>{{ $folio }}</strong> es auténtico (ingresado el {{ $s->created_at->timezone('America/Santiago')->format('d-m-Y H:i') }}).</div>
@else<div class="alert alert-danger" role="alert">No se pudo verificar este comprobante.</div>@endif
@endsection
