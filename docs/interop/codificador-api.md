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

## Verificación con la documentación y el listado oficial (22-10-2026)
Documentos considerados: «Selecciona un servicio para consultar» (API), la guía de Postman del Codificador, la ficha web de `PE-MUN-00243` y el **«Listado de códigos de Instituciones»** (CSV, 6.452 filas).

**Dos servidores en la documentación (A VALIDAR cuál es el vigente):**
- Documento de servicios: `https://codificacion.digital.gob.cl/api/v{version}/codigos/{idCodificacion}` (con `/v1` repetido en detalle y jerarquía). Es el predeterminado.
- Guía de Postman: `https://codificador.digital.gob.cl/codigos/1?descripcion=universidad&codigo=uni&padre=0`, sin `/api` ni versión; allí `info_extra` trae `{"sigla","dr","nivel","tipo":"Universidad"}` y `fecha_inicio`/`IdCodificacion` con otra grafía. El cliente usa este estilo de rutas si `CODIFICADOR_API_URL=https://codificador.digital.gob.cl`, y acepta ambas grafías.
- Error: `404 page not found` (texto plano).

**Listado CSV (separado por «;», UTF-8):** `id;codigo;descripcion;fecha_inicio;fecha_fin;idCodificacion;padre;secuencia;fecha_cambio;vigente;afecta_ley_21180;autonomia_operativa;clasificacion_seguimiento;cod_comuna;cod_provincia;cod_region;es_afecto_ley_21180_ltd;grupo_clasif_oae;id_ministerio;indicadores;motivo;nivel;oae_principal;poder_del_estado;seguimiento_ltd;sigla;sigla_relacion;tipo;vigencia_oae`.
- `padre` es el **id numérico** del padre (0 = sin padre); todos los padres existen en el listado.
- Fechas como `2021-12-14 11:39:58.649389 +0000 UTC` y `2999-12-30 21:00:00 -0300 -03` (fin de vigencia «sin término»). `vigente` es `true/false`: hay 115 no vigentes (113 con datos completos). Lo no vigente queda **inactivo** en el catálogo local y no se ofrece como destinatario.
- Tipos: UND 1.220, DEP 800, DIR 781, DRE 424, **MUN 411**, OFI 374, … El tipo es el segmento central del código (`PE-MUN-00243` → `MUN`); hay 64 códigos con otro formato (`PE-SER-1533`, `PE-FFAA-00171`, `PE-EP-00689`) que se aceptan. **Dos filas incompletas** (`PE-undefined-04868` y `PE-undefined-05338`, sin descripción) se omiten y se informan.
- `cod_comuna` viene como número (`5606`): se completa a 5 dígitos (`05606`). Región `05`, provincia `056`.
- OID = `idCodificacion.codigo` (p. ej. `1.PE-MUN-00243`); el listado de la API trae `identificador` vacío, por eso se arma así.

**Municipalidad de Santo Domingo en el listado:** `PE-MUN-00243`, id 243, tipo MUN, nivel 3, grupo B, sigla de relación OA, poder del Estado PE, región 05, provincia 056, comuna 05606, afecta a la Ley 21.180, vigente desde 14-12-2021 hasta 30-12-2999; padre `PE-SUB-00573` (SUBDERE) → `PE-MIN-00526` (Ministerio del Interior). Es la única institución con comuna 5606: **no hay códigos separados** para salud o educación municipal de Santo Domingo (el código por área del sistema sirve para usar otro si el municipio lo obtiene).

