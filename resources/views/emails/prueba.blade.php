@extends('emails.layout', ['titulo' => 'Prueba de correo', 'nota' => 'Mensaje de prueba del Sistema de Gestión Documental.'])
@section('contenido')
<p style="margin:0 0 18px 0;">Este es un <strong>correo de prueba</strong> del Sistema de Gestión Documental de la I. Municipalidad de Santo Domingo.</p>
<p style="margin:0 0 18px 0;">Si lo recibe, el envío por correo funciona correctamente.</p>
@endsection
