/* «Mis firmas»: selección múltiple y «seleccionar todos» (sin scripts en línea; sin JavaScript la lista funciona con la casilla «todos los pendientes»). */
(function () {
  'use strict';
  document.querySelectorAll('[data-seleccion]').forEach(function (grupo) {
    var filas = Array.prototype.slice.call(grupo.querySelectorAll('input[data-fila]'));
    var todas = grupo.querySelector('input[data-todas]');
    var contador = grupo.querySelector('[data-contador]');
    var todosServidor = grupo.querySelector('input[name="todos"]');
    var boton = grupo.querySelector('button[data-enviar]');
    var maximo = parseInt(grupo.getAttribute('data-max') || '25', 10);
    function actualizar() {
      var n = filas.filter(function (c) { return c.checked && !c.disabled; }).length;
      if (todas) {
        var habil = filas.filter(function (c) { return !c.disabled; });
        todas.checked = habil.length > 0 && n === habil.length;
        todas.indeterminate = n > 0 && n < habil.length;
      }
      if (contador) { contador.textContent = n === 0 ? 'Ningún documento seleccionado' : (n === 1 ? '1 documento seleccionado' : n + ' documentos seleccionados') + (n > maximo ? ' (máximo ' + maximo + ' por lote)' : ''); }
      if (boton) { boton.disabled = (n === 0 && !(todosServidor && todosServidor.checked)) || n > maximo; }
    }
    if (todas) {
      todas.addEventListener('change', function () { filas.forEach(function (c) { if (!c.disabled) { c.checked = todas.checked; } }); actualizar(); });
    }
    filas.forEach(function (c) { c.addEventListener('change', actualizar); });
    if (todosServidor) {
      todosServidor.addEventListener('change', function () { filas.forEach(function (c) { c.disabled = todosServidor.checked || c.hasAttribute('data-bloqueada'); if (todosServidor.checked) { c.checked = false; } }); actualizar(); });
    }
    actualizar();
  });
})();
