# Accesibilidad (WCAG 2.2 AA)

## Elementos implementados

| Elemento | Dónde | Detalle |
|---|---|---|
| **Barra de accesibilidad** | Todas las páginas (`layouts/app.blade.php`, `public/assets/js/a11y*.js`) | Tamaño de texto 87 %–150 % (A−/A/A+ con texto visible), **alto contraste**, **fuente y espaciado legibles** (WCAG 1.4.12), **subrayar enlaces**, **sin animaciones**, **restablecer**. Se guarda solo en `localStorage` del navegador (con *try/catch*), se aplica antes de pintar (`a11y-init.js`, sin parpadeo) y se **anuncia** a lectores de pantalla (`aria-live`). |
| **Enlaces de salto** | Todas las páginas | "Saltar al contenido" y "Saltar a la navegación"; el foco se mueve de verdad a `<main>`. |
| **Foco visible** | `sgd.css` | Contorno de 3 px (4 px cian en alto contraste) en todos los controles; nunca se elimina. |
| **Estructura semántica** | Plantillas Blade | `lang="es-CL"`, un `<h1>` por página, jerarquía de encabezados sin saltos, regiones (cabecera, navegación etiquetada, `main`, pie), `aria-current="page"` en la navegación. |
| **Formularios** | Todas las vistas | Etiqueta asociada a cada control, ids únicos, `autocomplete` donde corresponde, campos de solo lectura rotulados, ayudas con `aria-describedby`. |
| **Resumen de errores** | `layouts/app.blade.php` + `a11y.js` | `role="alert"`, recibe el foco, lista de errores con **enlaces a cada campo**, `aria-invalid` en los campos con error. |
| **Mensajes de estado** | Layout | `role="status"` / `role="alert"` según severidad. |
| **Tablas** | Todas las vistas y manuales | `<th scope="col">`, `<caption>` (oculto visualmente cuando corresponde), sin encabezados vacíos. |
| **Contraste** | `sgd.css` | Tokens verificados ≥ 4,5:1 (prueba automática de contraste); estados con texto además del color; sobreescrituras de colores del framework que no cumplían (`.text-muted`, `.text-danger`, `code`, `kbd`, `btn-outline-secondary`). |
| **Reflow y zoom** | CSS | Sin desborde horizontal a 320 px (verificado) y zoom del texto hasta 150 % desde la barra. Objetivos táctiles ≥ 24 px (WCAG 2.5.8). |
| **Preferencias del sistema** | CSS | `prefers-reduced-motion`, `forced-colors` (alto contraste de Windows), impresión (`@media print`). |
| **Declaración de accesibilidad** | `/accesibilidad` | Estado de conformidad (parcial, honesto), atajos de teclado, lectores de pantalla, limitaciones, **canal para reportar barreras** (`SGD_ACCESIBILIDAD_CONTACTO`). |
| **Manuales accesibles** | `/ayuda`, `resources/manuales/` | Siete manuales por perfil con índice, imágenes con texto alternativo, impresión limpia y versión PDF (`php scripts/build-manuales-pdf.php`). |
| **Editor de documentos** | TinyMCE autoalojado | Idioma español, navegación por teclado (Alt+F10), tirador de redimensión desactivado (no cumplía ARIA). |
| **PDF generados** | `resources/views/pdf/*` | Idioma y título declarados; el QR siempre va acompañado del código y la URL en texto. *No* son PDF etiquetados (ver limitaciones). |

## Cómo se verifica

1. **Pruebas automáticas (PHPUnit)** `tests/Feature/AccesibilidadTest.php`: idioma, título, un `h1`, un `main`, navegación etiquetada, enlaces de salto, sin saltos de encabezados, etiqueta/nombre accesible de cada control, ids únicos, `alt` en imágenes, enlaces con texto, `th scope`, sin encabezados de tabla vacíos, barra de accesibilidad, contraste calculado (fórmula WCAG), resumen de errores, declaración y manuales — sobre **24 páginas representativas de cada rol**.
2. **Auditoría axe-core en navegador real** (Chromium + Playwright; reglas `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa` y `best-practice`): resultado más abajo. Se ejecuta con `scripts/a11y-audit.js` (guion de apoyo; Playwright y axe-core no se distribuyen con la aplicación).
3. **Teclado y preferencias** (navegador real): primer `Tab` = "Saltar al contenido" visible; `Enter` mueve el foco a `main`; contorno de foco visible; las opciones de la barra se activan, se anuncian, **persisten al recargar** y se restablecen; foco en el resumen de errores tras un envío inválido.

