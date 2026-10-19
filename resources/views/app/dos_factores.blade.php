@extends('layouts.app')
@section('titulo', 'Segundo factor de autenticación')
@section('contenido')
<h1>Segundo factor de autenticación</h1>
@if($modo === 'verificar')
<p class="lead-sub">Por seguridad, los administradores del sistema deben confirmar su identidad con el código de su aplicación autenticadora.</p>
<form method="post" action="{{ route('app.2fa.verificar') }}" class="card card-body" style="max-width:28rem">@csrf
 <div class="form-group"><label for="cod">Código de 6 dígitos (o código de respaldo de 8 caracteres)</label>
 <input class="form-control" id="cod" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus></div>
 <button class="btn btn-primary">Verificar</button></form>
@elseif($modo === 'inscribir')
<p class="lead-sub">Su cuenta de administrador exige doble factor. Escanee el código QR con Google Authenticator, Microsoft Authenticator, FreeOTP u otra aplicación TOTP, e ingrese el código que muestre.</p>
<div class="row"><div class="col-md-5 mb-3"><img src="{{ $qr }}" alt="Código QR para configurar la aplicación autenticadora" width="220" height="220"></div>
<div class="col-md-7"><p>Si no puede escanear, ingrese esta clave manualmente (tipo de clave: basada en tiempo):</p><p><code class="h5" id="secreto">{{ implode(' ', str_split($secreto, 4)) }}</code></p>
<form method="post" action="{{ route('app.2fa.verificar') }}">@csrf
 <div class="form-group"><label for="cod">Código de 6 dígitos</label><input class="form-control" id="cod" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required style="max-width:12rem"></div>
 <button class="btn btn-primary">Activar segundo factor</button></form></div></div>
@else
<div class="alert alert-success" role="status">Segundo factor activado.</div>
<p>Guarde estos <strong>códigos de respaldo</strong> en un lugar seguro. Cada uno sirve <strong>una sola vez</strong> si pierde su teléfono. No se volverán a mostrar.</p>
<ul class="list-unstyled h5">@foreach($respaldo as $c)<li><code>{{ $c }}</code></li>@endforeach</ul>
<a class="btn btn-primary" href="{{ route('app.bandeja') }}">Continuar</a>
@endif
@endsection
