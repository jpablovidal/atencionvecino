@extends('layouts.app')
@section('titulo', 'Privacidad y protección de datos personales')
@section('contenido')
<nav aria-label="Usted está en" class="portal-migas"><span class="portal-migas-et">Usted está en:</span><ol><li><a href="{{ route('inicio') }}">Inicio</a></li><li aria-current="page">Privacidad y datos personales</li></ol></nav>
<h1 class="h3">Privacidad y protección de datos personales</h1>
<p>Resumen de la <strong>Política de Privacidad y Protección de Datos Personales de la I. Municipalidad de Santo Domingo</strong>, aprobada por el <strong>Decreto Alcaldicio N° 2590 del 31 de agosto de 2026</strong>, conforme a la Ley N° 19.628 modificada por la Ley N° 21.719. El texto oficial es el del decreto: <a href="{{ asset('assets/docs/decreto-2590-politica-privacidad.pdf') }}" target="_blank" rel="noopener noreferrer">ver el Decreto N° 2590 (PDF)<span class="sr-only"> (se abre en otra pestaña)</span></a>.</p>

<h2 class="h5 mt-4">Qué datos se tratan y para qué</h2>
<p>Al ingresar una solicitud se tratan su RUN, nombres y apellidos (obtenidos de ClaveÚnica), su correo electrónico, el detalle de su solicitud y los documentos que adjunte. Se usan <strong>exclusivamente para tramitar esa solicitud</strong> y responderle por el medio que usted elija. Sus datos no serán cedidos a terceros con fines de publicidad.</p>

<h2 class="h5 mt-4">Principios que aplica la Municipalidad</h2>
<ul>
<li><strong>Licitud y lealtad:</strong> se tratan datos dentro de las funciones legales del municipio.</li>
<li><strong>Finalidad:</strong> solo para los fines informados; no se usan para propósitos distintos.</li>
<li><strong>Proporcionalidad:</strong> solo los datos necesarios para el trámite.</li>
<li><strong>Calidad, seguridad y confidencialidad:</strong> datos exactos, protegidos contra accesos no autorizados y con reserva de quienes los conocen.</li>
<li><strong>Privacidad desde el diseño y por defecto.</strong></li>
</ul>

<h2 class="h5 mt-4">Sus derechos</h2>
<p>Usted puede ejercer, de forma expedita y gratuita, sus derechos de <strong>acceso, rectificación, supresión (cancelación), oposición y portabilidad</strong> en la plataforma www.santodomingo.cl o en la oficina de partes de la Municipalidad.</p>

<h2 class="h5 mt-4">Consentimiento</h2>
<p>Por regla general el municipio no requiere consentimiento cuando trata datos para cumplir sus funciones legales. En este formulario, no obstante, se le pide que indique expresamente si consiente el tratamiento de sus datos para tramitar la solicitud. Si no consiente, la solicitud no puede ingresarse por este medio y puede presentarla en la oficina de partes.</p>

<h2 class="h5 mt-4">Responsable</h2>
<p>Oficial de Protección de Datos (DPO) de la I. Municipalidad de Santo Domingo, designado por el Decreto Alcaldicio N° 2220 de 2026. Av. Santa Teresa de Los Andes N° 1, Santo Domingo.</p>
@endsection
