# Botón ClaveÚnica — uso y checklist de certificación (A VALIDAR)

## Dónde está
Un único componente, `resources/views/partials/boton_claveunica.blade.php`, usado en: ingreso (vecino y funcionario), consulta de pagos de proveedores y encabezado del sitio. Leyenda: **«Ingresa con ClaveÚnica»**.

## Botón oficial
Gobierno Digital exige usar **su botón y logo oficiales sin modificarlos**. Este entorno no pudo descargarlos. Para activarlos:
1. Descargue el kit oficial de botones de ClaveÚnica.
2. Copie el SVG como `public/assets/img/claveunica/boton-claveunica.svg` (y opcionalmente `boton-claveunica-blanco.svg` para fondos oscuros).
3. No se toca código: el componente lo detecta y reemplaza el botón de texto provisional.

Mientras no esté el archivo se muestra un botón **de texto** (sin logo, para no imitar el oficial). **Ese botón no sirve para certificar.**

## Checklist (marcar con el kit oficial a la vista)
- [ ] Botón y logo oficiales sin deformar ni recolorear.
- [ ] Texto de la leyenda según las pautas vigentes (hoy: «Ingresa con ClaveÚnica»).
- [ ] Tamaño y área de respeto según las pautas.
- [ ] El botón lleva a `…/ingreso/{ciudadano|funcionario}` y de ahí al flujo OAuth2 de ClaveÚnica (`/claveunica/callback`).
- [ ] URL de callback registrada en la ficha de la aplicación ante ClaveÚnica (A VALIDAR con su ejecutivo).
- [ ] Cierre de sesión ClaveÚnica / cierre de sesión local según las pautas (A VALIDAR).
- [ ] Sin otro acceso con RUN y contraseña en producción (en pruebas existe y vence solo).

## Accesibilidad
Enlace (no botón de formulario), alto táctil ≥ 44 px, foco visible, contraste ≥ 4,5:1 en el botón de texto y texto alternativo «Ingresa con ClaveÚnica» en la imagen oficial; el contexto («como vecino(a)») se anuncia a lectores de pantalla.
