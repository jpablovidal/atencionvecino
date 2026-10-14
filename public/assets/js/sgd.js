/* Sin scripts en línea (CSP script-src 'self'). */
(function () {
  'use strict';
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });
  var ta = document.querySelector('textarea[data-editor]');
  if (ta && window.tinymce) {
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var urlSubida = ta.getAttribute('data-upload-url');
    var variables = {};
    try { variables = JSON.parse(ta.getAttribute('data-variables') || '{}'); } catch (e) { variables = {}; }
    var SALTO = '<div style="page-break-after: always;">&nbsp;</div>';
    var cfg = {
      target: ta,
      license_key: 'gpl',
      base_url: '/assets/tinymce',
      suffix: '.min',
      language: 'es',
      promotion: false,
      branding: false,
      resize: false, /* el tirador de redimensión de TinyMCE no cumple ARIA; la altura es fija y el editor es responsive */
      height: parseInt(ta.getAttribute('data-height') || '520', 10),
      plugins: 'advlist anchor autolink charmap code fullscreen help image insertdatetime link lists nonbreaking preview quickbars searchreplace table visualblocks visualchars wordcount',
      menubar: 'edit view insert format table tools help',
      menu: {
        insert: { title: 'Insertar', items: 'image link table charmap hr saltopagina variables | insertdatetime nonbreaking anchor' }
      },
      toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough subscript superscript | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent lineheight | table image link charmap hr | variables saltopagina | searchreplace visualblocks fullscreen preview | removeformat code help',
      toolbar_mode: 'wrap',
      font_family_formats: 'Arial=arial,helvetica,sans-serif; Verdana=verdana,sans-serif; Tahoma=tahoma,sans-serif; Georgia=georgia,serif; Times New Roman=times new roman,times,serif; Courier New=courier new,courier,monospace; Roboto=roboto,arial,sans-serif',
      font_size_formats: '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 24pt 28pt 36pt',
      line_height_formats: '1 1.15 1.3 1.5 2',
      block_formats: 'Párrafo=p; Título 1=h1; Título 2=h2; Título 3=h3; Título 4=h4; Título 5=h5; Título 6=h6; Preformateado=pre',
      table_default_attributes: { border: '1' },
      table_default_styles: { 'border-collapse': 'collapse', width: '100%' },
      table_advtab: true, table_cell_advtab: true, table_row_advtab: true,
      table_appearance_options: true, table_grid: true,
      table_toolbar: 'tableprops tabledelete | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol | tablemergecells tablesplitcells',
      image_title: true, image_description: true, image_dimensions: true, image_advtab: false,
      images_file_types: 'jpeg,jpg,png,webp',
      resize_img_proportional: true,
      object_resizing: 'img,table',
      quickbars_selection_toolbar: 'bold italic | blocks | quicklink',
      quickbars_insert_toolbar: false,
      quickbars_image_toolbar: 'alignleft aligncenter alignright | imageoptions',
      link_default_protocol: 'https',
      link_title: false,
      target_list: false,
      invalid_elements: 'script,iframe,object,embed,form,input,button,select,textarea,video,audio,svg,style,link,meta',
      contextmenu: false,
      browser_spellcheck: true,
      statusbar: true,
      elementpath: false,
      content_css: '/assets/css/editor-content.css',
      paste_data_images: !!urlSubida,
      automatic_uploads: !!urlSubida,
      convert_urls: false,
      relative_urls: false,
      setup: function (ed) {
        ed.on('change input', function () { ed.save(); });
        ed.ui.registry.addIcon('saltopagina', '<svg width="24" height="24" viewBox="0 0 24 24"><path d="M5 3h14v6H5zM5 15h14v6H5z" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M2 12h20" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 2"/></svg>');
        ed.ui.registry.addButton('saltopagina', { icon: 'saltopagina', tooltip: 'Salto de página', onAction: function () { ed.insertContent(SALTO); } });
        ed.ui.registry.addMenuItem('saltopagina', { icon: 'saltopagina', text: 'Salto de página', onAction: function () { ed.insertContent(SALTO); } });
        var claves = Object.keys(variables);
        if (claves.length) {
          var items = claves.map(function (k) { return { type: 'menuitem', text: variables[k] + ' — {{' + k + '}}', onAction: function () { ed.insertContent('{{' + k + '}}'); } }; });
          ed.ui.registry.addMenuButton('variables', { text: 'Variables', tooltip: 'Variables: insertar campo de combinación', fetch: function (cb) { cb(items); } });
          ed.ui.registry.addNestedMenuItem('variables', { text: 'Campos de combinación', getSubmenuItems: function () { return items; } });
        }
      }
    };
    if (urlSubida) {
      cfg.images_upload_handler = function (blobInfo) {
        return new Promise(function (resolve, reject) {
          var fd = new FormData();
          fd.append('file', blobInfo.blob(), blobInfo.filename());
          fetch(urlSubida, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) {
              if (x.ok && x.j.location) { resolve(x.j.location); } else { reject({ message: (x.j && (x.j.error || x.j.message)) || 'No se pudo subir la imagen.', remove: true }); }
            })
            .catch(function () { reject({ message: 'Error de red al subir la imagen.', remove: true }); });
        });
      };
    }
    window.tinymce.init(cfg).then(function (eds) {
      var ed = eds[0];
      var form = ta.closest('form');
      if (ed && form) {
        form.addEventListener('submit', function (e) {
          /* imágenes pegadas (blob:) pendientes de subir: se suben antes de enviar */
          if (urlSubida && ed.getBody().querySelector('img[src^="blob:"],img[src^="data:"]') && !form.__subido) {
            e.preventDefault();
            ed.uploadImages().then(function () { ed.save(); form.__subido = true; form.requestSubmit ? form.requestSubmit() : form.submit(); });
          }
        });
      }
    });
  }
  var selTipo = document.getElementById('tipo_documento_id');
  var selPlantilla = document.getElementById('plantilla_id');
  if (selTipo && selPlantilla) {
    var filtrar = function () {
      Array.prototype.forEach.call(selPlantilla.options, function (o) {
        var t = o.getAttribute('data-tipo');
        var oculto = t !== null && t !== selTipo.value;
        o.hidden = oculto; o.disabled = oculto;
      });
      if (selPlantilla.selectedOptions[0] && selPlantilla.selectedOptions[0].disabled) { selPlantilla.value = ''; }
    };
    selTipo.addEventListener('change', filtrar); filtrar();
  }
  /* Convierte HTML en un fragmento inerte: se analiza con DOMParser (sin cargar recursos ni ejecutar nada) y solo se copian
     etiquetas de texto/tabla permitidas, sin atributos salvo enlaces https y colspan/rowspan. */
  function fragmentoSeguro(html) {
    var permitidas = { P: 1, BR: 1, STRONG: 1, B: 1, EM: 1, I: 1, U: 1, UL: 1, OL: 1, LI: 1, H1: 1, H2: 1, H3: 1, H4: 1, TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TD: 1, TH: 1, BLOCKQUOTE: 1, SPAN: 1, DIV: 1, A: 1, HR: 1 };
    var doc = new DOMParser().parseFromString('<!doctype html><body>' + String(html), 'text/html');
    var frag = document.createDocumentFragment();
    (function copiar(origen, destino) {
      Array.prototype.forEach.call(origen.childNodes, function (n) {
        if (n.nodeType === 3) { destino.appendChild(document.createTextNode(n.nodeValue)); return; }
        if (n.nodeType !== 1) { return; }
        if (!permitidas[n.tagName]) { copiar(n, destino); return; } /* etiqueta no permitida: se conserva solo su texto */
        var e = document.createElement(n.tagName.toLowerCase());
        if (n.tagName === 'A') { var h = n.getAttribute('href') || ''; if (/^https:\/\//i.test(h)) { e.setAttribute('href', h); e.setAttribute('rel', 'noopener noreferrer'); } }
        ['colspan', 'rowspan'].forEach(function (k) { if (n.hasAttribute(k) && /^\d{1,2}$/.test(n.getAttribute(k))) { e.setAttribute(k, n.getAttribute(k)); } });
        copiar(n, e); destino.appendChild(e);
      });
    })(doc.body, frag);
    return frag;
  }
  var llmBtn = document.getElementById('llm-generar');
  if (llmBtn) {
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var out = document.getElementById('llm-salida');
    var ins = document.getElementById('llm-insertar');
    var last = '';
    llmBtn.addEventListener('click', function () {
      var ed = window.tinymce && window.tinymce.activeEditor;
      var borrador = ed ? ed.getContent({ format: 'text' }) : '';
      out.textContent = 'Generando…';
      fetch(llmBtn.getAttribute('data-url'), {
        method: 'POST', credentials: 'same-origin', redirect: 'error',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
        body: JSON.stringify({ instruccion: document.getElementById('llm-instruccion').value, borrador: borrador.slice(0, 5000) })
      }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
          if (!x.ok) { out.textContent = x.j.error || 'Error'; ins.hidden = true; return; }
          last = x.j.html;
          out.textContent = '';
          var p = document.createElement('div'); p.appendChild(fragmentoSeguro(last)); last = p.innerHTML; /* servidor y cliente sanean: doble barrera */
          out.appendChild(p);
          var a = document.createElement('p'); a.className = 'text-danger small'; a.textContent = (x.j.advertencias || []).join(' ');
          out.appendChild(a);
          ins.hidden = false;
        }).catch(function () { out.textContent = 'El asistente no está disponible.'; });
    });
    ins.addEventListener('click', function () {
      if (window.tinymce && window.tinymce.activeEditor && last) { window.tinymce.activeEditor.insertContent(last); }
    });
  }
})();

