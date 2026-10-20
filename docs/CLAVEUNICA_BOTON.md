# Botón y requisitos de certificación de ClaveÚnica (manual 6.5, mayo 2026)

## Botón oficial
Se usa el **kit oficial** de Gobierno Digital (`cu.css`, `cu-blanco.svg` y el marcado de su `index.html`), sin cambiar colores, proporciones ni textos:
- `public/assets/css/claveunica.css` — idéntico a `cu.css` salvo la ruta del ícono (`../icon/cu-blanco.svg` → `../img/claveunica/cu-blanco.svg`; la propia hoja indica que debe ajustarse).
- `public/assets/img/claveunica/cu-blanco.svg` — ícono oficial.
- Un único componente, `resources/views/partials/boton_claveunica.blade.php`, usado en el ingreso (vecino y funcionario), la consulta de pagos de proveedores y el encabezado. Es un **enlace** (`<a class="btn-cu btn-m btn-color-estandar">`) con texto «Iniciar sesión» (parámetro `texto: 'ClaveÚnica'` para la otra versión oficial) y `aria-label` «Iniciar sesión con ClaveÚnica (como vecino(a))». Sobre fondo oscuro usa la variante oficial **alto contraste** (`btn-color-highContrast`).
- Fuente Roboto local (sin CDN); el kit de ejemplo carga Bootstrap y Google Fonts desde CDN, **no** se usan.
- El botón provisional anterior (texto + glifo propio) fue **eliminado**.

## Checklist del manual (§4.1 Requisitos de la certificación)
| Requisito | Estado |
|---|---|
| Uso del botón oficial | Cumple (kit oficial). Falta comparar a ojo con los lineamientos/Figma antes de certificar. |
| HTTPS en producción | Cumple: `ProductionGuard` exige `APP_URL` https. |
| Llamada al formulario a pantalla completa, sin iframe/popup, barra de direcciones visible | Cumple: redirección completa (`redirect()->away`); el sitio nunca se enmarca (`frame-ancestors 'none'`). |
| `state` dinámico | Cumple: 32 bytes aleatorios por intento, de un solo uso, vence a los 10 min, comparado con hash. |
| Scope `openid run name` y endpoints en `accounts.claveunica.gob.cl` | Cumple. **Corregido en esta versión:** `userinfo` apuntaba a `www.claveunica.gob.cl`; ahora `https://accounts.claveunica.gob.cl/openid/userinfo/`. |
| Evidencia de las llamadas a `token/` y `userinfo/` desde el backend | El código está en `app/Services/ClaveUnicaService.php` (URLs en `config/sgd.php`); la captura de pantalla la toma quien certifica. |
| `client_id` y `client_secret` ocultos | Cumple: solo en `.env` (nunca en el código ni el repositorio). |
| Cierre de sesión (botón visible + llamada al logout de ClaveÚnica) | Implementado: «Cerrar sesión» cierra la sesión local y redirige a `https://accounts.claveunica.gob.cl/api/v1/accounts/app/logout?redirect=<Logout URI>`; también al autenticarse un RUN **no habilitado** en el sistema. La CSP permite ese destino solo en `form-action`. **A VALIDAR en sandbox.** |
| Logout URI registrada (obligatoria desde el manual 6.4) | **Debe registrarla usted** al solicitar/actualizar las credenciales; se configura con `CLAVEUNICA_LOGOUT_URI` (por defecto la portada del sitio). Si no está registrada, ClaveÚnica no redirige. |
| Redirect URI | `https://gestor.santodomingo.cl/auth/callback`: solo esquema + autoridad + path, sin query ni IP. La Municipalidad informa que para municipios **no es obligatorio** el dominio «.gob.cl» y que sus credenciales ya están certificadas por Gobierno Digital; el sistema no lo exige ni lo evalúa. |

## Pruebas con sandbox
Las credenciales de sandbox/QA funcionan al instante, solo con RUN de prueba (`44.444.444-4`, `55.555.555-5`, `88.888.888-8`, `99.999.999-9`; contraseña `testing`). Las de producción están bloqueadas hasta certificar (el login muestra «La institución no está habilitada en ClaveÚnica»).

## Accesibilidad
Enlace (no botón de formulario), alto 48 px, foco visible del kit (contorno de 4 px), `aria-label` descriptivo; el texto visible y el ícono van `aria-hidden`.
