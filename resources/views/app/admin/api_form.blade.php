@extends('layouts.app')
@section('titulo', 'API: '.$d['etiqueta'])
@section('contenido')
<p><a href="{{ route('app.admin.api') }}">&larr; APIs e integraciones</a></p>
<h1>{{ $d['etiqueta'] }}</h1>
<p>{{ $d['descripcion'] }}</p>
@if($clave==='pisee')
<div class="alert alert-warning" role="note"><strong>Fin de PISEE (Decreto 12, art. 2° transitorio):</strong> {{ \App\Support\NormasTecnicas::avisoPisee() }}</div>
@endif
@php $grupos = \App\Services\Admin\ApiCatalogo::grupos($clave); $activoDef = old('activo', $estado['existe'] ? $estado['activo'] : true); @endphp
<form method="post" action="{{ route('app.admin.api.actualizar',$clave) }}" class="col-lg-10 px-0" autocomplete="off" enctype="multipart/form-data">@csrf
<div class="card mb-3"><div class="card-body">
<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="activo" name="activo" value="1" @checked($activoDef)><label class="custom-control-label" for="activo">Usar esta configuración de la base de datos (si no, se usa <code>.env</code>)</label></div>
@if(! $secretosBd)<p class="small text-muted mb-0 mt-2">El guardado de secretos en la base está desactivado en este servidor (<code>SGD_API_SECRETOS_EN_BD=0</code>): los secretos deben definirse en <code>.env</code>.</p>@else<p class="small text-muted mb-0 mt-2">Los secretos que escriba aquí se guardan <strong>cifrados</strong> en la base de datos y <strong>nunca se vuelven a mostrar</strong>. Déjelos en blanco para conservar el actual.</p>@endif
</div></div>
@foreach($grupos as $gi => $g)
<div class="card mb-3"><div class="card-body">
<h2 class="h5">{{ $g['titulo'] }}</h2>
@if(!empty($g['intro']))<p class="small text-muted">{{ $g['intro'] }}</p>@endif
@foreach($g['campos'] as $k)
@php $def = $d['campos'][$k]; [$etq,$tipo,$path] = $def; $op = $def[3] ?? []; $secreto = $op['secreto'] ?? false; $val = old($k, $estado['valores'][$k] ?? ($secreto ? null : config($path))); @endphp
<div class="form-group">
@if($tipo==='bool')
  <div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="{{ $k }}" name="{{ $k }}" value="1" @checked($val)><label class="custom-control-label" for="{{ $k }}">{{ $etq }}</label></div>
@elseif($secreto)
  <label for="{{ $k }}">{{ $etq }} <span class="estado {{ in_array($k,$estado['secretos_bd']) || $ef[$k]['definido'] ? 'estado-vigente' : '' }}">{{ in_array($k,$estado['secretos_bd']) ? 'guardado, cifrado' : ($ef[$k]['definido'] ? 'definido en .env' : 'sin definir') }}</span></label>
  <input class="form-control" type="password" id="{{ $k }}" name="{{ $k }}" autocomplete="new-password" @disabled(! $secretosBd) aria-describedby="s-{{ $k }}" placeholder="{{ $secretosBd ? 'Dejar en blanco para conservar el actual' : 'Desactivado: defínalo en .env' }}">
  <small id="s-{{ $k }}" class="form-text text-muted">Se guarda cifrado y no se vuelve a mostrar.</small>
  @if(in_array($k,$estado['secretos_bd']))<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="b-{{ $k }}" name="borrar[]" value="{{ $k }}"><label class="custom-control-label" for="b-{{ $k }}">Eliminar el valor guardado en la BD</label></div>@endif
