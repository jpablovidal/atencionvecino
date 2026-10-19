@extends('layouts.app')
@section('titulo', 'Repositorio de documentos')
@section('contenido')
<h1>Repositorio de documentos</h1>
<p>Los archivos (anexos, comprobantes, PDF de documentos) se <strong>cifran en este servidor antes de salir</strong> y se guardan con nombres aleatorios. Puede almacenarlos en el disco del servidor o en una <strong>unidad compartida de Google Drive</strong>: Google solo ve texto cifrado.</p>

<div class="row">
<div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body"><h2 class="h6">Modo actual</h2><p class="h4 mb-0">{{ $stats['modo'] === 'drive' ? 'Google Drive' : 'Disco local' }}</p></div></div></div>
<div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body"><h2 class="h6">En Google Drive</h2><p class="h4 mb-0">{{ $stats['drive_n'] }} archivos</p><small>{{ number_format($stats['drive_bytes']/1048576,1,',','.') }} MB (sin cifrar) · {{ $stats['reubicadas'] }} reubicados</small></div></div></div>
<div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body"><h2 class="h6">En disco local</h2><p class="h4 mb-0">{{ $stats['local_n'] }} archivos</p><small>{{ number_format($stats['local_bytes']/1048576,1,',','.') }} MB · <strong>{{ $stats['pendientes'] }}</strong> pendientes de migrar</small></div></div></div>
</div>
<p>Última prueba de conexión:
@if($ultima['fecha'])<span class="estado {{ $ultima['ok'] ? 'estado-vigente' : 'estado-fallida' }}">{{ $ultima['ok'] ? 'exitosa' : 'fallida' }}</span> {{ \Illuminate\Support\Carbon::parse($ultima['fecha'])->timezone('America/Santiago')->format('d-m-Y H:i') }}@else<span class="estado">nunca</span>@endif</p>

<h2 class="h4 mt-4">Configuración</h2>
<form method="post" action="{{ route('app.admin.repositorio.guardar') }}" class="col-lg-9 px-0" enctype="multipart/form-data">@csrf
<div class="form-group"><label for="modo">Dónde se guardan los archivos nuevos</label><select class="form-control col-md-6" id="modo" name="modo"><option value="local" @selected(old('modo',$cfg['modo'])==='local')>Disco del servidor (fuera del docroot)</option><option value="drive" @selected(old('modo',$cfg['modo'])==='drive')>Unidad compartida de Google Drive</option></select></div>
<div class="form-group"><label for="drive_sa_archivo">Cargar la clave JSON de la cuenta de servicio de Drive</label><input type="file" class="form-control-file" id="drive_sa_archivo" name="drive_sa_archivo" accept=".json,application/json" aria-describedby="h-saf">
  <small id="h-saf" class="form-text text-muted">Suba el archivo que descargó de Google Cloud (máx. 20 KB). Se valida, se guarda en el servidor fuera del sitio web con permisos 600 y <strong>la clave nunca se muestra</strong>. Al guardar, la ruta se completa sola.@if($cuenta) <br><strong>Cuenta cargada:</strong> {{ $cuenta['email'] }} (proyecto {{ $cuenta['proyecto'] }}, {{ \Illuminate\Support\Carbon::createFromTimestamp($cuenta['fecha'])->timezone('America/Santiago')->format('d-m-Y H:i') }}).@else <br>Aún no hay una clave cargada.@endif</small></div>
<div class="form-group"><label for="drive_sa_json">Archivo JSON de la cuenta de servicio de Drive</label><input class="form-control" id="drive_sa_json" name="drive_sa_json" value="{{ old('drive_sa_json',$cfg['sa']) }}" aria-describedby="h-sa" maxlength="300"><small id="h-sa" class="form-text text-muted">Ruta absoluta fuera del docroot, permisos 600. La clave privada no se pega en este sistema.</small></div>
<div class="form-row"><div class="form-group col-md-6"><label for="drive_shared_drive_id">ID de la unidad compartida</label><input class="form-control" id="drive_shared_drive_id" name="drive_shared_drive_id" value="{{ old('drive_shared_drive_id',$cfg['drive']) }}" maxlength="100"></div>
<div class="form-group col-md-6"><label for="drive_root_folder_id">ID de la carpeta raíz del repositorio</label><input class="form-control" id="drive_root_folder_id" name="drive_root_folder_id" value="{{ old('drive_root_folder_id',$cfg['raiz']) }}" maxlength="100"></div></div>
<button class="btn btn-primary">Guardar configuración</button></form>

<h2 class="h4 mt-4">Operaciones</h2>
<div class="d-flex flex-wrap">
<form method="post" action="{{ route('app.admin.repositorio.probar') }}" class="mr-2 mb-2">@csrf<button class="btn btn-outline-primary">Probar conexión con Drive</button></form>
<form method="post" action="{{ route('app.admin.repositorio.verificar') }}" class="mr-2 mb-2">@csrf<button class="btn btn-outline-primary">Verificar integridad (muestra de 10)</button></form>
<form method="post" action="{{ route('app.admin.repositorio.migrar') }}" class="mb-2" data-confirm="¿Mover a Google Drive los archivos que están en el disco local? Se hace en lotes y se verifica cada archivo antes de borrar la copia local.">@csrf<button class="btn btn-outline-primary" @disabled($stats['pendientes']===0)>Migrar archivos locales a Drive ({{ $stats['pendientes'] }})</button></form>
</div>
@if($pasos)<h3 class="h5 mt-3">Resultado de la prueba</h3>
<table class="table table-sm"><caption class="sr-only">Pasos de la prueba</caption><thead><tr><th scope="col">Paso</th><th scope="col">Resultado</th><th scope="col">Detalle</th></tr></thead><tbody>
@foreach($pasos as [$n,$ok,$det])<tr><td>{{ $n }}</td><td><span class="estado {{ $ok ? 'estado-vigente' : 'estado-fallida' }}">{{ $ok ? 'OK' : 'ERROR' }}</span></td><td>{{ $det }}</td></tr>@endforeach</tbody></table>@endif
@if($verif)<div class="alert alert-{{ $verif['fallos'] ? 'danger' : 'success' }}" role="status">Revisados {{ $verif['revisados'] }} · correctos {{ $verif['ok'] }} · fallos {{ count($verif['fallos']) }}@if($verif['fallos']): {{ implode(', ',$verif['fallos']) }}@endif</div>@endif

<h2 class="h4 mt-4">Cómo preparar la unidad compartida</h2>
<ol>
<li>En Google Cloud cree una <strong>cuenta de servicio</strong> exclusiva para Drive (distinta de la del correo), habilite la <strong>Google Drive API</strong> y descargue su clave JSON a <code>/home/USUARIO/sgd_secrets/drive-sa.json</code> (permisos 600).</li>
<li>En Google Drive, abra la <strong>unidad compartida</strong> → <em>Administrar miembros</em> → agregue el correo de la cuenta de servicio como <strong>Gestor de contenido</strong>. Solo esa unidad queda accesible para la cuenta.</li>
<li>Cree una carpeta (p. ej. «Repositorio SGD») dentro de la unidad y copie los <strong>ID</strong> de la unidad y de la carpeta desde la URL.</li>
<li>Complete este formulario, <strong>pruebe la conexión</strong>, cambie el modo a Google Drive y, si ya hay archivos, presione <strong>Migrar</strong>.</li>
</ol>
@endsection
