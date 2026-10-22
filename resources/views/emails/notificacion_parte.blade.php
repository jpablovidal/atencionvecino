@extends('emails.layout', ['titulo' => 'Notificación de un documento'])
@section('contenido')
<p style="margin:0 0 18px 0;">Estimado(a) {{ $nombre }}:</p>
<p style="margin:0 0 18px 0;">La I. Municipalidad de Santo Domingo le <strong>notifica</strong> el siguiente documento, emitido en un procedimiento administrativo en que usted figura como parte.</p>
@include('emails._tabla', ['filas' => ['Documento' => $documento]])
@if($copia)<p style="margin:0 0 8px 0;">Puede descargar una copia en: <a href="{{ $copia }}">{{ $copia }}</a></p>@endif
@if($enlace)<p style="margin:0 0 8px 0;">Para verificar la autenticidad del documento ingrese a: <a href="{{ $enlace }}">{{ $enlace }}</a></p>@endif
<p style="margin:0 0 8px 0;">Si el documento no es público, solicítelo en la oficina de partes de la Municipalidad.</p>
@endsection
@section('boton', 'Verificar documento')