/* Menú lateral (escritorio: se oculta/muestra; móvil: cajón con fondo) y desplegables de la barra superior (sin scripts en línea). */
(function () {
  'use strict';
  var layout = document.querySelector('.sgd-layout.con-lateral');
  var burger = document.getElementById('sgd-burger');
  var fondo = document.getElementById('sgd-backdrop');
  var movil = window.matchMedia('(max-width: 991px)');
  if (layout && burger) {
    document.querySelectorAll('.sgd-sidebar details.sgd-nav-grupo').forEach(function (d) { d.open = true; });
    var K = 'sgd_lateral';
    var oculto = false;
    try { oculto = window.localStorage.getItem(K) === '0'; } catch (e) { oculto = false; }
    var abierto = false;
    var pintar = function () {
      if (movil.matches) {
        layout.classList.remove('lateral-oculto');
        layout.classList.toggle('lateral-abierto', abierto);
        burger.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        if (fondo) { fondo.hidden = !abierto; }
      } else {
        abierto = false;
        layout.classList.remove('lateral-abierto');
        layout.classList.toggle('lateral-oculto', oculto);
        burger.setAttribute('aria-expanded', oculto ? 'false' : 'true');
        if (fondo) { fondo.hidden = true; }
      }
    };
    pintar();
    var cambio = function () { pintar(); };
    if (movil.addEventListener) { movil.addEventListener('change', cambio); } else if (movil.addListener) { movil.addListener(cambio); }
    burger.addEventListener('click', function () {
      if (movil.matches) {
        abierto = !abierto; pintar();
        if (abierto) { var a = layout.querySelector('.sgd-sidebar a'); if (a) { a.focus(); } }
      } else {
        oculto = !oculto; pintar();
        try { window.localStorage.setItem(K, oculto ? '0' : '1'); } catch (e) { /* sin almacenamiento */ }
      }
    });
    if (fondo) { fondo.addEventListener('click', function () { abierto = false; pintar(); burger.focus(); }); }
    layout.querySelectorAll('.sgd-sidebar a').forEach(function (a) { a.addEventListener('click', function () { if (movil.matches) { abierto = false; pintar(); } }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && abierto) { abierto = false; pintar(); burger.focus(); } });
  }
  var desplegables = document.querySelectorAll('details.a11y-mas, details.sgd-user');
  document.addEventListener('click', function (e) {
    desplegables.forEach(function (d) { if (d.open && !d.contains(e.target)) { d.open = false; } });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { desplegables.forEach(function (d) { if (d.open) { d.open = false; var s = d.querySelector('summary'); if (s) { s.focus(); } } }); }
  });
})();
