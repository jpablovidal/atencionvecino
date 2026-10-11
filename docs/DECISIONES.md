# Decisiones de diseño y supuestos

Ante ambigüedad se eligió la opción **más segura** y se documentó aquí.

| # | Tema | Decisión | Motivo |
|---|---|---|---|
| 1 | Reglas de firma | Cada tipo de documento parametriza `firmas_agil` (por defecto 1) y `firmas_clasico` (por defecto 2). El requerimiento dice "decreto alcaldicio: 1 firma" y a la vez "1 firma en flujo ágil y 2 en clásico": se interpretó que **1 es la firma del flujo ágil** y el clásico agrega una segunda firma (p. ej. secretario municipal como ministro de fe). Si el municipio quiere 1 firma también en clásico, basta editar el tipo en el mantenedor. | Parametrizable, sin recompilar. |
| 2 | Firma del alcalde | `firma_alcalde=1` exige que la **primera firma** sea del alcalde titular o de quien tenga delegación **vigente y activa** para ese tipo; se revalida al momento de firmar. | Evita firmar con delegación vencida. |
| 3 | Solo el alcalde crea delegaciones | Ruta protegida por rol `alcalde`; admin/auditor solo ven. | Requisito. |
| 4 | Firma simulada | `FIRMAGOB_MODE=mock` marca el paso (`simulada=1`), muestra "FIRMA SIMULADA – sin validez legal" en la hoja de certificado y la validación pública; **la app no arranca en producción con mock**. | Requisito. |
| 5 | Documento firmado | Con FirmaGob real, el PDF devuelto reemplaza al PDF a firmar para la siguiente firma (cadena). Al finalizar se genera la **copia pública** (HTML + hoja de certificado) como PDF propio; el PDF firmado por FirmaGob no se fusiona con la hoja (sin FPDI). El número correlativo se asigna **al completar todas las firmas**, por lo que no aparece dentro del PDF firmado por FirmaGob, sí en la copia pública. | Evita dependencias; el número solo existe tras la última firma. |
| 6 | Correlativo | `UPDATE … SET ultimo = ultimo+1` bajo la misma transacción de finalización (bloqueo de fila InnoDB hasta el `COMMIT`) tras `INSERT IGNORE` de la fila `(tipo, año)`. Año calculado en `America/Santiago`. Probado con 6 procesos paralelos sobre BD real. | Portable MySQL/SQLite, sin huecos si falla la transacción. |
| 7 | Código de validación | 12 símbolos de un alfabeto de 32 (5 bits) = **60 bits**; formato `SDD-XXXX-XXXX-XXXX`. | Requisito. |
| 8 | Zona horaria en BD | Conexión MySQL con `timezone '+00:00'`: se guardan las horas de Chile "tal cual". Los cambios de horario de verano tienen una hora ambigua al año; el correlativo no depende de ello (se usa el año calendario de Chile). | Evita conversiones silenciosas. |
| 9 | Esquema SQL | `01_schema.sql` se **genera** desde las migraciones (gramática MySQL en modo *pretend*) y una prueba falla si hay deriva. Sin servidor MySQL en el entorno de construcción, la ejecución real del SQL en MySQL/MariaDB **no pudo probarse** (se probó el seed en SQLite). | Consistencia garantizada. |
| 10 | Referencia circular | `solicitudes.expediente_id` es un índice sin FK (la FK circular con `expedientes.solicitud_id` no es portable). | Portabilidad. |
| 11 | Notificaciones | Un documento solo es descargable por el ciudadano si existe una notificación **enviada** (con evidencia) para él. Casilla Única `outbox` y presencial quedan *pendientes* hasta que un funcionario registre evidencia. El correo registra el **ID de mensaje de Gmail** (evidencia de *envío*, no de lectura/recepción). | "Nunca afirmar lo que no se envió". |
| 12 | Correo sin datos | El correo contiene folio, estado y enlace; **sin adjuntos ni RUN ni detalle**. | Requisito. |
| 13 | Roles | `admin` gestiona mantenedores/seguridad pero **no** lee expedientes (ni exporta); `auditor` lee todo y no modifica; `alcalde` y `secretario` operan como funcionarios con su unidad. Admin/auditor no pueden habilitarse para firmar. | Mínimo privilegio. |
| 14 | Participación | Un usuario ve un expediente si es de su unidad, es responsable, recibió una derivación o es visador/firmante de un documento del expediente. | Requisito. |
| 15 | Eliminación | Sin borrado físico en mantenedores (solo `activo`). Versiones de documento y bitácora inmutables. | Requisito. |
| 16 | Plazo de devolución | 1–90 días; vence al final del día; vencida no admite respuesta (el funcionario debe devolver nuevamente). | Supuesto. |
| 17 | Sesión | Driver propio `sgd_database` que guarda `SHA-256(id)`; el id de Laravel es de 40 caracteres alfanuméricos (≈238 bits). | Cercano a 256 bits sin reescribir el `Store`. |
| 18 | CSP | `style-src 'self' 'unsafe-inline'` por TinyMCE; scripts solo `'self'`. | Compatibilidad con el editor. |
| 19 | LLM | Proveedor único `api.anthropic.com`; modelo configurable (`LLM_MODEL`). Salida convertida a párrafos de texto escapado + sanitizado. | LLM03/LLM05. |
| 20 | PDF/A | No se garantiza PDF/A (Dompdf). Se deja como **A VALIDAR**/mejora. | Sin dependencia pesada. |
| 21 | Larastan | No pudo instalarse vía Composer en el entorno de construcción (restricciones de red); el análisis se ejecutó con `phpstan.phar` + Larastan clonado (nivel 5, 0 errores). `phpstan.neon` queda listo. | Limitación de entorno. |
| 22 | TinyMCE | v7 GPL autoalojada (`license_key: 'gpl'`), paquetes de idioma `es` de `tinymce-i18n` (licencia A VALIDAR). | Sin CDN. |
