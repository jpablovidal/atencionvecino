/* Barra de accesibilidad, gestión del foco y resumen de errores. Sin dependencias, sin scripts en línea. */
(function () {
  'use strict';
  var root = document.documentElement;
  var KEY = 'sgd_a11y';
  var SIZES = ['m1', '0', '1', '2', '3'];

  function leer() { try { return JSON.parse(window.localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; } }
  function guardar(p) { try { window.localStorage.setItem(KEY, JSON.stringify(p)); } catch (e) { /* sin almacenamiento */ } }
  function anunciar(msg) {
    var r = document.getElementById('a11y-anuncio');
    if (r) { r.textContent = ''; window.setTimeout(function () { r.textContent = msg; }, 50); }
  }
  function aplicar(p) {
    SIZES.forEach(function (s) { root.classList.remove('a11y-fs-' + s); });
    if (p.fs && p.fs !== '0') { root.classList.add('a11y-fs-' + p.fs); }
    ['contrast', 'legible', 'links', 'motion'].forEach(function (k) { root.classList.toggle('a11y-' + k, p[k] === true); });
    document.querySelectorAll('[data-a11y-toggle]').forEach(function (b) {
      b.setAttribute('aria-pressed', p[b.getAttribute('data-a11y-toggle')] === true ? 'true' : 'false');
    });
    var t = document.getElementById('a11y-tamano');
    if (t) { t.textContent = {'m1': '87%', '0': '100%', '1': '112%', '2': '125%', '3': '150%'}[p.fs || '0']; }
  }

  var p = leer();
  aplicar(p);

  document.querySelectorAll('[data-a11y-size]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = SIZES.indexOf(p.fs || '0');
      var d = b.getAttribute('data-a11y-size');
      i = d === 'reset' ? 1 : Math.max(0, Math.min(SIZES.length - 1, i + (d === 'up' ? 1 : -1)));
      p.fs = SIZES[i]; guardar(p); aplicar(p); anunciar('Tamaño del texto: ' + document.getElementById('a11y-tamano').textContent);
    });
  });
  document.querySelectorAll('[data-a11y-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.getAttribute('data-a11y-toggle');
      p[k] = !(p[k] === true); guardar(p); aplicar(p);
      anunciar(b.getAttribute('data-nombre') + (p[k] ? ' activado' : ' desactivado'));
    });
  });
  var reset = document.getElementById('a11y-reset');
  if (reset) {
    reset.addEventListener('click', function () { p = {}; guardar(p); aplicar(p); anunciar('Preferencias de accesibilidad restablecidas'); });
  }

  /* Resumen de errores: foco al cargar y marcado de campos inválidos (aria-invalid + aria-describedby). */
  var resumen = document.getElementById('resumen-errores');
  if (resumen) {
    (resumen.getAttribute('data-campos') || '').split(',').forEach(function (n) {
      if (!n) { return; }
      var el = document.getElementById(n) || document.querySelector('[name="' + n.replace(/"/g, '') + '"]');
      if (el) { el.setAttribute('aria-invalid', 'true'); el.setAttribute('aria-describedby', ((el.getAttribute('aria-describedby') || '') + ' resumen-errores').trim()); }
    });
    resumen.focus();
  } else {
    var alerta = document.querySelector('.alert-danger[role="alert"]');
    if (alerta) { alerta.setAttribute('tabindex', '-1'); alerta.focus(); }
  }

  /* El enlace "Saltar al contenido" debe mover el foco real a <main>. */
  document.querySelectorAll('.skip-link').forEach(function (a) {
    a.addEventListener('click', function () {
      var t = document.getElementById((a.getAttribute('href') || '').slice(1));
      if (t) { t.setAttribute('tabindex', '-1'); t.focus(); }
    });
  });

  /* Esc cierra el menú de la barra de accesibilidad (si está abierto en pantallas pequeñas). */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var d = document.getElementById('a11y-panel');
      if (d && d.hasAttribute('open')) { d.removeAttribute('open'); var s = d.querySelector('summary'); if (s) { s.focus(); } }
    }
  });
})();
