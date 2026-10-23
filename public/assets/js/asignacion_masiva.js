/* Asignación masiva: selección, paneles por acción, listas dependientes y búsqueda de permisos. Sin código en línea (CSP). */
(function () {
  'use strict';
  var f = document.querySelector('[data-masiva]');
  if (!f) { return; }
  var accion = f.querySelector('[data-masiva-accion]');
  var cuenta = f.querySelector('[data-masiva-cuenta]');
  var boton = f.querySelector('[data-masiva-aplicar]');
  var todos = f.querySelector('[data-masiva-todos]');
  var filas = [].slice.call(f.querySelectorAll('[data-masiva-fila]'));
  var alcance = [].slice.call(f.querySelectorAll('input[name="alcance"]'));

  function marcadas() { return filas.filter(function (c) { return c.checked; }).length; }
  function alcanceFiltro() { return alcance.some(function (r) { return r.checked && r.value === 'filtro'; }); }
  function refrescar() {
    var n = marcadas();
    cuenta.textContent = n + (n === 1 ? ' marcada' : ' marcadas');
    boton.disabled = !accion.value || (!alcanceFiltro() && n === 0);
    filas.forEach(function (c) { var tr = c.closest('tr'); if (tr) { tr.classList.toggle('masiva-sel', c.checked); } });
    if (todos) { todos.checked = filas.length > 0 && n === filas.length; }
  }
  function panel() {
    [].forEach.call(f.querySelectorAll('[data-accion-panel]'), function (p) {
      var activo = p.getAttribute('data-accion-panel') === accion.value;
      p.hidden = !activo;
      [].forEach.call(p.querySelectorAll('input,select'), function (i) { i.disabled = !activo; });
    });
    refrescar();
  }
  function hijos() {
    var un = f.querySelector('[data-masiva-unidad]');
    if (!un) { return; }
    [].forEach.call(f.querySelectorAll('[data-masiva-hijo]'), function (s) {
      var vacio = true;
      [].forEach.call(s.options, function (o) {
        if (!o.value) { return; }
        var ok = o.getAttribute('data-unidad') === un.value;
        o.hidden = !ok;
        if (!ok && o.selected) { s.value = ''; }
        if (ok) { vacio = false; }
      });
      s.disabled = vacio || s.closest('[data-accion-panel]').hidden;
    });
  }

  accion.addEventListener('change', function () { panel(); hijos(); });
  f.addEventListener('change', function (e) {
    var t = e.target;
    if (t === todos) { filas.forEach(function (c) { c.checked = todos.checked; }); }
    if (t.matches('[data-masiva-unidad]')) { hijos(); }
    refrescar();
  });
  f.addEventListener('input', function (e) {
    if (!e.target.matches('[data-masiva-buscar]')) { return; }
    var q = e.target.value.trim().toLowerCase();
    [].forEach.call(f.querySelectorAll('[data-perm-texto]'), function (d) { d.hidden = q !== '' && d.getAttribute('data-perm-texto').indexOf(q) === -1; });
    [].forEach.call(f.querySelectorAll('.masiva-grupo'), function (g) { g.hidden = !g.querySelector('.masiva-perm:not([hidden])'); });
  });
  f.addEventListener('submit', function (e) {
    var n = alcanceFiltro() ? 'todas las personas que coinciden con el filtro' : marcadas() + ' persona(s)';
    if (!window.confirm('¿Aplicar «' + accion.options[accion.selectedIndex].text + '» a ' + n + '? Cada cambio queda registrado en la bitácora.')) { e.preventDefault(); }
  });
  panel(); hijos();
})();
