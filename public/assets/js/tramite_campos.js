/* Editor de campos del formulario de un trámite (sin scripts en línea). */
(function () {
  'use strict';
  var cont = document.getElementById('campos');
  if (!cont) { return; }

  function aplicarTipo(fila) {
    var sel = fila.querySelector('[data-tipo-campo]');
    var tipo = sel ? sel.value : 'texto';
    fila.querySelectorAll('[data-tipos]').forEach(function (b) { b.hidden = b.getAttribute('data-tipos').split(' ').indexOf(tipo) === -1; });
  }

  function slug(t) {
    var s = t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^[0-9_]+|_+$/g, '');
    return (s || 'campo').slice(0, 26);
  }

  function claves() {
    var o = [];
    cont.querySelectorAll('[data-fila]').forEach(function (f) {
      if (f.hidden) { return; }
      var k = f.querySelector('[data-clave]'), t = f.querySelector('[data-tipo-campo]');
      if (k && k.value && ['numero', 'fecha', 'si_no', 'lista', 'calculo'].indexOf(t.value) !== -1) { o.push(k.value); }
    });
    return o;
  }

  function variables(fila) {
    var s = fila.querySelector('[data-variables]');
    if (!s) { return; }
    var k = claves().filter(function (c) { var mia = fila.querySelector('[data-clave]'); return !mia || mia.value !== c; });
    s.textContent = k.length ? 'Campos que puede usar: ' + k.join(', ') + ', hoy.' : 'Aún no hay otros campos numéricos, de fecha o de lista.';
  }

  cont.querySelectorAll('[data-fila]').forEach(aplicarTipo);

  document.addEventListener('change', function (e) {
    var t = e.target.closest ? e.target.closest('[data-tipo-campo]') : null;
    if (t) { aplicarTipo(t.closest('[data-fila]')); }
  });
  document.addEventListener('input', function (e) {
    var et = e.target.closest ? e.target.closest('[data-etiqueta]') : null;
    if (et) {
      var fila = et.closest('[data-fila]'), k = fila.querySelector('[data-clave]');
      if (k && !k.getAttribute('data-manual')) { k.value = slug(et.value); }
    }
    var k2 = e.target.closest ? e.target.closest('[data-clave]') : null;
    if (k2) { k2.setAttribute('data-manual', '1'); }
  });
  document.addEventListener('focusin', function (e) {
    var f = e.target.closest ? e.target.closest('[data-formula]') : null;
    if (f) { variables(f.closest('[data-fila]')); }
  });

  document.getElementById('agregar-campo').addEventListener('click', function () {
    var n = 0;
    cont.querySelectorAll('[data-fila]').forEach(function (f) {
      var m = /campos\[(\d+)\]/.exec((f.querySelector('input[name^="campos["]') || {}).name || '');
      if (m) { n = Math.max(n, +m[1] + 1); }
    });
    if (cont.querySelectorAll('[data-fila]:not([hidden])').length >= 40) { return; }
    var div = document.createElement('div');
    div.innerHTML = document.getElementById('tpl-campo').innerHTML.replace(/__I__/g, String(n)).trim();
    var fila = div.firstElementChild;
    cont.appendChild(fila);
    aplicarTipo(fila);
    var e = fila.querySelector('[data-etiqueta]');
    if (e) { e.focus(); }
  });

  cont.addEventListener('click', function (e) {
    var fila = e.target.closest ? e.target.closest('[data-fila]') : null;
    if (!fila) { return; }
    if (e.target.closest('[data-quitar]')) {
      var id = fila.querySelector('input[name$="[id]"]');
      if (id && id.value) { fila.querySelector('[data-eliminar]').value = '1'; fila.hidden = true; } else { fila.remove(); }
    } else if (e.target.closest('[data-subir]')) {
      var prev = fila.previousElementSibling; if (prev) { cont.insertBefore(fila, prev); }
    } else if (e.target.closest('[data-bajar]')) {
      var next = fila.nextElementSibling; if (next) { cont.insertBefore(next, fila); }
    }
  });
})();
