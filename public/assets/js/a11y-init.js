/* Aplica las preferencias de accesibilidad ANTES de pintar (evita parpadeo). Sin scripts en línea (CSP). */
(function () {
  'use strict';
  try {
    var p = JSON.parse(window.localStorage.getItem('sgd_a11y') || '{}');
    var c = document.documentElement.classList;
    if (p.fs && /^(m1|1|2|3)$/.test(String(p.fs))) { c.add('a11y-fs-' + p.fs); }
    ['contrast', 'legible', 'links', 'motion'].forEach(function (k) { if (p[k] === true) { c.add('a11y-' + k); } });
  } catch (e) { /* almacenamiento no disponible: se usan los valores por defecto */ }
})();
