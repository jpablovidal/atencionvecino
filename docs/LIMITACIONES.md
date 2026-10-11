# Limitaciones y cosas no verificadas

**No se presenta como hecho lo que no se probó.**

## Depende de credenciales o ambientes reales (NO probado contra el servicio real)
| Integración | Estado | Qué falta |
|---|---|---|
| **ClaveÚnica** | Implementado (OIDC) y probado con simulación (`/dev/login`, `ClaveUnicaService` sin prueba HTTP real). | `client_id/secret`, URL de retorno registrada; validar formato de `userinfo`. |
| **FirmaGob v2** | `HttpFirmaClient` probado con `Http::fake` (JWT HS256, OTP, checksum, 5 MB, sin redirecciones). | Credenciales y ambiente `test`; **validar URLs de prod/test, nombres de claims y de la respuesta (A VALIDAR)**; verificar firma atendida con OTP real; lógica de reintentos ante 429. |
| **Casilla Única / Notificador** | Modo `outbox` (operativo, sin envío real); modo `api` genérico no basado en contrato oficial. | Contrato y credenciales (A VALIDAR). |
| **Gmail API (cuenta de servicio)** | Transporte probado con cliente HTTP simulado: JWT RS256, único scope, `sub`=buzón, éxito, fallo, reintento, cuota, inyección de cabeceras. | JSON real, delegación de dominio autorizada, envío real. |
| **LLM** | Probado con respuestas simuladas; apagado por defecto. | Clave de API y revisión de la política de uso de IA del municipio. |
| **Códigos CPAT/OAE** | El seed usa **ejemplos** con el formato `PA-UNI00002-00001`. | Cargar los códigos reales del catálogo CPAT. |

## Reglas de firma asumidas
Ver `DECISIONES.md` #1, #2 y #5 (ágil=1 / clásico=2 por defecto; primera firma del decreto alcaldicio por alcalde o delegado; número tras la última firma).

## Técnicas
- **MySQL/MariaDB no disponible** en el entorno de construcción: las pruebas corren sobre **SQLite**. `01_schema.sql` se genera desde las migraciones con gramática MySQL y se verifica sintaxis/estructura por pruebas, pero **no se ejecutó en un servidor MySQL real**: importarlo en un entorno de pruebas antes de producción. La concurrencia del correlativo se probó con procesos paralelos en SQLite; el mismo patrón (UPDATE atómico + bloqueo InnoDB) es válido en MySQL pero **no se probó en MySQL**.
- **Triggers / GRANT por tabla:** pueden no estar disponibles en hosting compartido (cPanel asigna privilegios a nivel de BD completa). Sin ellos, la inmutabilidad de `audit_log` se apoya en el modelo y en la **detección** por cadena HMAC; un atacante con acceso directo a MySQL puede alterar y recalcular la cadena **solo si conoce `AUDIT_HMAC_KEY`** (que no está en la BD).
- **PDF/A y firma PAdES de la copia pública:** la copia pública y los PDF de versión se generan con Dompdf (no PDF/A). Con FirmaGob real el PDF firmado está disponible solo como documento de trabajo; la copia pública incluye la hoja de certificado pero no fusiona la firma PAdES (A VALIDAR si la norma exige copia autorizada con firma visible).
- **Antivirus:** no hay ClamAV; la validación de subidas es estructural. PDF con contenido activo legítimo (formularios con JS) serán rechazados.
- **Archivos en memoria:** se cifra/descifra en memoria (límite 10 MB por archivo, 5 MB para firmar con FirmaGob); `memory_limit` ≥ 256 MB recomendado.
- **TinyMCE:** requiere `style-src 'unsafe-inline'`. Idioma `es` desde `tinymce-i18n` (licencia A VALIDAR).
- **Accesibilidad:** se verificó con revisión manual básica (foco visible, etiquetas, "saltar al contenido", contraste del color primario sobre blanco). **No** se ejecutó una auditoría WCAG automatizada completa (axe/lighthouse) ni pruebas con lector de pantalla.
- **Larastan:** ver `DECISIONES.md` #21.
- **Concurrencia de sesiones, bloqueo de cuentas, rotación automática de claves y reporte automático al CSIRT:** no implementados.
- **Interoperabilidad (PISEE), integración con SIAPER/otros sistemas:** no implementadas.
- **Sesiones `__Host-`:** la cookie requiere HTTPS; en `http://localhost` Chromium la acepta (verificado), otros navegadores pueden no hacerlo.
- El **panel de incidentes** registra hitos pero no envía reportes; plazos legales A VALIDAR.
