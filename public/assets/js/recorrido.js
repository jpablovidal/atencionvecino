/* Recorrido guiado (sin scripts en línea). Abre el componente «onboarding» del framework gob.cl: html.onboarding-active muestra la sección a pantalla completa.
   Cualquier elemento con [data-recorrido] lo abre. Se muestra solo la primera vez por perfil y navegador (en el inicio); se puede repetir cuando se quiera. */
(function () {
  'use strict';
  var sec = document.getElementById('sgd-recorrido');
  if (!sec) { return; }
  var raiz = document.documentElement;
  var pasos = Array.prototype.slice.call(sec.querySelectorAll('[data-rec-paso]'));
  var total = pasos.length; var actual = 0; var previo = null;
  var contador = document.getElementById('rec-contador'); var barra = document.getElementById('rec-barra');
  var ant = document.getElementById('rec-ant'); var sig = document.getElementById('rec-sig'); var cerrar = document.getElementById('rec-cerrar');
  var anuncio = document.getElementById('a11y-anuncio');
  var clave = 'sgd-recorrido-' + (sec.getAttribute('data-perfil') || 'x');
  function leer() { try { return window.localStorage.getItem(clave); } catch (e) { return null; } }
  function marcar() { try { window.localStorage.setItem(clave, '1'); } catch (e) { /* sin almacenamiento: se vuelve a ofrecer */ } }
  function decir(t) { if (anuncio) { anuncio.textContent = ''; window.setTimeout(function () { anuncio.textContent = t; }, 30); } }
  function ir(i) {
    actual = Math.max(0, Math.min(total - 1, i));
    pasos.forEach(function (p, k) { p.hidden = k !== actual; });
    contador.textContent = 'Paso ' + (actual + 1) + ' de ' + total;
    barra.value = actual + 1;
    ant.disabled = actual === 0;
    sig.textContent = actual === total - 1 ? 'Terminar' : 'Siguiente';
    var h = pasos[actual].querySelector('h2'); if (h) { h.focus({ preventScroll: true }); }
    decir('Paso ' + (actual + 1) + ' de ' + total + ': ' + (h ? h.textContent : ''));
  }
  function abrir() {
    previo = document.activeElement;
    raiz.classList.remove('onboarding-closed'); raiz.classList.add('onboarding-active');
    ir(0);
  }
  function cerrarRec() {
    raiz.classList.remove('onboarding-active'); raiz.classList.add('onboarding-closed');
    marcar();
    if (previo && previo.focus) { previo.focus(); }
  }
  sig.addEventListener('click', function () { if (actual === total - 1) { cerrarRec(); } else { ir(actual + 1); } });
  ant.addEventListener('click', function () { ir(actual - 1); });
  cerrar.addEventListener('click', cerrarRec);
  sec.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { e.preventDefault(); cerrarRec(); return; }
    if (e.key === 'ArrowRight' && e.target.tagName !== 'INPUT') { if (actual < total - 1) { ir(actual + 1); } return; }
    if (e.key === 'ArrowLeft' && e.target.tagName !== 'INPUT') { ir(actual - 1); return; }
    if (e.key === 'Tab') { /* el foco se queda dentro del recorrido */
      var f = Array.prototype.filter.call(sec.querySelectorAll('a[href], button:not([disabled])'), function (n) { return n.offsetParent !== null; });
      if (!f.length) { return; }
      var p = f[0]; var u = f[f.length - 1];
      if (e.shiftKey && document.activeElement === p) { e.preventDefault(); u.focus(); } else if (!e.shiftKey && document.activeElement === u) { e.preventDefault(); p.focus(); }
    }
  });
  /* los enlaces del recorrido llevan a otra pantalla: se marca como visto para no repetirlo al volver */
  sec.addEventListener('click', function (e) { var a = e.target.closest ? e.target.closest('a[href]') : null; if (a) { marcar(); } });
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-recorrido]') : null;
    if (!b) { return; }
    e.preventDefault(); var d = b.closest('details'); if (d) { d.open = false; }
    abrir();
  });
  if (sec.getAttribute('data-auto') === '1' && !leer() && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches && false)) {
    window.setTimeout(abrir, 600);
  }
})();
