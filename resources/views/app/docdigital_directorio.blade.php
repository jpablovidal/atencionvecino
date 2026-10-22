@extends('layouts.app')
@section('titulo', 'Directorio de DocDigital')
@section('contenido')
<nav aria-label="Ruta de navegación"><a href="{{ route('app.docdigital') }}">&larr; Volver a DocDigital</a></nav>
<h1>Directorio de DocDigital</h1>
<p class="lead-sub">Entidades y personas habilitadas en DocDigital para despachar, derivar y gestionar documentos. Se cargan solas desde la API de DocDigital y se actualizan cada día.</p>

<section class="card mb-4"><div class="card-body">
  <h2 class="h5">Estado de la carga</h2>
  <dl class="row mb-2">
    <dt class="col-md-3">Entidades</dt><dd class="col-md-9">{{ number_format($res['entidades'], 0, ',', '.') }}</dd>
    <dt class="col-md-3">Personas habilitadas</dt><dd class="col-md-9">{{ number_format($res['personas'], 0, ',', '.') }} (de esta municipalidad: {{ number_format($res['propias'], 0, ',', '.') }}; con rol de gestión: {{ number_format($res['con_roles'], 0, ',', '.') }})</dd>
    <dt class="col-md-3">Funcionarios de este sistema que están en DocDigital</dt><dd class="col-md-9">{{ $res['sgd_enlazados'] }}</dd>
    <dt class="col-md-3">Última carga completa</dt><dd class="col-md-9">@if(!empty($res['estado']['completado_en'])){{ \Illuminate\Support\Carbon::parse($res['estado']['completado_en'])->timezone('America/Santiago')->format('d-m-Y H:i') }}@else aún no se completa @endif
      @if(!empty($res['estado']['cursor']))<span class="estado estado-pendiente">en curso: {{ $res['estado']['cursor']['fase'] }}, página {{ (int) $res['estado']['cursor']['pagina'] + 1 }}</span>@endif</dd>
    @if(!empty($res['estado']['error']))<dt class="col-md-3">Último error</dt><dd class="col-md-9 text-danger">{{ $res['estado']['error'] }}</dd>@endif
  </dl>
  <form method="post" action="{{ route('app.docdigital.directorio.cargar') }}" class="d-flex flex-wrap align-items-center">@csrf
    <button class="btn btn-outline-primary mr-2 mb-2" @disabled(! $modoApi)>Continuar / actualizar ahora</button>
    <div class="custom-control custom-checkbox mr-2 mb-2"><input class="custom-control-input" type="checkbox" id="forzar" name="forzar" value="1"><label class="custom-control-label" for="forzar">Empezar una carga completa</label></div>
  </form>
  @unless($modoApi)<p class="small text-muted mb-0">DocDigital no está en modo DemoDoc ni Producción: el directorio se carga cuando se conecta por API.</p>@endunless
  <p class="small text-muted mb-0">De las personas se guarda su nombre, cargo, correo institucional y entidad. El RUN no se guarda: solo una huella para reconocer a los funcionarios de este sistema.</p>
</div></section>

<form method="get" class="form-inline mb-3">
  <label class="mr-2" for="ver">Ver</label>
  <select class="form-control mr-2" id="ver" name="ver"><option value="propias" @selected($ver==='propias')>Personas de esta municipalidad</option><option value="personas" @selected($ver==='personas')>Todas las personas</option><option value="entidades" @selected($ver==='entidades')>Entidades</option></select>
  <label class="mr-2" for="q">Buscar</label><input class="form-control mr-2" id="q" name="q" value="{{ $q }}" maxlength="100">
  <button class="btn btn-primary">Buscar</button>
</form>

<div class="table-responsive">
@if($ver==='entidades')
<table class="table table-sm"><caption class="sr-only">Entidades de DocDigital</caption><thead><tr><th scope="col">Id</th><th scope="col">Entidad</th><th scope="col">Organismo</th></tr></thead><tbody>
@forelse($filas as $e)<tr><td><code>{{ $e->entidad_id }}</code></td><td>{{ $e->nombre }}</td><td>{{ $e->organismo_nombre ?: '—' }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No hay entidades cargadas con este criterio.</td></tr>@endforelse</tbody></table>
@else
<table class="table table-sm"><caption class="sr-only">Personas habilitadas en DocDigital</caption><thead><tr><th scope="col">Persona</th><th scope="col">Cargo</th><th scope="col">Entidad</th><th scope="col">Roles en DocDigital</th><th scope="col">En este sistema</th></tr></thead><tbody>
@forelse($filas as $p)@php $loc = $p->run_huella ? ($enlaces[$p->run_huella] ?? null) : null; @endphp<tr><td>{{ $p->nombre }}<br><small class="text-muted">{{ $p->run_mascara }} @if($p->email)· {{ $p->email }}@endif</small></td><td>{{ $p->cargo ?: '—' }}</td><td>{{ $p->entidad_nombre ?: '—' }}</td>
<td>@forelse($p->rolesEtiquetas() as $re)<span class="estado">{{ $re }}</span> @empty — @endforelse</td><td>@if($loc)@if(auth()->user()->tienePermiso('roles.gestionar'))<a href="{{ route('app.admin.permisos.usuario', $loc) }}">{{ $loc->nombreCompleto() }}</a>@else{{ $loc->nombreCompleto() }}@endif @else <span class="text-muted">sin cuenta</span>@endif</td></tr>
@empty<tr><td colspan="5" class="text-muted">No hay personas cargadas con este criterio.</td></tr>@endforelse</tbody></table>
@endif
</div>
@endsection