## Resultado de la auditoría axe-core (último ciclo)

| Página | Modo | Violaciones |
|---|---|---|
| inicio | normal | 0 |
| ingreso | normal | 0 |
| publico | normal | 0 |
| validar | normal | 0 |
| accesibilidad | normal | 0 |
| ayuda | normal | 0 |
| manual-ciudadano | normal | 0 |
| manual-funcionario | normal | 0 |
| manual-administrador | normal | 0 |
| inicio | alto-contraste+150% | 0 |
| ingreso | alto-contraste+150% | 0 |
| manual-ciudadano | alto-contraste+150% | 0 |
| ciudadano-inicio | normal | 0 |
| ciudadano-nueva | normal | 0 |
| ciudadano-errores | normal | 0 |
| bandeja | normal | 0 |
| vigentes | normal | 0 |
| comunicaciones | normal | 0 |
| admin-usuarios | normal | 0 |
| admin-tramite | normal | 0 |
| admin-plantilla | normal | 0 |
| codificador | normal | 0 |
| diagnostico | normal | 0 |
| seguridad | normal | 0 |

**Total de violaciones: 0.** Hallazgos corregidos durante la auditoría: nombre accesible de los botones de tamaño de texto (WCAG 2.5.3), contraste de `code`, `kbd`, `.text-danger` y botones secundarios heredados del framework, tamaño de objetivos (2.5.8), `<thead>` dañado por una expresión regular del renderizador de manuales, encabezados de tabla vacíos y un `aria-label` inválido del tirador de TinyMCE.

Comprobaciones de comportamiento:
- Primer Tab: {"texto":"Saltar al contenido","visible":true}
- Tras Enter en "Saltar al contenido", foco en: #contenido
- Foco visible (outline) en botón de accesibilidad: solid 3px rgb(255, 176, 0)
- Clases tras activar opciones: a11y-contrast a11y-legible a11y-fs-2 a11y-links
- aria-pressed contraste: true
- Anuncio en región aria-live: ""
- Persistencia tras recargar: a11y-contrast a11y-legible a11y-links a11y-fs-2
- Tras Restablecer: ""
- Reflow 320px inicio: scrollWidth=320 clientWidth=320 OK
- Reflow 320px ingreso: scrollWidth=320 clientWidth=320 OK
- Reflow 320px accesibilidad: scrollWidth=320 clientWidth=320 OK
- Reflow 320px manual-funcionario: scrollWidth=320 clientWidth=320 OK
- Reflow 320px publico: scrollWidth=320 clientWidth=320 OK
- Tras error de validación el foco está en: #resumen-errores ; aria-invalid en #correo: true

Capturas: `docs/capturas/a11y-*.png`.

## Limitaciones (honestas)
- **No** hay auditoría con personas usuarias de lectores de pantalla (NVDA, JAWS, VoiceOver) ni certificación externa; las herramientas automáticas detectan alrededor de un tercio de los problemas posibles. Se recomienda una auditoría manual antes de producción.
- Los **PDF** (comprobante, copias públicas, manuales PDF) no son PDF/UA etiquetados (limitación de Dompdf).
- TinyMCE y ClaveÚnica son componentes de terceros; su accesibilidad no depende de este sistema.
- No se ofrece lectura en voz alta, lengua de señas ni versión en lenguaje claro certificada; los manuales usan lenguaje sencillo pero no han sido validados con personas con discapacidad cognitiva.
- Medios (video/audio) no existen en el sistema.

## Cómo reportar o mejorar
Correo configurable en `SGD_ACCESIBILIDAD_CONTACTO` (aparece en `/accesibilidad`). Para sumar controles a una vista nueva: etiquete cada campo, use un único `<h1>`, `scope` en `<th>` y ejecute `vendor/bin/phpunit tests/Feature/AccesibilidadTest.php` (agregue la página a `paginas()`).
