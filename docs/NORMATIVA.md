# Marco normativo y técnico (resumen)

> Resumen de trabajo para el diseño del SGD. **No es asesoría jurídica.** Todo lo marcado **A VALIDAR** no pudo verificarse contra la fuente oficial en esta construcción (sin acceso a credenciales/ambientes y con fuentes secundarias) y debe confirmarse antes de producción. No se inventaron contratos de API.

## 1. Ley 21.180 de Transformación Digital del Estado y DS 4/2020
- Ley 21.180 (2019): procedimientos administrativos **electrónicos por defecto**, expediente electrónico, interoperabilidad, notificaciones electrónicas (Domicilio Digital Único / Casilla Única), sin papel. Texto: https://www.bcn.cl/leychile/navegar?idNorma=1138479
- **DS 4/2020** (Segpres): reglamento sobre *Transformación digital en la administración*; gradualidad de implementación. https://www.bcn.cl/leychile/navegar?idNorma=1146352 — A VALIDAR vigencia de plazos por organismo.
- Sitio de Gobierno Digital: https://digital.gob.cl · Wikiguías: https://wikiguias.digital.gob.cl

## 2. Normas técnicas (DS 4/2020 y modificaciones)
| Norma técnica | Qué exige (resumen) | Dónde se refleja en el SGD |
|---|---|---|
| **Interoperabilidad** | Intercambio de documentos/expedientes entre organismos; metadatos y formatos estándar. | Exportación JSON de metadatos MGDEE/MGDDE (`/app/expedientes/{id}/exportar`). Interoperabilidad real (PISEE) **no implementada** — A VALIDAR. |
| **Seguridad y ciberseguridad** | Controles mínimos de seguridad de la información (basada en NCh-ISO 27001). | `docs/SEGURIDAD.md`. |
| **Documentos y expedientes electrónicos** | Estructura del expediente, índice, metadatos, formatos, copias, conservación. | Expediente MGDEE, documento MGDDE, índice con SHA-256, copia pública. |
| **Notificaciones** | Notificación electrónica a Casilla Única/correo; evidencia. | `NotificacionService`: estados *pendiente/enviada/fallida* con evidencia. |
| **Calidad y funcionamiento** | Disponibilidad, accesibilidad, usabilidad de plataformas. | WCAG AA básico, framework gob.cl. |
| **Autenticación** | ClaveÚnica como mecanismo de autenticación de personas. | Solo ClaveÚnica (OIDC). |

Las normas técnicas y guías están publicadas en https://digital.gob.cl/transformacion-digital/ y https://wikiguias.digital.gob.cl. Los textos exactos de cada norma técnica **no se transcriben**; su cumplimiento fino es **A VALIDAR** con el equipo jurídico/TI.

## 3. Guía Técnica de Documentos y Expedientes
- **MGDEE** (metadatos del expediente): identificación, descripción, temporalidad, caracterización, relaciones, índice, poderes, seguridad, procedimiento, versión. Implementado en `ExpedienteService::mgdee()` con **nombres de campo propios inspirados en estas categorías — A VALIDAR** contra el esquema oficial vigente.
- **MGDDE** (metadatos del documento): `DocumentoService::mgdde()` — A VALIDAR.
- **Índice electrónico:** lista de archivos/documentos con SHA-256 (`ExpedienteService::indice`).
- **Copia autorizada / copia pública:** PDF con hoja de certificado (tipo, N°/año, fecha, firmantes, SHA-256, código y QR). *Formato PDF/A: no garantizado* (Dompdf genera PDF 1.7; conversión PDF/A es A VALIDAR / ver LIMITACIONES).
- **Enlaces permanentes:** `/validar/{codigo}` (código `SDD-XXXX-XXXX-XXXX`).

## 4. Framework gob.cl
- https://framework.digital.gob.cl · paquete npm `@gobdigital-cl/gob.cl` (v3.0.10, MIT). Basado en **Bootstrap 4**, tipografías **Roboto / Roboto Slab**, color primario **#006fb3**. Se aloja localmente en `public/assets` (sin CDN); las fuentes provienen de `@fontsource`.

