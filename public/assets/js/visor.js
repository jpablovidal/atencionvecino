/* Visor de archivos del expediente (sin scripts en línea). Los enlaces con [data-visor] se abren en un modal; PDF, imágenes y texto
   se muestran dentro de la página; los demás formatos se descargan. Sin JavaScript el enlace abre el archivo en la misma pestaña. */
(function () {
  'use strict';
  var modal = document.getElementById('sgd-visor');
  if (!modal || !window.jQuery || !window.jQuery.fn.modal) { return; }
  var $ = window.jQuery;
  var cuerpo = document.getElementById('sgd-visor-cuerpo');
  var titulo = document.getElementById('sgd-visor-titulo');
  var nueva = document.getElementById('sgd-visor-nueva');
  var desc = document.getElementById('sgd-visor-descarga');
  var INLINE = ['application/pdf', 'image/jpeg', 'image/png', 'text/plain', 'text/csv'];
  var previo = null;
  function limpiar() { while (cuerpo.firstChild) { cuerpo.removeChild(cuerpo.firstChild); } }
  function abrir(a) {
    var url = a.getAttribute('href');
    var mime = a.getAttribute('data-visor-mime') || '';
    var nombre = a.getAttribute('data-visor-titulo') || 'Archivo';
    limpiar();
    titulo.textContent = nombre;
    nueva.setAttribute('href', url);
    desc.setAttribute('href', a.getAttribute('data-visor-descarga') || url);
    if (mime.indexOf('image/') === 0) {
      var img = document.createElement('img');
      img.src = url; img.alt = 'Vista del archivo ' + nombre; img.className = 'img-fluid d-block mx-auto';
      cuerpo.appendChild(img);
    } else if (INLINE.indexOf(mime) !== -1) {
      var f = document.createElement('iframe');
      f.src = url; f.title = 'Contenido de ' + nombre; f.style.cssText = 'width:100%;height:70vh;border:0';
      f.setAttribute('referrerpolicy', 'same-origin');
      cuerpo.appendChild(f);
    } else {
      var p = document.createElement('p');
      p.className = 'p-4 mb-0';
      p.textContent = 'Este formato no se puede mostrar en el navegador. Use «Descargar» para abrirlo en su equipo.';
      cuerpo.appendChild(p);
      nueva.setAttribute('hidden', '');
    }
    if (mime.indexOf('image/') === 0 || INLINE.indexOf(mime) !== -1) { nueva.removeAttribute('hidden'); }
    previo = a;
    modal.removeAttribute('hidden');
    $(modal).modal({ backdrop: true, keyboard: true, focus: true });
  }
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a[data-visor]') : null;
    if (!a || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button === 1) { return; } /* clic con modificador = comportamiento normal del navegador */
    ev.preventDefault();
    abrir(a);
  });
  $(modal).on('hidden.bs.modal', function () {
    limpiar();
    modal.setAttribute('hidden', '');
    if (previo && previo.focus) { previo.focus(); }
  });
})();
