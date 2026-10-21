/* Formulario de nueva solicitud (sin scripts en línea; CSP script-src 'self'). Mejora progresiva: sin JavaScript el formulario sigue funcionando.
   1) Contador de caracteres del detalle (avisa al acercarse y si se pasa).
   2) Anexos: tarjeta por archivo con progreso de lectura, vista previa en modal, reemplazar, quitar y «Tomar fotografía» (móvil).
   3) Consentimiento del tratamiento de datos: si no consiente igual puede enviar; queda registrado que no lo autoriza. */
(function () {
  'use strict';
  var anuncio = document.getElementById('a11y-anuncio');
  function decir(t) { if (anuncio) { anuncio.textContent = ''; window.setTimeout(function () { anuncio.textContent = t; }, 30); } }
  function el(tag, cls, texto) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (texto != null) { e.textContent = texto; } return e; }
  function mb(n) { return n >= 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
  function miles(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

  /* ---------- 1. Contador de caracteres ---------- */
  document.querySelectorAll('textarea[data-contador]').forEach(function (ta) {
    var max = parseInt(ta.getAttribute('data-contador'), 10) || 2000;
    var texto = document.querySelector('[data-contador-texto]');
    if (!texto) { return; }
    ta.removeAttribute('maxlength'); /* con JavaScript se deja escribir de más para poder avisar cuánto se pasó */
    var estado = el('span', 'sr-only'); estado.setAttribute('role', 'status'); texto.parentNode.appendChild(estado);
    var previo = '';
    function largo() { return Array.from(ta.value.replace(/\r\n/g, '\n')).length; }
    function pintar() {
      var n = largo(); var resta = max - n; var clase = 'text-muted'; var msg; var nuevo = 'ok';
      if (n > max) { clase = 'text-danger font-weight-bold'; nuevo = 'pasado'; msg = miles(n) + ' / ' + miles(max) + ' caracteres. Se pasó por ' + miles(n - max) + ' caracteres: acorte el texto para continuar.'; }
      else if (n >= max * 0.9) { clase = 'text-warning-dark'; nuevo = 'cerca'; msg = miles(n) + ' / ' + miles(max) + ' caracteres. Le quedan ' + miles(resta) + '.'; }
      else { msg = miles(n) + ' / ' + miles(max) + ' caracteres.'; }
      texto.className = 'form-text ' + clase; texto.textContent = msg;
      ta.classList.toggle('is-invalid', n > max);
      ta.setCustomValidity(n > max ? 'El detalle supera los ' + miles(max) + ' caracteres.' : '');
      if (nuevo !== previo) { previo = nuevo; if (nuevo === 'pasado') { estado.textContent = 'Se pasó del máximo de ' + miles(max) + ' caracteres.'; } else if (nuevo === 'cerca') { estado.textContent = 'Le quedan ' + miles(resta) + ' caracteres.'; } else { estado.textContent = ''; } }
    }
    ta.addEventListener('input', pintar); pintar();
  });

  /* ---------- 2. Anexos ---------- */
  var EXT = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'];
  function extension(n) { var p = String(n).toLowerCase().split('.'); return p.length > 1 ? p[p.length - 1] : ''; }
  function previsualizar(archivo, url) {
    var a = el('a'); a.href = url; a.hidden = true; a.setAttribute('data-visor', '1');
    a.setAttribute('data-visor-mime', archivo.type || 'application/octet-stream'); a.setAttribute('data-visor-titulo', archivo.name); a.setAttribute('data-visor-descarga', url);
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  }
  function conectar(input) {
    var multiple = input.hasAttribute('data-extras');
    var max = parseInt(input.getAttribute('data-max'), 10) || 0;
    var nombreCampo = input.getAttribute('data-anexo') || 'Archivo';
    var zona = el('div', 'sgd-anexos mt-2'); input.parentNode.appendChild(zona);
    var aviso = el('div', 'small text-danger mt-1'); aviso.setAttribute('role', 'alert'); aviso.hidden = true; zona.parentNode.insertBefore(aviso, zona);
    var camara = el('input'); camara.type = 'file'; camara.accept = 'image/*'; camara.setAttribute('capture', 'environment'); camara.hidden = true; camara.tabIndex = -1; camara.setAttribute('aria-hidden', 'true');
    input.parentNode.appendChild(camara);
    var botonFoto = el('button', 'btn btn-outline-secondary btn-sm mt-1', 'Tomar fotografía'); botonFoto.type = 'button';
    botonFoto.title = 'En un teléfono abre la cámara; en un computador abre el selector de imágenes.';
    input.parentNode.insertBefore(botonFoto, zona);
    botonFoto.addEventListener('click', function () { camara.value = ''; camara.click(); });
    var datos = new DataTransfer(); /* lo que realmente se enviará: el selector nativo se sincroniza con esto */
    var urls = [];
    function error(t) { aviso.textContent = t; aviso.hidden = !t; if (t) { decir(t); } }
    function liberar() { urls.forEach(function (u) { URL.revokeObjectURL(u); }); urls = []; }
    function valido(f) {
      if (EXT.indexOf(extension(f.name)) === -1) { return f.name + ': formato no admitido (use PDF, JPG, PNG o TIF).'; }
      if (max > 0 && f.size > max) { return f.name + ' pesa ' + mb(f.size) + ' y el máximo es ' + mb(max) + '. Elija un archivo más liviano.'; }
      if (f.size === 0) { return f.name + ' está vacío.'; }
      return '';
    }
    function tarjeta(f, idx) {
      var url = URL.createObjectURL(f); urls.push(url);
      var t = el('div', 'sgd-anexo border rounded p-2 mb-2');
      var fila = el('div', 'd-flex flex-wrap align-items-center');
      var nom = el('span', 'mr-auto font-weight-bold text-break', f.name); var tam = el('span', 'small text-muted mx-2', mb(f.size));
      fila.appendChild(nom); fila.appendChild(tam); t.appendChild(fila);
      var prog = el('progress', 'w-100 d-block mt-1'); prog.max = 100; prog.value = 0; prog.setAttribute('aria-label', 'Preparando ' + f.name); t.appendChild(prog);
      var est = el('div', 'small text-muted', 'Leyendo el archivo… 0 %'); est.setAttribute('role', 'status'); t.appendChild(est);
      var botones = el('div', 'mt-1');
      var ver = el('button', 'btn btn-outline-primary btn-sm mr-1', 'Ver'); ver.type = 'button'; ver.setAttribute('aria-label', 'Ver ' + f.name);
      ver.addEventListener('click', function () { previsualizar(f, url); });
      var rep = el('button', 'btn btn-outline-secondary btn-sm mr-1', multiple ? 'Agregar otro' : 'Reemplazar'); rep.type = 'button';
      rep.addEventListener('click', function () { input.click(); });
      var quitar = el('button', 'btn btn-outline-danger btn-sm', 'Quitar'); quitar.type = 'button'; quitar.setAttribute('aria-label', 'Quitar ' + f.name);
      quitar.addEventListener('click', function () {
        var d = new DataTransfer(); Array.prototype.forEach.call(datos.files, function (x, i) { if (i !== idx) { d.items.add(x); } });
        datos = d; input.files = datos.files; error(''); pintar(); decir(f.name + ' quitado.');
      });
      botones.appendChild(ver); if (!multiple) { botones.appendChild(rep); } botones.appendChild(quitar); t.appendChild(botones);
      /* progreso de lectura local: confirma que el navegador puede leer el archivo completo antes de enviarlo */
      var lector = new FileReader();
      lector.onprogress = function (e) { if (e.lengthComputable) { var p = Math.round(e.loaded * 100 / e.total); prog.value = p; est.textContent = 'Leyendo el archivo… ' + p + ' %'; } };
      lector.onload = function () { prog.value = 100; est.textContent = 'Listo para enviar. Revíselo con «Ver»; el envío mostrará su propia barra de avance.'; };
      lector.onerror = function () { est.textContent = 'No se pudo leer el archivo. Quítelo y vuelva a elegirlo.'; est.className = 'small text-danger'; };
      lector.readAsArrayBuffer(f);
      return t;
    }
    function pintar() {
      liberar(); while (zona.firstChild) { zona.removeChild(zona.firstChild); }
      Array.prototype.forEach.call(input.files || [], function (f, i) { zona.appendChild(tarjeta(f, i)); });
    }
    function incorporar(lista) {
      error('');
      var buenos = []; var msgs = [];
      Array.prototype.forEach.call(lista, function (f) { var m = valido(f); if (m) { msgs.push(m); } else { buenos.push(f); } });
      if (multiple) {
        buenos.forEach(function (f) { var dup = Array.prototype.some.call(datos.files, function (x) { return x.name === f.name && x.size === f.size && x.lastModified === f.lastModified; }); if (!dup) { datos.items.add(f); } });
      } else if (buenos.length) { /* un solo archivo: reemplaza al anterior */
        datos = new DataTransfer(); datos.items.add(buenos[0]);
      }
      input.files = datos.files;
      if (msgs.length) { error(msgs.join(' ')); }
      pintar();
    }
    input.addEventListener('change', function () {
      var lista = Array.prototype.slice.call(input.files || []);
      if (!lista.length) { input.files = datos.files; pintar(); return; } /* se canceló el selector: se conserva lo ya elegido */
      incorporar(lista);
    });
    camara.addEventListener('change', function () { if (camara.files && camara.files.length) { incorporar(camara.files); } });
    pintar();
  }
  if (window.FileReader && window.URL && window.DataTransfer) {
    document.querySelectorAll('input[type=file][data-anexo]').forEach(conectar);
  }

  /* ---------- 3. Consentimiento ---------- */
  var no = document.getElementById('consent-no'); var si = document.getElementById('consent-si');
  var aviso = document.getElementById('consent-aviso');
  function consentimiento() {
    var rechaza = !!(no && no.checked);
    if (aviso) { aviso.hidden = !rechaza; }
    if (rechaza) { decir('Podrá enviar igualmente su solicitud; quedará registrado que no autoriza el tratamiento de sus datos.'); }
  }
  [no, si].forEach(function (r) { if (r) { r.addEventListener('change', consentimiento); } });
  consentimiento();
})();
