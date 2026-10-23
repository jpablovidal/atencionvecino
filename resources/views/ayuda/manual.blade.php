@extends('layouts.app')
@section('titulo', 'Manual: '.$nombre)
@section('contenido')
<p class="no-print">@auth<a href="{{ route('ayuda') }}">&larr; Todos los manuales</a> · @endauth<button type="button" class="btn btn-sm btn-outline-primary" data-print>Imprimir / guardar como PDF</button></p>
@if(count($m['toc']))
<nav class="ayuda-toc mb-4 no-print" aria-labelledby="toc-titulo"><p class="h5 font-weight-bold" id="toc-titulo">Contenido de este manual</p>
<ol class="mb-0">@foreach($m['toc'] as $t)<li><a href="#{{ $t['id'] }}">{{ $t['texto'] }}</a></li>@endforeach</ol></nav>
@endif
<article class="manual" lang="es-CL">
{{-- HTML generado por league/commonmark con html_input=strip y enlaces inseguros bloqueados --}}
{!! $m['html'] !!}
</article>
@endsection
