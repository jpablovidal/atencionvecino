/* Progreso y pasos (sin scripts en línea; CSP script-src 'self').
   1) Barra superior en cada carga de vista (inicial, enlaces y formularios).
   2) Subida de archivos con barra de progreso real (XMLHttpRequest), pasos y botón Cancelar.
   3) Formularios por pasos ([data-pasos] con secciones [data-paso]) con revisión final.
   Sin JavaScript todo sigue funcionando: los formularios se envían normalmente y los pasos se muestran juntos. */
(function () {
  'use strict';
  var raiz = document.documentElement;
  var quieto = function () { return raiz.classList.contains('a11y-motion') || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); };
  var anuncio = document.getElementById('a11y-anuncio');
  function decir(t) { if (anuncio) { anuncio.textContent = ''; window.setTimeout(function () { anuncio.textContent = t; }, 30); } }
  function el(tag, cls, texto) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (texto != null) { e.textContent = texto; } return e; }
  function mb(n) { return n >= 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }

  /* ---------- 1. Barra de carga de vistas ---------- */
  var barra = document.getElementById('sgd-carga');
  var relleno = barra ? barra.querySelector('.sgd-carga-barra') : null;
  var timer = null; var valor = 0; var seguro = null;
  function pintar(v, etiqueta) {
    valor = v;
    if (!barra) { return; }
    relleno.style.width = v + '%';
    barra.setAttribute('aria-valuenow', String(Math.round(v)));
    if (etiqueta) { barra.setAttribute('aria-label', etiqueta); }
  }
  function empezar(etiqueta) {
    if (!barra) { return; }
    window.clearInterval(timer); window.clearTimeout(seguro);
    raiz.classList.remove('sgd-cargando');
    barra.hidden = false; barra.classList.remove('sgd-carga-fin');
    pintar(8, etiqueta || 'Cargando la página');
    if (quieto()) { pintar(70); } else {
      timer = window.setInterval(function () { if (valor < 92) { pintar(valor + (92 - valor) * 0.08); } }, 200);
    }
    /* red de seguridad: una descarga de archivo no recarga la página; la barra no debe quedar atascada */
    seguro = window.setTimeout(function () { terminar(true); }, 20000);
  }
  function terminar(rapido) {
    if (!barra) { return; }
    window.clearInterval(timer); window.clearTimeout(seguro);
    raiz.classList.remove('sgd-cargando');
    pintar(100);
    window.setTimeout(function () { barra.classList.add('sgd-carga-fin'); }, rapido ? 0 : 150);
    window.setTimeout(function () { barra.hidden = true; barra.classList.remove('sgd-carga-fin'); pintar(0, 'Cargando la página'); raiz.classList.remove('sgd-cargando'); }, rapido ? 150 : 600);
  }
  if (barra) {
    barra.hidden = false; pintar(document.readyState === 'loading' ? 35 : 70);
    document.addEventListener('DOMContentLoaded', function () { pintar(70); });
    if (document.readyState === 'complete') { terminar(); } else { window.addEventListener('load', function () { terminar(); }); }
    window.addEventListener('pageshow', function (e) { if (e.persisted) { terminar(true); } });
    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
      var a = e.target.closest ? e.target.closest('a[href]') : null;
      if (!a || a.target === '_blank' || a.hasAttribute('download') || a.origin !== window.location.origin) { return; }
      var h = a.getAttribute('href') || '';
      if (h.charAt(0) === '#' || (a.pathname === window.location.pathname && a.search === window.location.search && a.hash)) { return; }
      empezar('Cargando la página');
    });
    document.addEventListener('submit', function (e) {
      var f = e.target;
      if (e.defaultPrevented || !f || f.target === '_blank' || f.getAttribute('data-sin-progreso') !== null) { return; }
      empezar('Enviando el formulario');
    });
  }

  /* ---------- 2. Subida de archivos con progreso ---------- */
  function archivosDe(f) {
    var l = [];
    f.querySelectorAll('input[type=file]').forEach(function (i) { Array.prototype.forEach.call(i.files || [], function (a) { l.push(a); }); });
    return l;
  }
  function subir(f, submitter, archivos) {
    var total = archivos.reduce(function (s, a) { return s + a.size; }, 0);
    var previo = document.activeElement;
    var fondo = el('div', 'sgd-subida-fondo');
    var cuadro = el('div', 'sgd-subida'); cuadro.setAttribute('role', 'dialog'); cuadro.setAttribute('aria-modal', 'true'); cuadro.setAttribute('aria-labelledby', 'sgd-subida-t');
    var t = el('h2', 'h5', 'Enviando sus archivos'); t.id = 'sgd-subida-t';
    var resumen = el('p', 'small mb-2', archivos.length + (archivos.length === 1 ? ' archivo' : ' archivos') + ' · ' + mb(total));
    var pasos = el('ol', 'sgd-subida-pasos');
    var nombres = ['Subiendo los archivos', 'Verificando y protegiendo en el servidor', 'Listo'];
    var items = nombres.map(function (n, i) { var li = el('li', null, (i + 1) + '. ' + n); pasos.appendChild(li); return li; });
    var prog = el('progress', 'sgd-subida-prog'); prog.max = 100; prog.value = 0; prog.setAttribute('aria-label', 'Avance de la subida');
    var pct = el('p', 'sgd-subida-pct', '0 %'); pct.setAttribute('role', 'status');
    var cancelar = el('button', 'btn btn-outline-secondary btn-sm', 'Cancelar'); cancelar.type = 'button';
    var nota = el('p', 'small text-muted mt-2 mb-0', 'No cierre esta ventana hasta que termine. Puede cancelar y volver a intentarlo.');
    [t, resumen, pasos, prog, pct, cancelar, nota].forEach(function (n) { cuadro.appendChild(n); });
    fondo.appendChild(cuadro); document.body.appendChild(fondo);
    function paso(n) { items.forEach(function (li, i) { li.className = i < n ? 'hecho' : (i === n ? 'actual' : ''); if (i === n) { li.setAttribute('aria-current', 'step'); } else { li.removeAttribute('aria-current'); } }); }
    paso(0); cancelar.focus();
    empezar('Subiendo archivos');
    var botones = f.querySelectorAll('button, input[type=submit]'); botones.forEach(function (b) { b.disabled = true; });
    function cerrar() { if (fondo.parentNode) { fondo.parentNode.removeChild(fondo); } botones.forEach(function (b) { b.disabled = false; }); if (previo && previo.focus) { previo.focus(); } }
    var datos;
    try { datos = submitter ? new FormData(f, submitter) : new FormData(f); } catch (e) { datos = new FormData(f); }
    var x = new XMLHttpRequest();
    x.open((f.method || 'post').toUpperCase(), f.action);
    x.setRequestHeader('Accept', 'text/html,application/xhtml+xml');
    var ultimo = -1;
    x.upload.onprogress = function (e) {
      if (!e.lengthComputable) { prog.removeAttribute('value'); return; }
      var p = Math.min(100, Math.round(e.loaded * 100 / e.total));
      prog.value = p; pct.textContent = p + ' % (' + mb(e.loaded) + ' de ' + mb(e.total) + ')'; pintar(Math.min(90, p * 0.9));
      if (p >= ultimo + 25) { ultimo = p; }
    };
    x.upload.onload = function () { paso(1); prog.removeAttribute('value'); pct.textContent = 'Archivos recibidos. El servidor está verificándolos y protegiéndolos…'; cancelar.disabled = true; decir('Archivos recibidos. Verificando y protegiendo en el servidor.'); };
    x.onload = function () {
      paso(2); prog.value = 100; pct.textContent = 'Listo. Abriendo el resultado…'; pintar(100);
      var html = x.responseText;
      try { if (x.responseURL && x.responseURL.indexOf(window.location.origin) === 0) { window.history.replaceState(null, '', x.responseURL); } } catch (e) { /* sin historial */ }
      document.open(); document.write(html); document.close();
    };
    x.onerror = x.ontimeout = function () {
      cerrar(); terminar(true);
      var av = el('div', 'alert alert-danger', 'No se pudo enviar: se interrumpió la conexión. Revise su conexión e intente nuevamente; sus datos no se perdieron.'); av.setAttribute('role', 'alert');
      f.insertBefore(av, f.firstChild); av.scrollIntoView(); decir('No se pudo enviar. Revise su conexión e intente nuevamente.');
    };
    x.onabort = function () { cerrar(); terminar(true); decir('Envío cancelado. Puede intentarlo de nuevo.'); };
    cancelar.addEventListener('click', function () { x.abort(); });
    cuadro.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !cancelar.disabled) { x.abort(); } if (e.key === 'Tab') { e.preventDefault(); cancelar.focus(); } });
    x.send(datos);
  }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || !f || !f.matches || !f.matches('form[enctype="multipart/form-data"]') || !window.XMLHttpRequest || !window.FormData) { return; }
    if (f.getAttribute('data-en-final') === '0') { return; }
    var archivos = archivosDe(f);
    if (!archivos.length) { return; }
    e.preventDefault();
    var tope = parseInt((document.querySelector('meta[name=sgd-max-subida]') || {}).content || '0', 10);
    var suma = archivos.reduce(function (s, a) { return s + a.size; }, 0);
    if (tope > 0 && suma > tope * 0.97) {
      var av = el('div', 'alert alert-danger', 'Los archivos suman ' + mb(suma) + ' y el servidor admite como máximo ' + mb(tope) + ' por envío. Quite o reduzca archivos e intente de nuevo; nada fue enviado.'); av.setAttribute('role', 'alert');
      f.insertBefore(av, f.firstChild); av.scrollIntoView(); decir('Los archivos superan el máximo permitido. Nada fue enviado.');
      return;
    }
    var conf = f.getAttribute('data-confirm');
    if (conf && !window.confirm(conf)) { return; }
    e.stopImmediatePropagation(); /* evita que la barra de navegación arranque dos veces */
    subir(f, e.submitter || null, archivos);
  }, true);

  /* ---------- 3. Formularios por pasos ---------- */
  document.querySelectorAll('form[data-pasos]').forEach(function (f) {
    var secciones = Array.prototype.slice.call(f.querySelectorAll(':scope [data-paso]'));
    if (secciones.length < 2) { return; }
    var ultimaSec = secciones[secciones.length - 1];
    /* acciones finales: lo que sigue a la última sección (botones de envío, enlaces) */
    var acciones = el('div', 'sgd-pasos-acciones');
    var tras = []; for (var n = ultimaSec.nextSibling; n; n = n.nextSibling) { tras.push(n); }
    tras.forEach(function (n) { acciones.appendChild(n); });
    var titulos = secciones.map(function (s) { return s.getAttribute('data-paso'); }).concat(['Revisar y enviar']);
    var total = titulos.length; var actual = 0;
    var cab = el('ol', 'sgd-pasos'); cab.setAttribute('aria-label', 'Pasos del formulario');
    var lis = titulos.map(function (tt, i) {
      var li = el('li'); var b = el('button', 'sgd-paso-btn'); b.type = 'button';
      b.appendChild(el('span', 'sgd-paso-num', String(i + 1))); b.appendChild(el('span', 'sgd-paso-txt', tt));
      b.addEventListener('click', function () { if (i < actual) { ir(i); } });
      li.appendChild(b); cab.appendChild(li); return li;
    });
    var revision = el('section', 'sgd-revision'); revision.setAttribute('data-revision', '1');
    var h = el('h2', 'h5', 'Revise antes de enviar'); h.tabIndex = -1; revision.appendChild(h);
    var lista = el('dl', 'sgd-revision-lista'); revision.appendChild(lista);
    revision.appendChild(el('p', 'small text-muted', 'Si algo no está correcto, vuelva al paso correspondiente con «Atrás» o con los números de arriba.'));
    var nav = el('div', 'sgd-pasos-nav');
    var atras = el('button', 'btn btn-outline-secondary', 'Atrás'); atras.type = 'button';
    var sig = el('button', 'btn btn-primary', 'Siguiente'); sig.type = 'button';
    var cont = el('p', 'sgd-pasos-cont mb-0'); cont.setAttribute('aria-hidden', 'true');
    nav.appendChild(atras); nav.appendChild(sig); nav.appendChild(cont);
    secciones[0].parentNode.insertBefore(cab, secciones[0]);
    ultimaSec.parentNode.insertBefore(revision, ultimaSec.nextSibling);
    revision.parentNode.insertBefore(nav, revision.nextSibling);
    nav.parentNode.insertBefore(acciones, nav.nextSibling);
    f.classList.add('sgd-pasos-activo');

    function etiquetaDe(c) {
      if (c.id) { var l = f.querySelector('label[for="' + c.id.replace(/"/g, '') + '"]'); if (l) { return l.textContent.replace(/\(obligatorio\)|\(opcional\)/g, '').trim(); } }
      var lg = c.closest('fieldset'); var lg2 = lg ? lg.querySelector('legend') : null;
      return lg2 ? lg2.textContent.trim() : (c.name || 'Dato');
    }
    function resumir() {
      lista.textContent = '';
      var vistos = {};
      secciones.forEach(function (s) {
        s.querySelectorAll('input, select, textarea').forEach(function (c) {
          if (c.type === 'hidden' || c.disabled || c.name === '_token') { return; }
          var v = '';
          if (c.type === 'file') { v = Array.prototype.map.call(c.files || [], function (a) { return a.name + ' (' + mb(a.size) + ')'; }).join(', ') || 'Sin archivo'; }
          else if (c.type === 'radio') { if (!c.checked) { return; } var l = f.querySelector('label[for="' + c.id + '"]'); v = l ? l.textContent.trim() : c.value; }
          else if (c.type === 'checkbox') { v = c.checked ? 'Sí' : 'No'; }
          else if (c.tagName === 'SELECT') { v = c.selectedOptions.length ? c.selectedOptions[0].textContent.trim() : ''; }
          else { v = c.value.trim(); }
          var et = c.type === 'radio' ? (c.closest('fieldset') && c.closest('fieldset').querySelector('legend') ? c.closest('fieldset').querySelector('legend').textContent.trim() : 'Opción') : etiquetaDe(c);
          var k = et + '|' + (c.type === 'radio' ? 'r' : c.id || c.name);
          if (vistos[k]) { return; } vistos[k] = 1;
          if (v === '' && c.type !== 'file') { v = '—'; }
          lista.appendChild(el('dt', null, et)); lista.appendChild(el('dd', null, v.length > 300 ? v.slice(0, 300) + '…' : v));
        });
      });
    }
    function ir(i) {
      actual = i;
      secciones.forEach(function (s, k) { s.hidden = k !== i; });
      revision.hidden = i !== total - 1; acciones.hidden = i !== total - 1;
      if (i === total - 1) { resumir(); }
      lis.forEach(function (li, k) { li.className = k < i ? 'hecho' : (k === i ? 'actual' : ''); var b = li.firstChild; if (k === i) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); } b.disabled = k >= i; });
      f.setAttribute('data-en-final', i === total - 1 ? '1' : '0');
      atras.hidden = i === 0; sig.hidden = i === total - 1;
      cont.textContent = 'Paso ' + (i + 1) + ' de ' + total + ' · ' + titulos[i];
      decir('Paso ' + (i + 1) + ' de ' + total + ': ' + titulos[i]);
      var foco = i === total - 1 ? h : secciones[i].querySelector('input:not([type=hidden]):not([readonly]), select, textarea');
      if (foco && foco.focus) { foco.focus({ preventScroll: true }); }
      cab.scrollIntoView({ block: 'nearest', behavior: quieto() ? 'auto' : 'smooth' });
    }
    function validoPaso() {
      var cs = secciones[actual].querySelectorAll('input, select, textarea');
      for (var k = 0; k < cs.length; k++) { if (!cs[k].disabled && !cs[k].checkValidity()) { cs[k].reportValidity(); return false; } }
      return true;
    }
    sig.addEventListener('click', function () { if (validoPaso()) { ir(actual + 1); } });
    atras.addEventListener('click', function () { if (actual > 0) { ir(actual - 1); } });
    f.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && actual < total - 1 && e.target.tagName === 'INPUT' && e.target.type !== 'file') { e.preventDefault(); sig.click(); }
    });
    f.addEventListener('submit', function (e) { if (actual < total - 1) { e.preventDefault(); e.stopImmediatePropagation(); sig.click(); } }, true);
    /* si el servidor devolvió errores en algún campo, empezar en el paso que lo contiene */
    var inv = f.querySelector('.is-invalid');
    var inicio = 0; if (inv) { secciones.forEach(function (s, k) { if (s.contains(inv)) { inicio = k; } }); }
    ir(inicio);
    var primero = document.activeElement; if (primero && primero !== document.body && inicio === 0) { /* no robar el foco del salto de teclado */ }
  });
})();