@else
  @if(!empty($op['subida']))
  @php $cuenta = \App\Services\Repositorio\CredencialDriveService::resumen($val ?: config($path)); @endphp
  <label for="archivo_{{ $k }}">Archivo de clave JSON de la cuenta de servicio</label>
  <input type="file" class="form-control-file mb-1" id="archivo_{{ $k }}" name="archivo_{{ $k }}" accept=".json,application/json" aria-describedby="f-{{ $k }}">
  <small id="f-{{ $k }}" class="form-text text-muted mb-2">Suba el archivo JSON descargado de Google Cloud (máx. 20 KB): se valida y se guarda en el servidor fuera del sitio web, con permisos 600, y <strong>la clave nunca se muestra</strong>; al guardar, la ruta de abajo se completa sola. @if($cuenta)<strong>Cuenta cargada:</strong> {{ $cuenta['email'] }} (proyecto {{ $cuenta['proyecto'] }}).@else Aún no hay una clave cargada.@endif</small>
  @endif
  <label for="{{ $k }}">{{ $etq }}</label>
  @if($tipo==='select')<select class="form-control" id="{{ $k }}" name="{{ $k }}" @if(!empty($op['ayuda'])) aria-describedby="a-{{ $k }}" @endif>@foreach($op['opciones'] as $v=>$l)<option value="{{ $v }}" @selected($val===$v)>{{ $l }}</option>@endforeach</select>
  @else<input class="form-control" type="{{ $tipo==='number' ? 'number' : 'text' }}" id="{{ $k }}" name="{{ $k }}" value="{{ $val }}" maxlength="500" @if(!empty($op['ayuda'])) aria-describedby="a-{{ $k }}" @endif>@endif
  @if(!empty($op['ayuda']))<small id="a-{{ $k }}" class="form-text text-muted">{{ $op['ayuda'] }}</small>@endif
@endif
</div>
@endforeach
<button class="btn btn-primary">{{ $g['boton'] ?? 'Guardar' }}</button>
</div></div>
@endforeach
</form>

<div class="card mb-3"><div class="card-body">
<h2 class="h5">Verificar</h2>
@if($clave==='docdigital')<p class="small text-muted">Hace la consulta de solo lectura de «Probar conexión» (token, entidad y tipos de documento) con el modo guardado; no envía ni descarga documentos.</p>
@elseif($clave==='claveunica')<p class="small text-muted">Revisa los datos del ambiente activo y consulta a ClaveÚnica si acepta el client_id y el client_secret (sin iniciar sesión de ninguna persona).</p>
@elseif($clave==='firmagob')<p class="small text-muted">Revisa que el ambiente activo tenga sus credenciales, la entidad y el propósito. Para confirmar con FirmaGob use «Probar la firma» (más abajo).</p>
@elseif($clave==='google_mail')<p class="small text-muted">Revisa la cuenta de servicio y pide un token a Google con la delegación de dominio (sin enviar correo); luego puede enviar un correo de prueba.</p>@endif
<div class="d-flex flex-wrap">
<form method="post" action="{{ route('app.admin.api.probar',$clave) }}" class="mr-2 mb-2">@csrf<button class="btn btn-outline-primary">{{ $clave==='docdigital' ? 'Probar conexión' : 'Validar credenciales' }}</button></form>
@if($estado['activo'])<form method="post" action="{{ route('app.admin.api.env',$clave) }}" class="mb-2" data-confirm="¿Volver a usar la configuración del archivo .env?">@csrf<button class="btn btn-outline-secondary">Volver a .env</button></form>@endif
</div>
@if($pruebas)<h3 class="h6 mt-3">Resultado</h3>
<table class="table table-sm"><caption class="sr-only">Validación</caption><thead><tr><th scope="col">Punto</th><th scope="col">Resultado</th><th scope="col">Detalle</th></tr></thead><tbody>
@foreach($pruebas as [$n,$ok,$det])<tr><td>{{ $n }}</td><td><span class="estado {{ $ok ? 'estado-vigente' : 'estado-fallida' }}">{{ $ok ? 'OK' : 'REVISAR' }}</span></td><td>{{ $det }}</td></tr>@endforeach</tbody></table>@endif
</div></div>

@if($clave==='firmagob')
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Probar la firma</h2>
<p class="small text-muted">Firma un PDF de ejemplo con la configuración guardada y confirma que FirmaGob responde. No queda guardado. Ingrese el RUN del titular de la firma y el código OTP vigente de su aplicación de FirmaGob. <strong>Ambiente vigente: {{ config('sgd.firmagob.mode') }}</strong>@if(config('sgd.firmagob.mode')==='prod') (se usa el certificado real del titular)@endif.</p>
<form method="post" action="{{ route('app.admin.api.firma_prueba') }}">@csrf
<div class="form-row"><div class="form-group col-md-4"><label for="fp-run">RUN del titular</label><input class="form-control" id="fp-run" name="run" value="{{ old('run', auth()->user()->run) }}" maxlength="14" required></div>
<div class="form-group col-md-3"><label for="fp-otp">OTP (6 dígitos)</label><input class="form-control" id="fp-otp" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required></div></div>
<div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="fp-ok" name="entiendo" value="1" required><label class="custom-control-label" for="fp-ok">Entiendo que se firmará un documento de prueba@if(config('sgd.firmagob.mode')==='prod') con el certificado real@endif.</label></div>
<button class="btn btn-primary">Probar firma (ambiente {{ config('sgd.firmagob.mode') }})</button></form>
</div></div>
@endif

