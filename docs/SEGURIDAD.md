# Seguridad: matrices OWASP y Ley 21.663

Cada control se **demuestra con pruebas automatizadas** (carpeta `tests/`). `vendor/bin/phpunit` debe pasar completo.

## 1. OWASP Top 10:2025

| ID | Categoría | Controles implementados | Prueba(s) |
|---|---|---|---|
| **A01** | Control de acceso rota | Policies (`ExpedientePolicy`, `DocumentoPolicy`) + middleware de rol. El **admin no lee expedientes**; el auditor es solo lectura; el funcionario solo su unidad o participación (derivación/firma). IDOR: solicitud siempre por `folio` **y** RUN de sesión; archivos verificados contra su expediente; descarga ciudadana solo con notificación *enviada*. Sin SSRF: endpoints externos fijos, sin redirecciones, timeouts. | `SeguridadHttpTest::test_control_de_acceso_expedientes_por_rol`, `test_idor_*`, `ReglasFirmaTest::test_solo_el_alcalde_crea_delegaciones`, `FirmaGobClienteTest::test_no_sigue_redirecciones` |
| **A02** | Configuración de seguridad incorrecta | `APP_DEBUG=false`; páginas de error genéricas; **CSP** `default-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'`; HSTS, `nosniff`, `Referrer-Policy`, `Permissions-Policy`; `Cache-Control: no-store` en contenido autenticado; `.htaccess` (HTTPS, dotfiles, extensiones, `Indexes`). **Falla cerrada en producción** (`ProductionGuard`). | `test_cabeceras_*`, `test_paginas_sin_scripts_en_linea*`, `test_cache_control*`, `test_produccion_falla_cerrada*`, `test_pagina_de_error_generica*`, `test_dotfiles*` |
| **A03** | Fallas en la cadena de suministro | Todo autoalojado (gob.cl, fuentes, TinyMCE, jQuery/Bootstrap; **sin CDN**); dependencias mínimas (HTMLPurifier, Dompdf, chillerlan/php-qrcode; Gmail/FirmaGob/ClaveÚnica con el cliente HTTP de Laravel, sin SDKs); `composer.lock` versionado; `composer audit` limpio; **SBOM** CycloneDX `docs/sbom.cdx.json`. | CSP sin orígenes externos (test), `composer audit` |
| **A04** | Fallas criptográficas | Archivos cifrados con **libsodium XChaCha20-Poly1305**, nonce aleatorio de 24 bytes, **SHA-256 del contenido como datos asociados** y verificación al leer; nombres aleatorios; claves con id y rotación (`FILE_ENCRYPTION_KEYS`); sesión: token de 256 bits (Laravel 40 chars alfanum ≈ 238 bits + `state` ClaveÚnica de 256 bits), solo hash SHA-256 en BD; comparaciones con `hash_equals`; HMAC-SHA256 en auditoría; JWT HS256/RS256 estándar; TLS obligatorio. | `FileVaultTest`, `test_sesion_en_bd_guarda_solo_el_hash*`, `test_login_state*` |
| **A05** | Inyección | Solo Eloquent/Query Builder parametrizado (búsquedas con `LIKE` escapado); Blade con escape automático (único `{!! !!}`: HTML ya sanitizado por HTMLPurifier); **HTMLPurifier** con lista blanca estricta; subidas validadas **por contenido** (firma binaria), solo PDF/JPG/PNG/TIFF, rechazo de `/JavaScript /JS /Launch /EmbeddedFile /RichMedia /OpenAction` (incluida evasión `#hex`), límites de tamaño/cantidad, almacenamiento fuera del docroot, `Content-Disposition` + `nosniff`, rutas validadas por regex (sin path traversal); asunto/destinatario de correo validados contra inyección de cabeceras. | `HtmlSanitizerTest`, `UploadValidatorTest`, `SubidasHttpTest`, `FileVaultTest::test_path_traversal*`, `GmailTransportTest::test_rechaza_inyeccion*`, `test_xss_se_escapa*`, `TramitesYMantenedoresTest::test_campos_de_combinacion_se_escapan` |
| **A06** | Diseño inseguro | Rate limiting (login 10/min, envío 10/min, consulta pública 30/min, firma 10/min, LLM 6/min y 30/día, correo 20/min); límites de campos; **máquinas de estado** (documento: borrador→en_flujo→vigente; no se edita en flujo/vigente); turnos estrictos; idempotencia de `finalizar`; transacciones con rollback (el correlativo se revierte si falla); una persona no puede visar y firmar. | `test_rate_limit*`, `ReglasFirmaTest`, `CorrelativoTest`, `CorrelativoConcurrenciaTest` |
| **A07** | Fallas de autenticación | Solo ClaveÚnica; `state` de un solo uso; **cookie `__Host-sgd_session` `HttpOnly; Secure; SameSite=Lax`**; sesión nueva en login (anti fijación); expiración por **inactividad 30 min** y **absoluta 8 h**; sesiones en BD con hash; **CSRF** obligatorio; funcionario debe existir y estar activo (revalidado en cada petición); OTP de firma nunca almacenado ni registrado. | `test_cookie_de_sesion*`, `test_sesion_expira*`, `test_login_state*`, `test_funcionario_debe_existir*`, `test_usuario_desactivado*`, `test_csrf_obligatorio*`, `ReglasFirmaTest::test_otp_fallido*` |
| **A08** | Fallas de integridad de software y datos | Bitácora **append-only con cadena HMAC-SHA256** (cada registro incluye el hash del anterior; `audit:verify` y panel `/app/seguridad`); bloqueo a nivel de modelo **y de BD** (triggers; GRANT sin UPDATE/DELETE documentado); versiones de documento inmutables (modelo + trigger); SHA-256 por documento verificado al leer; `checksum_original` de FirmaGob verificado contra el enviado. | `AuditoriaTest`, `ReglasFirmaTest::test_versiones_inmutables*`, `FirmaGobClienteTest::test_checksum*` |
| **A09** | Fallas de registro y alertas | Se auditan login/logout/fallos, accesos a expedientes y archivos (marcando sensibles), firmas, CSRF fallidos, accesos denegados (403), errores 500, consultas públicas, cambios de mantenedores, exportaciones, envíos de correo (solo metadatos). Secretos y OTP **redactados**. Panel `/app/seguridad` (admin/auditor) con verificación de cadena e **incidentes** con hitos CSIRT. Verificación nocturna (`audit:verify`). | `AuditoriaTest::test_no_registra_secretos*`, `test_csrf_obligatorio*` |
| **A10** | Manejo inadecuado de condiciones excepcionales | Falla cerrada: si FirmaGob/Gmail/Casilla fallan el flujo **no avanza** ni se pierde estado; `finalizar` reintentable e idempotente; correo → *fallida* con reintentos; mensajes sin detalles internos; limpieza de archivos huérfanos ante rollback. | `ReglasFirmaTest::test_otp_fallido*`, `GmailTransportTest::test_fallo_*`, `FirmaGobClienteTest::test_checksum_distinto_error_http*` |

