/* Listas dinámicas del formulario de plantillas (vistos, considerando, firmas, cadena): agregar y quitar filas. Sin scripts en línea (CSP). */
(function () {
  'use strict';
  document.querySelectorAll('[data-lista]').forEach(function (cont) {
    var filas = cont.querySelector('.lista-filas'); var tpl = cont.querySelector('template[data-lista-plantilla]');
    var max = parseInt(cont.getAttribute('data-max') || '30', 10); var n = filas.querySelectorAll('.lista-fila').length + 100;
    var anuncio = document.getElementById('a11y-anuncio');
    function estado() { var b = cont.querySelector('[data-lista-agregar]'); if (b) { b.disabled = filas.querySelectorAll('.lista-fila').length >= max; } }
    cont.addEventListener('click', function (e) {
      var q = e.target.closest ? e.target.closest('[data-lista-quitar]') : null;
      if (q) { var f = q.closest('.lista-fila'); if (f) { f.parentNode.removeChild(f); estado(); if (anuncio) { anuncio.textContent = 'Fila quitada.'; } } return; }
      var a = e.target.closest ? e.target.closest('[data-lista-agregar]') : null;
      if (a && tpl && filas.querySelectorAll('.lista-fila').length < max) {
        var div = document.createElement('div'); div.innerHTML = tpl.innerHTML.replace(/__I__/g, String(n++));
        var nueva = div.firstElementChild; filas.appendChild(nueva); estado();
        var c = nueva.querySelector('input,textarea,select'); if (c) { c.focus(); }
        if (anuncio) { anuncio.textContent = 'Fila agregada.'; }
      }
    });
    estado();
  });
})();