## 5. ClaveÚnica (OIDC)
- Documentación: https://claveunica.gob.cl (Portal de integración). Flujo *authorization code*:
  - `GET https://accounts.claveunica.gob.cl/openid/authorize/?client_id&redirect_uri&scope=openid run name&response_type=code&state`
  - `POST https://accounts.claveunica.gob.cl/openid/token/` (client_id, client_secret, redirect_uri, grant_type=authorization_code, code, state)
  - `POST https://www.claveunica.gob.cl/openid/userinfo/` (Bearer) → `RolUnico{numero,DV,tipo}`, `name{nombres[],apellidos[]}`.
- Implementado en `ClaveUnicaService` con endpoints fijos, `state` de 256 bits de un solo uso, sin redirecciones, timeouts. **No probado contra el servicio real** (A VALIDAR campos exactos con credenciales de prueba).

## 6. API FirmaGob v2 (firma electrónica avanzada)
- Guía: https://wikiguias.digital.gob.cl/Manuales/integracion-api-firma · https://firma.digital.gob.cl
- Verificado en fuentes públicas: endpoint `.../firma/v2/files/tickets` (POST); parámetro `token` = JWT firmado (HS256, clave simétrica entregada al registrar la aplicación); cabecera **OTP** solo en firma *atendida*; 5 OTP errados → HTTP 429.
- Según el requerimiento: JWT con `run` (sin DV), `entity`, `purpose`, `expiration` ≤ 30 min (hora de Chile); `api_token_key`; archivos en base64 con checksum SHA-256; máx. 5 MB; ambientes test/prod. **Los nombres exactos de claims/campos de respuesta y las URLs de prod/test están marcados A VALIDAR** (`config/sgd.php`, `HttpFirmaClient`). Hacer pruebas en `test` antes de `prod`.

## 7. CPAT (Catálogo de Procedimientos Administrativos y Trámites)
- https://cpat.gob.cl — formato de código ejemplo `PA-UNI00002-00001`. El mantenedor valida el patrón `^[A-Z]{2}-[A-Z]{3}\d{5}-\d{5}$` (**patrón A VALIDAR** contra todo el catálogo). Los códigos del seed son ejemplos, no reales.

## 8. Casilla Única / Notificador / Domicilio Digital Único
- La notificación electrónica se hace al Domicilio Digital Único (Casilla Única). La notificación **presencial** requiere certificado. El contrato de API para integración **no está validado**: `CasillaAdapter` opera en modo `outbox` (nada se envía; queda *pendiente* hasta registrar evidencia real) y modo `api` genérico — **A VALIDAR**.

## 9. Gmail API con cuenta de servicio y delegación de dominio
- https://developers.google.com/identity/protocols/oauth2/service-account#delegatingauthority · https://developers.google.com/gmail/api/reference/rest/v1/users.messages/send
- JWT RS256 (`iss`=client_email, `sub`=buzón, `scope`=`gmail.send`, `aud`=token_url) → `POST https://oauth2.googleapis.com/token` (`urn:ietf:params:oauth:grant-type:jwt-bearer`) → `POST https://gmail.googleapis.com/gmail/v1/users/me/messages/send` con `raw` base64url. Implementado y probado con cliente simulado; no probado contra Google real.

## 10. Ley 21.663 – Marco de Ciberseguridad
- Texto: https://www.bcn.cl/leychile/navegar?idNorma=1202434 · ANCI: https://www.anci.gob.cl · CSIRT Nacional: https://csirt.gob.cl
- Crea la **ANCI** y el **CSIRT Nacional**; obligaciones para servicios esenciales y OIV (operadores de importancia vital); **reporte de incidentes** con impacto significativo. Los órganos de la Administración del Estado están incluidos como sujetos obligados.
- Plazos de reporte (según fuentes secundarias de estudios jurídicos; **A VALIDAR con la Ley, el reglamento (Res. 295/2024) y la taxonomía (Res. Ex. 7/2025)**): **alerta temprana ≤ 3 horas** desde el conocimiento; **segundo reporte ≤ 72 horas** (24 h para OIV); **informe final ≤ 15 días** (o informe de situación si sigue en curso). El panel `/app/seguridad` registra los hitos pero **no automatiza el reporte**.
- Obligaciones de la Ley 21.663 abordadas: gestión de riesgos, controles técnicos, registro/trazabilidad, reporte de incidentes. Ver correspondencia en `SEGURIDAD.md`.

