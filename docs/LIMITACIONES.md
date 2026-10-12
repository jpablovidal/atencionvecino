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

## Interoperabilidad (PISEE, Codificador, DocDigital) — **no verificada contra los servicios reales**
- **Sin acceso a las especificaciones oficiales** (los hosts de Gobierno Digital no eran alcanzables desde el entorno de construcción; solo se pudieron consultar resúmenes de búsqueda). Los contratos no se inventaron: son configurables y están marcados **A VALIDAR** en el código (`HttpDocDigitalClient`, `PiseeService`, `CodificadorService::sincronizar`) y en `docs/NORMATIVA.md` §11–13.
- **PISEE:** solo consumidor, con mTLS y una cabecera de identificación configurable. Si el nodo exige otro mecanismo (firma de mensajes, cabeceras adicionales, SOAP, etc.), hay que adaptar `PiseeService::llamar()`. Requiere adhesión a los T&C PISEE y un nodo instalado en la municipalidad. No se publican servicios propios como proveedor.
- **Codificador:** catálogo local; formato de códigos y esquema de la API no validados. La vía confiable hoy es la **carga CSV**.
- **DocDigital:** rutas/URLs/token y formas JSON a validar en **DemoDoc**; requiere Administrador Principal e interlocutor ante la SGD. Solo se envían PDF con FEA real (`pdf_firmado_*`, devuelto por FirmaGob); la numeración propia de DocDigital no se integra con el correlativo del SGD. No se implementa visación/firma dentro de DocDigital ni acuse de recepción/lectura (solo el ID de envío).
- La sincronización de recibidos asume que la API entrega el PDF en base64 en la lista (forma A VALIDAR); los entrantes no crean expedientes automáticamente: quedan en la bandeja de la unidad receptora (`DOCDIGITAL_UNIDAD_RECEPCION`).

- **Recepción (expediente por comunicación entrante):** depende de que la API de DocDigital entregue el PDF en la lista de recibidos y de un endpoint de **acuse de recibo** (`DOCDIGITAL_PATH_ACUSE`, forma A VALIDAR). El acuse a través de la API no se probó contra DocDigital real; en `outbox` es una referencia manual. No se interpreta el contenido de la comunicación (sin clasificación automática ni plazos de respuesta). El identificador único usa el código de institución del Codificador (A VALIDAR el esquema oficial).

- **Google Drive (unidad compartida):** probado solo contra un **Drive simulado** (`tests/Support/FakeDrive.php`) que reproduce el contrato de la API v3 (`supportsAllDrives`, carpetas, md5). **No se ha probado contra Google real**: valide con *Probar conexión* y una migración piloto. Dependencia: disponibilidad/cuotas de Drive; las escrituras fallan cerradas.
- **Cifrado + Drive:** si se pierden las claves de `sgd_secrets`, los archivos en Drive no se pueden recuperar. El respaldo de claves es responsabilidad de la institución.
- **Firmas en la copia pública:** el sello visual (estilo/posición) se estampa en la copia pública. **No** reemplaza la firma electrónica avanzada: el PDF firmado por FirmaGob conserva su propia representación y sus coordenadas en el proveedor son **A VALIDAR**.
- **Secretos en BD (opcional):** `SGD_API_SECRETOS_EN_BD=1` guarda secretos cifrados con `APP_KEY`; quien obtenga BD **y** `.env` los descifra. Por defecto está apagado y los secretos viven solo en `.env`/`sgd_secrets`.
- **Roles personalizados:** el techo por rol base y los permisos fijos del sistema son una decisión de diseño; no existen permisos por expediente ni por unidad (la visibilidad por unidad sigue las reglas de acceso existentes).
- **Usuarios conectados:** se basa en la tabla de sesiones (actividad dentro del tiempo de vida de sesión); no detecta usuarios con la pestaña abierta pero inactiva más allá de ese plazo.
- **Log de aplicación:** la redacción es por patrones (RUN, correos, tokens, rutas); puede dejar pasar datos con formatos no previstos. Revíselo antes de compartirlo fuera de la institución.
- **Logo y accesibilidad:** el sistema exige texto alternativo y valida contraste de colores, pero no puede juzgar si el contenido del logo es legible.

## Accesibilidad
Ver `docs/ACCESIBILIDAD.md`: pruebas automáticas (PHPUnit y axe-core en navegador real, 0 violaciones en las páginas auditadas) y revisión de teclado, **pero sin auditoría manual con lectores de pantalla ni certificación externa**; los PDF generados no son PDF/UA; el canal de contacto y su plazo de respuesta (`SGD_ACCESIBILIDAD_CONTACTO`) deben definirse por la Municipalidad.
