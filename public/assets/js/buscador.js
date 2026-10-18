/* Buscador de trámites con sugerencias (combobox ARIA 1.2). Sin scripts en línea (CSP); sin JavaScript sigue siendo una búsqueda normal. */
(function () {
  'use strict';
  document.querySelectorAll('input[data-buscador]').forEach(function (inp) {
    var lista = document.getElementById(inp.getAttribute('aria-controls'));
    var estado = document.getElementById(inp.id + '-estado');
    var url = inp.getAttribute('data-url');
    if (!lista || !url || !window.fetch) { return; }
    var items = []; var activo = -1; var timer = null; var ultima = '';
    function cerrar() { lista.hidden = true; inp.setAttribute('aria-expanded', 'false'); inp.removeAttribute('aria-activedescendant'); activo = -1; }
    function marcar(i) {
      activo = i;
      items.forEach(function (li, k) { li.setAttribute('aria-selected', k === i ? 'true' : 'false'); li.classList.toggle('activo', k === i); });
      if (i >= 0) { inp.setAttribute('aria-activedescendant', items[i].id); items[i].scrollIntoView({ block: 'nearest' }); } else { inp.removeAttribute('aria-activedescendant'); }
    }
    function pintar(datos, q) {
      lista.textContent = ''; items = [];
      datos.forEach(function (d, k) {
        var li = document.createElement('li'); li.id = inp.id + '-op' + k; li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
        var a = document.createElement('a'); a.href = d.url; a.tabIndex = -1;
        var t = document.createElement('strong'); t.textContent = d.nombre; var s = document.createElement('small'); s.textContent = d.direccion || '';
        a.appendChild(t); a.appendChild(s); li.appendChild(a);
        li.addEventListener('mousedown', function (e) { e.preventDefault(); window.location.href = d.url; });
        lista.appendChild(li); items.push(li);
      });
      if (!datos.length) { var v = document.createElement('li'); v.setAttribute('role', 'presentation'); v.className = 'vacio-sug'; v.textContent = 'Sin coincidencias: presione Buscar para ver el catálogo completo.'; lista.appendChild(v); }
      lista.hidden = false; inp.setAttribute('aria-expanded', 'true'); activo = -1;
      if (estado) { estado.textContent = datos.length ? datos.length + (datos.length === 1 ? ' sugerencia disponible' : ' sugerencias disponibles') + '. Use las flechas para elegir.' : 'Sin sugerencias.'; }
    }
    function pedir() {
      var q = inp.value.trim();
      if (q.length < 2) { cerrar(); ultima = ''; return; }
      if (q === ultima) { return; }
      ultima = q;
      fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (d) { if (inp.value.trim() === q) { pintar(Array.isArray(d) ? d : [], q); } })
        .catch(function () { cerrar(); });
    }
    inp.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(pedir, 180); });
    inp.addEventListener('keydown', function (e) {
      if (lista.hidden || !items.length) { if (e.key === 'ArrowDown' && inp.value.trim().length >= 2) { ultima = ''; pedir(); } return; }
      if (e.key === 'ArrowDown') { e.preventDefault(); marcar((activo + 1) % items.length); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(activo <= 0 ? items.length - 1 : activo - 1); }
      else if (e.key === 'Enter' && activo >= 0) { e.preventDefault(); window.location.href = items[activo].querySelector('a').href; }
      else if (e.key === 'Escape') { cerrar(); }
    });
    inp.addEventListener('blur', function () { window.setTimeout(cerrar, 120); });
    inp.addEventListener('focus', function () { if (items.length && inp.value.trim().length >= 2) { lista.hidden = false; inp.setAttribute('aria-expanded', 'true'); } });
  });
})();
