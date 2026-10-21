@extends('emails.layout', ['titulo' => 'Actualización de su solicitud'])
@section('contenido')
<p style="margin:0 0 18px 0;">Su solicitud registra una <strong>actualización</strong>.</p>
@include('emails._tabla', ['filas' => ['Solicitud N°' => $folio, 'Estado' => $estado]])
<p style="margin:0 0 8px 0;">Para ver el detalle y descargar los documentos que se le notificaron, ingrese con ClaveÚnica.</p>
@endsection
@section('boton', 'Ver mi solicitud')