@if($clave==='directorio')
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Importar direcciones y funcionarios</h2>
<p class="small text-muted">Lee la tabla del directorio (solo identificación, cargo, correo, dirección, departamento y unidad; nunca contraseñas) y: crea las <strong>direcciones, departamentos y unidades</strong> que falten; actualiza el <strong>cargo, correo y ubicación</strong> de los funcionarios que ya tienen cuenta desde el directorio. Es seguro repetirlo.</p>
<form method="post" action="{{ route('app.admin.api.directorio_sincronizar') }}" data-confirm="¿Importar ahora desde el directorio?">@csrf
<div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="dir-crear" name="crear_usuarios" value="1"><label class="custom-control-label" for="dir-crear">Crear también la cuenta de los funcionarios vigentes que aún no la tienen (rol funcionario; entran con ClaveÚnica)</label></div>
<button class="btn btn-primary mb-4">Importar ahora</button></form>
<h2 class="h5">Probar con un RUN</h2>
<p class="small text-muted">Indica por qué un RUN se reconoce (o no) como funcionario(a): conexión, si figura en la tabla, dígito verificador y vigencia. No muestra datos personales. Si el RUT no figura, la persona entra solo como vecino(a).</p>
<form method="post" action="{{ route('app.admin.api.directorio_prueba') }}" class="form-inline">@csrf
<label class="sr-only" for="dir-run">RUN a consultar</label><input class="form-control mr-2 mb-2" id="dir-run" name="run" placeholder="12345678-9" maxlength="14" required value="{{ auth()->user()->run }}">
<button class="btn btn-primary mb-2">Consultar</button></form>
</div></div>
@endif

@if($clave==='google_mail')
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Enviar correo de prueba</h2>
<p class="small text-muted">Envía un correo de prueba con la configuración guardada y muestra si salió bien o cuál fue el problema. Método vigente: <strong>{{ config('mail.default') }}</strong>.</p>
<form method="post" action="{{ route('app.admin.api.correo_prueba') }}" class="form-inline">@csrf
<label class="sr-only" for="destino">Correo de destino</label><input class="form-control mr-2 mb-2" type="email" id="destino" name="destino" placeholder="destino@ejemplo.cl" required maxlength="190" value="{{ auth()->user()->correo }}">
<button class="btn btn-primary mb-2">Enviar correo de prueba</button></form>
</div></div>
@endif

@if($clave==='claveunica')
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Referencia rápida (manual de integración)</h2>
<table class="table table-sm"><caption class="sr-only">Referencia de ClaveÚnica</caption><tbody>
<tr><th scope="row">Endpoint de autorización</th><td><code>{{ config('sgd.claveunica.authorize_url') }}</code></td></tr>
<tr><th scope="row">Endpoint de token</th><td><code>{{ config('sgd.claveunica.token_url') }}</code></td></tr>
<tr><th scope="row">Endpoint de datos de usuario</th><td><code>{{ config('sgd.claveunica.userinfo_url') }}</code></td></tr>
<tr><th scope="row">Endpoint de logout</th><td><code>{{ config('sgd.claveunica.logout_url') }}</code></td></tr>
<tr><th scope="row">Scope</th><td><code>{{ config('sgd.claveunica.scope') }}</code></td></tr>
<tr><th scope="row">RUN de prueba (sandbox/QA)</th><td><code>44.444.444-4 / 55.555.555-5 / 88.888.888-8 / 99.999.999-9</code>, clave <code>testing</code></td></tr>
</tbody></table>
<p class="small text-muted mb-0">Detalle del botón y del flujo en <code>docs/CLAVEUNICA_BOTON.md</code>.</p>
</div></div>
@endif
@endsection