## 2. OWASP Top 10 for LLM Applications 2025 (asistente de redacción, **apagado por defecto**)

| ID | Riesgo | Control | Prueba |
|---|---|---|---|
| **LLM01** | Inyección de prompt | Contenido del expediente enviado como dato delimitado `<datos_no_confiables>` (se neutraliza cualquier intento de cerrar el delimitador); **sin herramientas**; heurística de inyección con advertencia visible. | `LlmTest::test_contenido_delimitado*` |
| **LLM02** | Divulgación de información sensible | Redacción previa de RUN, correos y teléfonos; se envía solo el texto del borrador (no metadatos, folio ni expediente). | `test_redaccion_previa*` |
| **LLM03** | Cadena de suministro | Una única API HTTPS fija (`api.anthropic.com`), sin plugins ni modelos locales. | `test_contenido_delimitado*` (URL fija) |
| **LLM04** | Envenenamiento de datos/modelo | Sin entrenamiento ni *fine-tuning* con datos ciudadanos. | Diseño (no hay código de entrenamiento) |
| **LLM05** | Manejo inadecuado de salida | La salida se trata como texto no confiable: se **escapa por completo y se sanitiza** (HTMLPurifier); jamás se inserta sola. | `test_salida_no_confiable*` |
| **LLM06** | Agencia excesiva | Sin agencia: requiere acción humana "Insertar"; el asistente nunca guarda ni firma. | `test_el_asistente_no_guarda_nada*` |
| **LLM07** | Filtración del prompt de sistema | El prompt de sistema no contiene secretos ni datos internos. | `test_contenido_delimitado*` (system sin clave) |
| **LLM08** | Debilidades de vectores/embeddings | Sin RAG ni embeddings con datos ciudadanos. | Diseño |
| **LLM09** | Desinformación | Aviso de revisión humana y prohibición de inventar normas/numeración (`[COMPLETAR]`). | `test_contenido_delimitado*` |
| **LLM10** | Consumo desmedido | Tope de entrada (≈6.000 caracteres) y salida (800 tokens), **6/min y 30/día por usuario**, timeouts. | `test_tope_de_entrada`, `test_limite_de_6_por_minuto*` |

## 3. Correspondencia con la Ley 21.663

| Obligación / principio de la ley | Medida en el SGD |
|---|---|
| Gestión de riesgos y medidas de seguridad proporcionales | Esta matriz; mínimo privilegio; falla cerrada. |
| Prevención y resistencia (principio de seguridad por diseño) | CSP estricta, CSRF, validación por contenido, cifrado en reposo. |
| Trazabilidad y registro | Bitácora append-only con cadena HMAC verificable. |
| Detección y respuesta a incidentes | Panel de incidentes, `audit:verify` diario, plan `docs/INCIDENTES.md`. |
| Reporte al CSIRT Nacional/ANCI | Registro de hitos (alerta temprana, 2.º reporte, informe final); **plazos A VALIDAR**; el reporte es manual por el CSIRT/encargado. |
| Protección de datos personales y confidencialidad | Cifrado de archivos, correos sin adjuntos ni datos sensibles, redacción previa en LLM, minimización en logs. |
| Continuidad y recuperación | Plan de respaldo/restauración (README), cola con reintentos idempotentes. |
| Cadena de suministro | SBOM, `composer audit`, sin CDN. |

## 4. Limitaciones conocidas (resumen; ver `LIMITACIONES.md`)
- `style-src 'unsafe-inline'` es necesario para TinyMCE (los **scripts** en línea sí están prohibidos).
- Privilegio `TRIGGER` / `GRANT` por tabla puede no existir en hosting compartido: la inmutabilidad de la BD queda respaldada por el modelo y por la **detección** (cadena HMAC), no por prevención total ante un atacante con acceso directo a MySQL.
- Los archivos se cifran en memoria (máx. 10 MB por archivo).
- El control de `session` ata `last_seen` a la sesión; no hay bloqueo de sesiones concurrentes.
- Sin antivirus/ClamAV (no disponible en hosting compartido): la validación es estructural por firma binaria.
- Ley 21.663: plazos de reporte y obligaciones por tipo de sujeto **A VALIDAR**.
