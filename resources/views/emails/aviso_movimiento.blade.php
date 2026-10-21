@extends('emails.layout', ['titulo' => 'Novedad en su solicitud'])
@section('contenido')
<p style="margin:0 0 18px 0;">Su solicitud tiene una <strong>novedad</strong>.</p>
@include('emails._tabla', ['filas' => array_filter([
    'Solicitud N°' => $folio,
    'Expediente N°' => $expediente,
    'Fecha' => $fecha,
    'Dirección responsable' => $direccion,
    'Movimiento' => $descripcion,
], fn ($v) => filled($v))])
<p style="margin:0 0 8px 0;">Para ver el historial completo y, cuando corresponda, descargar los documentos de la respuesta, ingrese con ClaveÚnica.</p>
@endsection
@section('boton', 'Ver mi solicitud')
