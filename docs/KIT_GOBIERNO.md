# Framework kit de Gobierno — cómo lo usa el sitio

Fuente: documento «Framework kit Gobierno» entregado por la municipalidad (basado en Bootstrap 4; paquete npm `@gobdigital-cl/gob.cl`).

## Versión y carga
- El sitio usa `public/assets/css/gob.cl.css`, **idéntica byte a byte a la versión 3.0.10** del paquete npm (la última publicada, 16-12-2020). El documento avisa que el kit «será actualizado»: cuando la mesa de ayuda de Gobierno Digital (digital.gob.cl/mesadeayuda) entregue la versión nueva, se reemplaza ese archivo y los íconos `public/assets/fonts/gob-cl.*` y se revisa esta página.
- **Sin CDN** (decisión de seguridad): CSS, fuentes Roboto y Roboto Slab, íconos, jQuery y Bootstrap JS se sirven desde el propio sitio. No se carga `gob.cl.js` (plugin de jQuery de la barra de accesibilidad y del lector de pantalla de pago): la barra de accesibilidad es propia (`a11y.js`), sin scripts en línea, y mantiene las clases `a11y-contrast` del kit.
- Estructura de página del kit: `header` / `main` / `footer`, `doctype` HTML5 y `meta viewport`.

## Componentes del kit usados
| Componente del kit | Dónde |
|---|---|
| Pie `footer-1` (franja decorativa `.line`, columnas Secciones / Enlaces internos / Atención) | todas las páginas (`partials/pie.blade.php`) |
| Migas `breadcrumb` | ficha, catálogo, mis solicitudes, privacidad, permisos |
| Colapsables `collapsible-flush` | preguntas frecuentes del portal |
| Banners `banner` con íconos `cl-*` | «Otros servicios para el vecino» |
| Botones, formularios, alertas, tarjetas, paginación, tipografía y paleta | en todo el sitio (clases de Bootstrap 4 del kit) |
| Modo contraste `.a11y-contrast` y utilidades `a11y-fonts-*` | barra de accesibilidad y pie |

## Pendiente / no aplicado
- Navbar oficial (`navbar-light/dark`): el sitio mantiene la barra superior oscura tipo intranet y el menú lateral del personal, pedidos por la municipalidad; usan los colores de la paleta del kit.
- Lector de pantalla (Read Speaker): requiere licencia pagada; no incluido.
- Sin JavaScript, las preguntas frecuentes (colapsables de Bootstrap) quedan cerradas salvo la primera.

## Coherencia con otros desarrollos municipales
El portal público y del vecino sigue el estilo del servicio de Firma Electrónica Avanzada (encabezado blanco con franja azul/roja, pasos con círculos) y el área del personal el del Escritorio Municipal (barra oscura, menú lateral, indicadores con cifra azul). Ver decisión 162.
