/* Marca la página como «cargando» ANTES de pintar: la barra superior de progreso se ve desde el primer instante.
   progreso.js la completa al terminar la carga. Sin scripts en línea (CSP). */
(function () {
  'use strict';
  document.documentElement.classList.add('sgd-cargando');
})();
