# Normas técnicas de la Ley 21.180 — matriz de cumplimiento (A VALIDAR)

> **Estado honesto:** el entorno donde se desarrolló el sistema **no pudo abrir** las páginas de las normas ni la documentación del Codificador (red bloqueada). Esta matriz declara **lo que el sistema hace**; **no** afirma cumplimiento de artículos concretos. Cada fila queda **A VALIDAR contra el texto vigente** por quien tenga acceso a las normas.

## Normas indicadas por el municipio
| Norma | Enlace |
|---|---|
| Decreto 7 | https://wikiguias.digital.gob.cl/Normas/Decreto7 |
| Decreto 8 | https://wikiguias.digital.gob.cl/Normas/Decreto8 |
| Decreto 10 | https://wikiguias.digital.gob.cl/Normas/Decreto10 |
| Decreto 11 | https://wikiguias.digital.gob.cl/Normas/Decreto11 |
| Decreto 12 | https://wikiguias.digital.gob.cl/Normas/Decreto12 |

(Los títulos y el alcance de cada decreto **no se citan** aquí porque no pudieron verificarse.)

## Qué hace el sistema (por tema)
| Tema | Control implementado | Dónde | Estado |
|---|---|---|---|
| Identificación única de documentos y expedientes | Identificador por **patrón configurable** que incluye el código de institución del Codificador del Estado; correlativo único por tipo y año | Administración › Codificación y normas | A VALIDAR |
| Metadatos | Metadatos de expediente (MGDEE) y de documento (MGDDE) con esquema `…-A-VALIDAR` | Expediente › Metadatos | A VALIDAR |
| Firma electrónica | Simple (Ley 19.799) y avanzada (FirmaGob); sello SHA-256 y código de validación público | Documento | A VALIDAR |
| Integridad y no repudio | Cadena de auditoría HMAC; archivos cifrados (XChaCha20-Poly1305) con hash por versión | Seguridad | A VALIDAR |
| Versionado y trazabilidad | Versión nueva por cada cambio, con motivo, diferencias y quién editó, visó o firmó | Expediente › Archivos / Historial | A VALIDAR |
| Seguridad de la información | 2FA del administrador, CSP estricta, validación de archivos por contenido, OWASP (docs/PENTEST.md) | Seguridad | A VALIDAR |
| Interoperabilidad | Adaptadores configurables Codificador / PISEE / DocDigital; contratos reales A VALIDAR | APIs e integraciones | A VALIDAR |
| Calidad de plataformas / accesibilidad | Diseño gob.cl y WCAG 2.1 AA verificado con axe en pantallas clave | docs/ACCESIBILIDAD.md | A VALIDAR |

## Cómo cerrar la validación
1. Permitir `wikiguias.digital.gob.cl` y `codificador.digital.gob.cl` en el acceso de red del entorno **o** entregar los textos/capturas.
2. Contrastar el **esquema de identificación** con `Administración › Codificación y normas` y ajustar los patrones (no requiere programación).
3. Contrastar metadatos obligatorios con `ExpedienteService::mgdee` y `DocumentoService::mgdde`.
4. Actualizar esta tabla y `docs/LIMITACIONES.md`.
