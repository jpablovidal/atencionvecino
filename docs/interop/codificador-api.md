# API del Codificador (resumen del documento «Selecciona un servicio para consultar»)

Base: `https://codificacion.digital.gob.cl/api/v{version}/…` (consulta pública, sin autenticación).

| Servicio | Petición | Parámetros |
|---|---|---|
| Código (listado) | `GET /v{version}/codigos/{idCodificacion}?codigo={codigo}&desde={desde}&hasta={hasta}&padre={padre}&descripcion={descripcion}` | `version` (plantilla, obligatorio), `idCodificacion` (plantilla, int, obligatorio); consulta opcionales: `codigo` (string), `desde`, `hasta` (string, fechas), `padre` (int), `descripcion` (string) |
| Detalle de un código | `GET /v{version}/v1/codigos/{idcodificacion}/{idcodigo}` | `version`, `idCodificacion`, `idcodigo` (string) |
| Relación jerárquica | `GET /v{version}/v1/codigos/{idcodificacion}/{idcodigo}/jerarquia?{tipovista}&orden={orden}` | `tipovista` (`jsonlist`, `jsontree`, `html`), `orden` (`asc`, `desc`) |
| Código anidado (OID) | `GET …/jerarquia` (el documento repite la misma URL) | — |

Respuesta 200: `{"codigos":[{id, codigo, descripcion, fecha_incio, fecha_fin, idCodificacion, info_extra (JSON en texto), padre, secuencia, fecha_cambio, identificador}]}`.
La jerarquía agrega `hijos`/`Hijos`; el código OID usa `identificador` (p. ej. `30.TEST-INVZ-40`).

**A VALIDAR (inconsistencias del documento):** detalle y jerarquía llevan `/v1` además de la versión (el cliente reintenta sin ese segmento si recibe 404); el cuarto servicio repite la URL de la jerarquía (no se implementó aparte); la respuesta usa `fecha_incio` (se aceptan ambas grafías); el id de la codificación «Instituciones» se supone 1 (configurable).

Municipalidad de Santo Domingo en el Codificador: `PE-MUN-00243` (id 243, OID `1.PE-MUN-00243`, vigente desde 14-12-2021, padre `PE-SUB-00573` Subsecretaría de Desarrollo Regional y Administrativo; tipo MUN, nivel 3, región de Valparaíso, provincia de San Antonio).
