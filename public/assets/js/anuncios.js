/* Modal de anuncios al cargar el sitio (sin scripts en línea). Muestra los anuncios vigentes en un solo modal, una vez por visita;
   «No volver a mostrar» los recuerda en este navegador. Sin almacenamiento disponible, se muestra igualmente. */
(function () {
  'use strict';
  var fuente = document.getElementById('sgd-anuncios');
  var modal = document.getElementById('sgd-anuncio-modal');
  if (!fuente || !modal || !window.jQuery || !window.jQuery.fn.modal) { return; }
  var $ = window.jQuery;
  function leer(almacen, k) { try { return window[almacen].getItem(k); } catch (e) { return null; } }
  function guardar(almacen, k, v) { try { window[almacen].setItem(k, v); } catch (e) { /* sin almacenamiento */ } }
  var cola = [];
  Array.prototype.forEach.call(fuente.querySelectorAll('[data-anuncio]'), function (s) {
    var clave = 'sgd-anuncio-' + s.getAttribute('data-anuncio') + '-' + s.getAttribute('data-version');
    if (leer('localStorage', clave) === 'no' || leer('sessionStorage', clave) === 'visto') { return; }
    cola.push({ nodo: s, clave: clave });
  });
  if (!cola.length) { return; }
  var previo = null;
  var titulo = document.getElementById('sgd-anuncio-titulo');
  var cuerpo = document.getElementById('sgd-anuncio-cuerpo');
  var no = document.getElementById('sgd-anuncio-no');
  /* Un solo modal: con varios anuncios vigentes se apilan dentro de la misma ventana. */
  function mostrar() {
    cuerpo.textContent = '';
    titulo.textContent = cola.length === 1 ? cola[0].nodo.querySelector('h2').textContent : 'Avisos del municipio';
    cola.forEach(function (it) {
      var h = it.nodo.querySelector('h2');
      var caja = document.createElement('section');
      caja.className = 'sgd-anuncio-item';
      if (cola.length > 1) { var t = document.createElement('h3'); t.className = 'h5'; t.textContent = h.textContent; caja.appendChild(t); }
      Array.prototype.forEach.call(it.nodo.children, function (c) { if (c !== h) { caja.appendChild(c.cloneNode(true)); } });
      cuerpo.appendChild(caja);
    });
    no.checked = false;
    previo = document.activeElement;
    modal.removeAttribute('hidden');
    $(modal).modal({ backdrop: true, keyboard: true, focus: true });
  }
  $(modal).on('hidden.bs.modal', function () {
    cola.forEach(function (it) {
      guardar('sessionStorage', it.clave, 'visto');
      if (no.checked) { guardar('localStorage', it.clave, 'no'); }
    });
    Array.prototype.forEach.call(cuerpo.querySelectorAll('video'), function (v) { v.pause(); });
    modal.setAttribute('hidden', '');
    if (previo && previo.focus) { previo.focus(); }
  });
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', mostrar); } else { mostrar(); }
})();
