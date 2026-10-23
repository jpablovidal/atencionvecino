@extends('layouts.app')
@section('titulo', 'Declaración de accesibilidad')
@section('contenido')
<h1>Declaración de accesibilidad</h1>
<p>La Ilustre Municipalidad de Santo Domingo se compromete a que este sistema pueda ser usado por todas las personas, incluidas las personas con discapacidad visual, auditiva, motriz o cognitiva. El diseño sigue las pautas <strong>WCAG 2.2 nivel AA</strong> y el Framework gob.cl.</p>

<h2 class="h4">Estado de conformidad</h2>
<p><strong>Conformidad parcial</strong> con WCAG 2.2 AA. Se verificó con pruebas automáticas (reglas axe y pruebas propias de etiquetas, contraste, encabezados y estructura) y con revisión manual de teclado. <strong>Aún no</strong> se ha realizado una auditoría con personas usuarias de lectores de pantalla ni una certificación externa (A VALIDAR por la Municipalidad).</p>

<h2 class="h4">Qué puede hacer para adaptar la pantalla</h2>
<p>En la barra superior ("Opciones de accesibilidad") puede: aumentar o reducir el tamaño del texto (87% a 150%), activar <strong>alto contraste</strong>, usar una <strong>fuente y espaciado legibles</strong>, <strong>subrayar los enlaces</strong> y <strong>quitar animaciones</strong>. Sus preferencias se guardan solo en su navegador. También respetamos la configuración de su sistema (movimiento reducido y colores forzados).</p>

<h2 class="h4">Uso con teclado</h2>
<table class="table table-sm"><caption class="sr-only">Atajos y teclas</caption><thead><tr><th scope="col">Tecla</th><th scope="col">Acción</th></tr></thead><tbody>
<tr><td><kbd>Tab</kbd> / <kbd>Mayús</kbd>+<kbd>Tab</kbd></td><td>Avanzar / retroceder entre enlaces, botones y campos. El foco siempre es visible.</td></tr>
<tr><td><kbd>Enter</kbd></td><td>Activar enlaces y botones.</td></tr>
<tr><td><kbd>Espacio</kbd></td><td>Marcar casillas y activar botones.</td></tr>
<tr><td><kbd>Flechas</kbd></td><td>Cambiar de opción en grupos de selección y listas desplegables.</td></tr>
<tr><td>Primer <kbd>Tab</kbd> de cada página</td><td>Aparecen los enlaces "Saltar al contenido" y "Saltar a la navegación".</td></tr>
<tr><td><kbd>Esc</kbd></td><td>Cierra paneles abiertos.</td></tr>
<tr><td>Editor de documentos (TinyMCE): <kbd>Alt</kbd>+<kbd>F10</kbd></td><td>Va a la barra de herramientas del editor; <kbd>Esc</kbd> vuelve al texto. <kbd>Ctrl</kbd>+<kbd>B</kbd>, <kbd>I</kbd>, <kbd>U</kbd> aplican negrita, cursiva y subrayado.</td></tr>
</tbody></table>

<h2 class="h4">Lectores de pantalla</h2>
<p>Las páginas usan encabezados jerárquicos, regiones (banner, navegación, contenido principal, pie), etiquetas en todos los campos, tablas con encabezados de columna, mensajes de estado anunciados automáticamente y un resumen de errores con enlaces a cada campo. Se probó la estructura con árbol de accesibilidad del navegador; falta validación con NVDA/JAWS/VoiceOver.</p>

<h2 class="h4">Limitaciones conocidas</h2>
<ul>
<li>Los <strong>PDF generados</strong> (comprobantes y copias públicas) no son PDF etiquetados (PDF/UA) y la hoja de certificado incluye un código QR: el mismo dato se entrega también como texto (código y dirección de validación). Si necesita otro formato accesible, solicítelo al canal indicado abajo.</li>
<li>Los PDF que adjuntan las personas usuarias o que envían otros organismos pueden no ser accesibles.</li>
<li>El <strong>editor de documentos</strong> (TinyMCE) es un componente de terceros con soporte de teclado y lector de pantalla, pero su interfaz puede tener limitaciones.</li>
<li>La autenticación se realiza en el sitio de <strong>ClaveÚnica</strong> (Gobierno Digital), cuya accesibilidad no depende de este sistema.</li>
</ul>

<h2 class="h4">Reportar una barrera o pedir apoyo</h2>
<p>Si encuentra una barrera de accesibilidad o necesita la información en otro formato, escriba a <a href="mailto:{{ $contacto }}">{{ $contacto }}</a> o acuda a la oficina de atención presencial del municipio. Indique la dirección de la página, el problema y su tecnología de apoyo. Responderemos a la brevedad (plazo A VALIDAR por la Municipalidad). Mientras tanto, todos los trámites pueden realizarse de forma presencial.</p>

<p><a href="{{ \App\Support\ManualRenderer::ayudaUrl() }}">{{ auth()->check() ? 'Ver manuales de usuario' : 'Ver el manual del ciudadano' }}</a></p>
@endsection
