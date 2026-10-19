@extends('layouts.app')
@section('titulo', 'Versiones del archivo')
@section('contenido')
<nav aria-label="Ruta de navegación"><a href="{{ route('app.expediente', $e) }}">&larr; Volver al expediente {{ $e->codigo }}</a></nav>
<h1>Versiones: {{ $a->titulo ?: $a->nombre_original }}</h1>
<p class="lead-sub">Si un archivo cambia, el cambio queda como una <strong>versión nueva</strong>; las anteriores se conservan íntegras. Cada versión indica quién la subió, por qué, qué cambió respecto de la anterior y las firmas electrónicas que trae el archivo.</p>

@if($versionable && $puedeOperar)
<form method="post" action="{{ route('app.expediente.archivo.version.nueva', [$e, $a]) }}" enctype="multipart/form-data" class="card card-body mb-4">@csrf
 <h2 class="h5">Subir una versión nueva</h2>
 <div class="form-row"><div class="form-group col-md-5"><label for="vf">Archivo</label><input class="form-control-file" type="file" id="vf" name="archivo" required></div>
 <div class="form-group col-md-7"><label for="vm">Motivo del cambio</label><input class="form-control" id="vm" name="motivo" required minlength="5" maxlength="500" placeholder="Ej.: se corrigió el monto y se agregó la firma del director"></div></div>
 <div><button class="btn btn-primary">Crear versión nueva</button></div></form>
@endif

<ol class="list-unstyled">
@foreach($historial as $h) @php $v = $h['v']; $res = $h['resumen']; @endphp
<li class="card mb-3"><div class="card-body">
 <h2 class="h5 mb-1">Versión {{ $v->numero }} @if($h['vigente'])<span class="chip">vigente</span>@endif</h2>
 <p class="small text-muted mb-2">{{ $v->created_at?->timezone('America/Santiago')->format('d-m-Y H:i') }} · {{ $v->autor_nombre ?? 'Sin autor registrado' }} · {{ number_format($v->tamano / 1024, 1, ',', '.') }} KB · <code title="Huella SHA-256">{{ substr($v->sha256, 0, 12) }}…</code></p>
 <p class="mb-1"><strong>Motivo:</strong> {{ $v->motivo ?: '—' }}</p>
 <p class="mb-1"><strong>Qué cambió respecto de la versión anterior:</strong> {{ $res['texto_resumen'] ?? ($v->numero === 1 ? 'Versión original.' : 'Sin detalle.') }}</p>
 @if(! empty($res['texto']['ejemplos_agregadas']))<p class="small mb-1">Líneas agregadas (ejemplos): @foreach($res['texto']['ejemplos_agregadas'] as $x)<q>{{ $x }}</q>@unless($loop->last), @endunless @endforeach</p>@endif
 @if(! empty($res['texto']['ejemplos_quitadas']))<p class="small mb-1">Líneas quitadas (ejemplos): @foreach($res['texto']['ejemplos_quitadas'] as $x)<q>{{ $x }}</q>@unless($loop->last), @endunless @endforeach</p>@endif
 <p class="mb-2"><strong>Subió/editó:</strong> {{ $v->autor_nombre ?? '—' }}.
  <strong>Firmas electrónicas en el archivo:</strong>
  @if($h['firmas']) @foreach($h['firmas'] as $f){{ $f['nombre'] ?? 'Firmante sin nombre' }} ({{ ($f['cadena'] ?? '') === 'verificada' ? 'emisor verificado' : 'emisor no verificado' }})@unless($loop->last), @endunless @endforeach
  @else ninguna detectada @endif.
  <span class="small text-muted">Las visaciones y firmas dentro del sistema se registran en el historial de documentos.</span></p>
 <a class="btn btn-sm btn-outline-primary" href="{{ route('app.expediente.archivo.ver', [$e, $a, $v->numero]) }}" data-visor data-visor-mime="{{ $v->mime }}" data-visor-titulo="{{ ($a->titulo ?: $a->nombre_original) }} — versión {{ $v->numero }}" data-visor-descarga="{{ route('app.expediente.archivo.version.descargar', [$e, $a, $v->numero]) }}">Ver versión {{ $v->numero }}</a>
 <a class="btn btn-sm btn-outline-secondary" href="{{ route('app.expediente.archivo.version.descargar', [$e, $a, $v->numero]) }}">Descargar</a>
</div></li>
@endforeach
</ol>
@include('partials.visor_modal')
@endsection
