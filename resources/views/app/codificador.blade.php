@extends('layouts.app')
@section('titulo', 'Codificador')
@section('contenido')
<h1>Codificador de instituciones y procesos</h1>
<p>Catálogo local para identificar instituciones y procesos del Estado (destinatarios de DocDigital, proveedores PISEE y procesos de los trámites).
Código de esta institución: <strong>{{ $miCodigo ?: 'no configurado (CODIFICADOR_INSTITUCION_CODIGO)' }}</strong> · modo: <strong>{{ $modo }}</strong> · {{ $inst }} instituciones · {{ $proc }} procesos.</p>
@if($resultado)<div class="alert alert-info">Creados: {{ $resultado['creados'] }} · actualizados: {{ $resultado['actualizados'] }}@if($resultado['errores'])<ul class="mb-0">@foreach(array_slice($resultado['errores'],0,20) as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</div>@endif
<form method="post" action="{{ route('app.admin.codificador.importar') }}" enctype="multipart/form-data" class="card card-body col-lg-8 mb-3">@csrf
<h2 class="h5">Importar CSV (UTF-8, con cabecera)</h2>
<p class="small text-muted">Instituciones: <code>codigo,nombre,tipo,codigo_padre</code> · Procesos: <code>codigo,nombre,institucion_codigo</code>. Los códigos existentes se actualizan.</p>
<div class="form-row"><div class="form-group col-md-4"><label for="entidad">Catálogo</label><select class="form-control" id="entidad" name="entidad"><option value="instituciones">Instituciones</option><option value="procesos">Procesos</option></select></div>
<div class="form-group col-md-8"><label for="archivo">Archivo CSV</label><input class="form-control-file" type="file" id="archivo" name="archivo" accept=".csv,text/csv" required></div></div>
<div><button class="btn btn-primary">Importar</button></div></form>
<form method="post" action="{{ route('app.admin.codificador.sincronizar') }}" class="mb-3">@csrf<button class="btn btn-outline-primary" @disabled($modo!=='api')>Sincronizar por API ahora</button> @if($modo!=='api')<small class="text-muted">Requiere CODIFICADOR_MODE=api</small>@endif</form>
<p><a href="{{ route('app.admin.index','instituciones') }}">Ver/editar instituciones</a> · <a href="{{ route('app.admin.index','procesos') }}">Ver/editar procesos</a></p>
@endsection
