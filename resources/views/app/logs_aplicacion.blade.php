@extends('layouts.app')
@section('titulo', 'Log de aplicación')
@section('contenido')
<h1>Log de aplicación</h1>
<p class="text-muted">Últimas líneas del log técnico. RUN, correos, tokens y rutas del servidor están redactados. <a href="{{ route('app.logs') }}">Volver al registro de auditoría</a>.</p>
<pre class="p-3 border bg-light small" tabindex="0" aria-label="Últimas líneas del log" style="max-height:32rem;overflow:auto;white-space:pre-wrap">@forelse($lineas as $l){{ $l }}
@empty Sin registros.
@endforelse</pre>
@endsection