## 11. PISEE – Plataforma Integrada de Servicios Electrónicos del Estado
- Operada por la Secretaría de Gobierno Digital (Ley 21.658 / DS 4/2020). Según los *Términos y Condiciones de integración y uso PISEE (actualización 2025)* publicados en https://wikiguias.digital.gob.cl, PISEE se compone de conexiones directas y seguras por internet mediante **nodos de interoperabilidad** instalados en la infraestructura de cada organismo (proveedor o consumidor). Los T&C anteriores se aprobaron por Res. Ex. 873 (2023).
- **No se pudo acceder** a los anexos técnicos (protocolo, cabeceras, certificados, catálogo de servicios): en esta construcción los hosts de Gobierno Digital no eran alcanzables. Por eso la integración es un **consumidor genérico** y todo lo específico es configurable y está **A VALIDAR**: URL del nodo (`PISEE_BASE_URL`, https), cabecera/valor de identificación del consumidor, certificado de cliente (mTLS) y CA, y la lista blanca de servicios (ruta, método, parámetro del RUN) en `/app/admin/pisee`.
- Alcance implementado: **consumo** de servicios (no se publican servicios propios como proveedor). Si PISEE exige firmas de mensaje/cabeceras adicionales (p. ej. del tipo X-Road), deben agregarse en `PiseeService::llamar()`.

## 12. Codificador de instituciones y procesos
- Catálogo de Gobierno Digital para identificar de manera normalizada instituciones y procesos del Estado. **No se pudo verificar** la fuente oficial, el formato de los códigos ni una API pública (búsquedas sin resultados oficiales; ver https://digital.gob.cl y https://wikiguias.digital.gob.cl). Por eso el SGD mantiene un **catálogo local** (`instituciones`, `procesos_codificador`) cargable por **CSV** o sincronizable por **API** (forma JSON propuesta, **A VALIDAR**). Los códigos del seed son ejemplos (`DEMO-…`).
- Uso: código de esta institución (`CODIFICADOR_INSTITUCION_CODIGO`) y código de proceso del trámite en los metadatos del expediente; instituciones destinatarias de DocDigital; instituciones proveedoras de servicios PISEE.

## 13. DocDigital – comunicaciones oficiales del Estado
- Plataforma de la Secretaría de Gobierno Digital para cargar, visar, firmar, numerar y distribuir documentos oficiales entre organismos (más de 270 de las 345 municipalidades ya la usaban a inicios de 2025, según Hacienda: https://www.hacienda.cl/noticias-y-eventos/noticias/80-de-los-municipios-del-pais-ya-cuentan-con-docdigital).
- Guía de integración **API Middleware V2** (API MW): https://wikiguias.digital.gob.cl/Manuales/integracion-api-docdigital · documentación de métodos: https://api-doc.digital.gob.cl/api/defs/ · ambiente de pruebas **DemoDoc**. Requisitos según la guía: plataforma que emita y almacene **PDF con Firma Electrónica Avanzada** (no se admiten documentos sin FEA), **Administrador Principal** registrado e interlocutor ante la SGD.
- **No se pudo leer la especificación** de la API en esta construcción: rutas (`DOCDIGITAL_PATH_*`), URLs (`DOCDIGITAL_URL_DEMO/PROD`), token y formas JSON están **A VALIDAR**; el cliente falla cerrado si no están configurados. Modo por defecto `outbox` (no envía; queda pendiente hasta registrar el folio de DocDigital).
