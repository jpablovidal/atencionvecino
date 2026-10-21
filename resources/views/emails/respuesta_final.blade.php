@extends('emails.layout', ['titulo' => 'Respuesta a su solicitud'])
@section('contenido')
<p style="margin:0 0 18px 0;">La Municipalidad despachó la <strong>respuesta final</strong> de su solicitud.</p>
@include('emails._tabla', ['filas' => array_filter([
    'Solicitud N°' => $folio,
    'Expediente N°' => $expediente,
    'Documentos de la respuesta' => $cantidad,
], fn ($v) => filled($v))])
<p style="margin:0 0 8px 0;">Los recibirá en su Casilla Única; este correo es la copia del aviso. También puede descargarlos ingresando con ClaveÚnica.</p>
@endsection
@section('boton', 'Ver mi solicitud')
