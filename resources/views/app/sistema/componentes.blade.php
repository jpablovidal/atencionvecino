@extends('layouts.app')
@section('titulo', 'Componentes y librerías')
@section('contenido')
<div class="d-flex justify-content-between flex-wrap"><h1>Componentes y librerías</h1><a class="btn btn-outline-primary align-self-center" href="{{ route('app.sistema.componentes.json') }}">Descargar inventario (JSON)</a></div>
<p class="text-muted">Inventario generado en este momento desde los archivos instalados en el servidor. No consulta Internet: para vulnerabilidades conocidas use <code>composer audit</code> y el SBOM del proyecto @if($sbom) (<code>docs/sbom.cdx.json</code>)@endif.</p>

<h2 class="h4">Recursos externos (CDN)</h2>
@if(count($ext['cargados']) === 0)
<div class="alert alert-success" role="status"><strong>Sin CDN ni recursos externos.</strong> Se revisaron {{ $ext['archivos_revisados'] }} archivos (vistas, hojas de estilo y scripts): ninguno carga scripts, estilos, fuentes ni imágenes desde otros sitios.</div>
@else
<div class="alert alert-danger" role="alert"><strong>Se detectaron {{ count($ext['cargados']) }} recursos cargados desde otros orígenes:</strong>
<ul class="mb-0">@foreach($ext['cargados'] as $c)<li><code>{{ $c['archivo'] }}</code> → {{ $c['url'] }}</li>@endforeach</ul></div>
@endif
<p class="small">El navegador además lo impide: política de contenido (CSP) vigente:</p>
<pre class="small p-2 border bg-light" tabindex="0" aria-label="Política de seguridad de contenido" style="white-space:pre-wrap">{{ $ext['csp'] }}</pre>
@if($ext['enlaces'])
<details class="mb-3"><summary>Hipervínculos externos de navegación ({{ count($ext['enlaces']) }}) — no cargan recursos</summary>
<ul class="small mt-2">@foreach($ext['enlaces'] as $c)<li><code>{{ $c['archivo'] }}</code> → {{ $c['url'] }}</li>@endforeach</ul></details>
@endif
<details class="mb-4"><summary>Servicios externos a los que se conecta el <em>servidor</em> (integraciones, no CDN)</summary>
<table class="table table-sm mt-2"><caption class="sr-only">Integraciones del servidor</caption><thead><tr><th scope="col">Integración</th><th scope="col">Destino configurado</th></tr></thead><tbody>@foreach($integraciones as $n => $h)<tr><td>{{ $n }}</td><td>{{ $h }}</td></tr>@endforeach</tbody></table></details>

<h2 class="h4">Plataforma</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Plataforma</caption><thead><tr><th scope="col">Componente</th><th scope="col">Versión</th></tr></thead><tbody>
<tr><th scope="row">PHP ({{ $plat['sapi'] }})</th><td>{{ $plat['php'] }} · Zend {{ $plat['zend'] }}</td></tr>
<tr><th scope="row">Laravel</th><td>{{ $plat['laravel'] }} ({{ $plat['entorno'] }})</td></tr>
<tr><th scope="row">Base de datos ({{ $plat['bd']['driver'] }})</th><td>{{ $plat['bd']['version'] ?: '—' }}</td></tr>
<tr><th scope="row">Sistema operativo</th><td>{{ $plat['so'] }}</td></tr>
@if($plat['servidor_web'])<tr><th scope="row">Servidor web</th><td>{{ $plat['servidor_web'] }}</td></tr>@endif
@foreach($plat['bibliotecas'] as $n => $v)<tr><th scope="row">{{ $n }}</th><td>{{ $v }}</td></tr>@endforeach
</tbody></table></div>
<details class="mb-4"><summary>Extensiones de PHP cargadas ({{ count($plat['extensiones']) }})</summary>
<table class="table table-sm mt-2"><caption class="sr-only">Extensiones de PHP</caption><thead><tr><th scope="col">Extensión</th><th scope="col">Versión</th></tr></thead><tbody>@foreach($plat['extensiones'] as $e)<tr><td>{{ $e['nombre'] }}</td><td>{{ $e['version'] ?? '—' }}</td></tr>@endforeach</tbody></table></details>

<h2 class="h4">Front-end (todo autoalojado)</h2>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Activos front-end</caption><thead><tr><th scope="col">Componente</th><th scope="col">Versión</th><th scope="col">Origen</th><th scope="col">Licencia</th><th scope="col">Archivo</th><th scope="col">Tamaño</th><th scope="col">SHA-256</th></tr></thead><tbody>
@foreach($front as $f)<tr><th scope="row">{{ $f['nombre'] }}<br><small class="text-muted font-weight-normal">{{ $f['uso'] }}</small></th><td>{{ $f['version'] ?? '—' }}</td><td>{{ $f['paquete'] }} <span class="badge badge-secondary">{{ $f['origen'] }}</span></td><td>{{ $f['licencia'] }}</td><td><code>{{ $f['archivo'] }}</code>@unless($f['presente']) <strong class="text-danger">(falta)</strong>@endunless</td><td>{{ \App\Services\Sistema\RecursosService::humano($f['tamano']) }}</td><td><small class="hash">{{ $f['sha256'] ? substr($f['sha256'], 0, 16).'…' : '—' }}</small></td></tr>@endforeach
</tbody></table></div>
<p class="small">Plugins de TinyMCE instalados: {{ implode(', ', $plugins) }}.</p>

<h2 class="h4">Librerías PHP (Composer)</h2>
<form method="get" class="form-inline mb-2" aria-label="Buscar librerías"><label class="mr-2" for="q">Buscar</label><input class="form-control mr-2" id="q" name="q" maxlength="60" value="{{ $filtro['q'] }}" placeholder="nombre o licencia"><button class="btn btn-outline-primary">Filtrar</button></form>
<p role="status">{{ count($paquetes) }} {{ count($paquetes) === 1 ? 'paquete' : 'paquetes' }}@if(collect($paquetes)->where('dev', true)->count()) · {{ collect($paquetes)->where('dev', true)->count() }} solo de desarrollo @endif.</p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Librerías PHP</caption><thead><tr><th scope="col">Paquete</th><th scope="col">Versión</th><th scope="col">Licencia</th><th scope="col">Tipo</th><th scope="col">Fecha</th></tr></thead><tbody>
@forelse($paquetes as $p)<tr><th scope="row">{{ $p['nombre'] }}@if($p['dev']) <span class="badge badge-secondary">desarrollo</span>@endif<br><small class="text-muted font-weight-normal">{{ $p['descripcion'] }}</small></th><td>{{ $p['version'] }}</td><td>{{ $p['licencia'] }}</td><td>{{ $p['tipo'] }}</td><td>{{ $p['fecha'] ?? '—' }}</td></tr>
@empty<tr><td colspan="5">Sin resultados.</td></tr>@endforelse</tbody></table></div>
@endsection
