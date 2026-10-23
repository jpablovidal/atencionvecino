/* Recorrido guiado sobre la pantalla real (sin scripts en línea ni ventana nueva).
   Resalta cada elemento de la pantalla con una tarjeta de instrucciones y, cuando el paso corresponde a otra pantalla, navega a ella y sigue ahí.
   Cualquier elemento con [data-recorrido] lo inicia. Se ofrece solo la primera vez por perfil y navegador (en el inicio); se puede repetir cuando se quiera. */
(function () {
  'use strict';
  var raiz = document.getElementById('sgd-recorrido');
  if (!raiz) { return; }
  var perfil = raiz.getAttribute('data-perfil') || 'x';
  var nombre = raiz.getAttribute('data-nombre') || '';
  var pasos = [];
  try { pasos = JSON.parse(raiz.getAttribute('data-pasos') || '[]'); } catch (e) { pasos = []; }
  if (!pasos.length) { return; }
  var CLAVE_ESTADO = 'sgd-recorrido-estado';
  var CLAVE_VISTO = 'sgd-recorrido-' + perfil;
  var anuncio = document.getElementById('a11y-anuncio');
  var foco = null, tarjeta = null, actual = 0, previo = null, destino = null;

  function leer(almacen, k) { try { return window[almacen].getItem(k); } catch (e) { return null; } }
  function escribir(almacen, k, v) { try { if (v === null) { window[almacen].removeItem(k); } else { window[almacen].setItem(k, v); } } catch (e) { /* sin almacenamiento */ } }
  function estado() { try { return JSON.parse(leer('sessionStorage', CLAVE_ESTADO) || 'null'); } catch (e) { return null; } }
  function guardar(i, nav) { escribir('sessionStorage', CLAVE_ESTADO, JSON.stringify({ perfil: perfil, i: i, nav: nav ? 1 : 0 })); }
  function decir(t) { if (anuncio) { anuncio.textContent = ''; window.setTimeout(function () { anuncio.textContent = t; }, 30); } }
  function el(tag, cls, txt) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (txt !== undefined) { n.textContent = txt; } return n; }

  function visible(n) { return !!n && (n.offsetWidth > 0 || n.offsetHeight > 0 || n.getClientRects().length > 0); }

  /* Busca el elemento del paso; si está dentro de un grupo plegado (details), lo abre. */
  function buscar(sel) {
    if (!sel) { return null; }
    var n = null;
    try { n = document.querySelector(sel); } catch (e) { return null; }
    if (!n) { return null; }
    var d = n.parentElement && n.parentElement.closest ? n.parentElement.closest('details') : null; // solo los grupos que contienen al elemento (no el propio)
    while (d) { if (!d.open) { d.open = true; } d = d.parentElement ? d.parentElement.closest('details') : null; }
    return visible(n) ? n : null;
  }

  function crear() {
    if (tarjeta) { return; }
    foco = el('div', 'sgd-rec-foco'); foco.setAttribute('aria-hidden', 'true'); document.body.appendChild(foco);
    tarjeta = el('div', 'sgd-rec-tarjeta');
    tarjeta.setAttribute('role', 'dialog'); tarjeta.setAttribute('aria-modal', 'false'); tarjeta.setAttribute('aria-labelledby', 'sgd-rec-titulo'); tarjeta.setAttribute('aria-describedby', 'sgd-rec-texto');
    var cab = el('p', 'sgd-rec-cab'); cab.appendChild(el('span', 'sgd-rec-perfil', 'Recorrido guiado' + (nombre ? ' · ' + nombre : ''))); var cont = el('span', 'sgd-rec-cont'); cont.id = 'sgd-rec-cont'; cab.appendChild(cont); tarjeta.appendChild(cab);
    var barra = el('progress', 'sgd-rec-barra'); barra.id = 'sgd-rec-barra'; barra.setAttribute('aria-label', 'Avance del recorrido'); barra.max = pasos.length; tarjeta.appendChild(barra);
    var h = el('h2', 'sgd-rec-titulo'); h.id = 'sgd-rec-titulo'; h.tabIndex = -1; tarjeta.appendChild(h);
    var t = el('p', 'sgd-rec-texto'); t.id = 'sgd-rec-texto'; tarjeta.appendChild(t);
    var acc = el('div', 'sgd-rec-acciones');
    var ant = el('button', 'btn btn-sm btn-outline-secondary', 'Anterior'); ant.type = 'button'; ant.id = 'sgd-rec-ant';
    var sig = el('button', 'btn btn-sm btn-primary', 'Siguiente'); sig.type = 'button'; sig.id = 'sgd-rec-sig';
    var cer = el('button', 'btn btn-sm btn-link', 'Cerrar el recorrido'); cer.type = 'button'; cer.id = 'sgd-rec-cerrar';
    acc.appendChild(ant); acc.appendChild(sig); acc.appendChild(cer); tarjeta.appendChild(acc);
    var man = el('a', 'sgd-rec-manual', 'Ver esto en el manual'); man.id = 'sgd-rec-manual'; man.target = '_blank'; man.rel = 'noopener'; tarjeta.appendChild(man);
    document.body.appendChild(tarjeta);
    ant.addEventListener('click', function () { ir(actual - 1); });
    sig.addEventListener('click', function () { if (actual >= pasos.length - 1) { cerrar(true); } else { ir(actual + 1); } });
    cer.addEventListener('click', function () { cerrar(true); });
    tarjeta.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); cerrar(true); }
      else if (e.key === 'ArrowRight' && e.target.tagName !== 'INPUT') { sig.click(); }
      else if (e.key === 'ArrowLeft' && e.target.tagName !== 'INPUT') { if (actual > 0) { ant.click(); } }
    });
    window.addEventListener('resize', colocar);
  }

  /* Coloca el resaltado sobre el elemento y la tarjeta junto a él (o fija abajo si no hay elemento o no cabe). */
  function colocar() {
    if (!tarjeta || !tarjeta.parentNode || tarjeta.hidden) { return; }
    var movil = window.innerWidth < 768;
    tarjeta.classList.remove('sgd-rec-fija', 'sgd-rec-ancla');
    tarjeta.style.top = ''; tarjeta.style.left = ''; tarjeta.style.maxWidth = '';
    if (!destino || !document.body.contains(destino) || !visible(destino)) {
      foco.hidden = true; tarjeta.classList.add('sgd-rec-fija'); return;
    }
    var r = destino.getBoundingClientRect(), pad = 6, sx = window.pageXOffset, sy = window.pageYOffset;
    foco.hidden = false;
    foco.style.top = (r.top + sy - pad) + 'px'; foco.style.left = (r.left + sx - pad) + 'px';
    foco.style.width = (r.width + pad * 2) + 'px'; foco.style.height = (r.height + pad * 2) + 'px';
    var th = tarjeta.offsetHeight, tw = Math.min(380, window.innerWidth - 24);
    var abajo = window.innerHeight - r.bottom, arriba = r.top;
    if (movil || (abajo < th + 24 && arriba < th + 24) || r.height > window.innerHeight * 0.6) { tarjeta.classList.add('sgd-rec-fija'); return; }
    tarjeta.classList.add('sgd-rec-ancla');
    var top = abajo >= th + 24 ? r.bottom + sy + 12 : r.top + sy - th - 12;
    var left = Math.max(12, Math.min(r.left + sx, sx + window.innerWidth - tw - 12));
    tarjeta.style.top = top + 'px'; tarjeta.style.left = left + 'px'; tarjeta.style.maxWidth = tw + 'px';
  }

  function mostrar(i) {
    actual = Math.max(0, Math.min(pasos.length - 1, i));
    var p = pasos[actual];
    crear();
    tarjeta.hidden = false;
    document.documentElement.classList.add('sgd-recorriendo');
    destino = buscar(p.sel);
    document.getElementById('sgd-rec-cont').textContent = 'Paso ' + (actual + 1) + ' de ' + pasos.length;
    document.getElementById('sgd-rec-barra').value = actual + 1;
    document.getElementById('sgd-rec-titulo').textContent = p.titulo;
    document.getElementById('sgd-rec-texto').textContent = p.texto;
    document.getElementById('sgd-rec-ant').disabled = actual === 0;
    document.getElementById('sgd-rec-sig').textContent = actual === pasos.length - 1 ? 'Terminar' : 'Siguiente';
    var m = document.getElementById('sgd-rec-manual'); if (p.manual) { m.href = p.manual; m.hidden = false; } else { m.hidden = true; }
    if (destino && destino.scrollIntoView) { destino.scrollIntoView({ block: 'center', inline: 'nearest' }); }
    colocar();
    window.setTimeout(colocar, 120);
    window.setTimeout(colocar, 500);
    document.getElementById('sgd-rec-titulo').focus({ preventScroll: true });
    decir('Paso ' + (actual + 1) + ' de ' + pasos.length + ': ' + p.titulo + '. ' + p.texto);
  }

  /* Va al paso i: si su pantalla es otra, navega a ella y el recorrido continúa allí. */
  function ir(i) {
    i = Math.max(0, Math.min(pasos.length - 1, i));
    var p = pasos[i];
    if (p.url && p.url !== window.location.pathname) { guardar(i, true); window.location.href = p.url; return; }
    guardar(i, false);
    mostrar(i);
  }

  function cerrar(visto) {
    escribir('sessionStorage', CLAVE_ESTADO, null);
    if (visto) { escribir('localStorage', CLAVE_VISTO, '1'); }
    if (tarjeta) { tarjeta.hidden = true; }
    if (foco) { foco.hidden = true; }
    document.documentElement.classList.remove('sgd-recorriendo');
    if (previo && document.body.contains(previo) && previo.focus) { previo.focus(); }
  }

  function iniciar() { previo = document.activeElement; ir(0); }

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-recorrido]') : null;
    if (!b) { return; }
    e.preventDefault();
    var d = b.closest('details'); if (d) { d.open = false; }
    iniciar();
  });

  function retomar() {
    var s = estado();
    if (s && s.perfil === perfil && s.i >= 0 && s.i < pasos.length) {
      var p = pasos[s.i];
      // se continúa si se llegó navegando desde el recorrido o si la persona sigue en la pantalla del paso; si se fue a otra parte, se cierra
      if (s.nav === 1 || !p.url || p.url === window.location.pathname) { guardar(s.i, false); mostrar(s.i); return; }
      cerrar(false);
      return;
    }
    if (raiz.getAttribute('data-auto') === '1' && !leer('localStorage', CLAVE_VISTO)) { window.setTimeout(iniciar, 600); }
  }
  if (document.readyState === 'complete') { window.setTimeout(retomar, 60); } else { window.addEventListener('load', function () { window.setTimeout(retomar, 60); }); }
})();
