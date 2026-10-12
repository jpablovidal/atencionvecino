@extends('layouts.app')
@section('titulo', 'Versión de plantilla')
@section('contenido')
<p><a href="{{ route('app.admin.plantillas.versiones', $p) }}">← Historial de «{{ $p->nombre }}»</a></p>
<h1>{{ $v->nombre }} — versión {{ $v->version }}</h1>
<div class="formato-vista" tabindex="0" aria-label="Vista previa del documento">{!! $html !!}</div>
@endsection
