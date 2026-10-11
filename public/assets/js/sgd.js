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
    window.tinymce.init({
      target: ta,
      license_key: 'gpl',
      base_url: '/assets/tinymce',
      suffix: '.min',
      language: 'es',
      promotion: false,
      branding: false,
      menubar: false,
      resize: false, /* el tirador de redimensión de TinyMCE no cumple ARIA; la altura es fija y el editor es responsive */
      height: 420,
      plugins: 'lists link table code autolink',
      toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | table link | removeformat | code',
      content_css: '/assets/css/editor-content.css',
      paste_data_images: false,
      convert_urls: false,
      setup: function (ed) { ed.on('change input', function () { ed.save(); }); }
    });
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
          var p = document.createElement('div'); p.innerHTML = last; /* HTML ya sanitizado en servidor; se muestra solo como vista previa */
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
