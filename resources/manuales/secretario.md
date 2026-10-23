# Manual del secretario(a) municipal

La Secretaría Municipal cumple tres funciones en el sistema: (1) **visar y firmar** documentos como ministro(a) de fe, (2) operar como **unidad receptora** de las comunicaciones oficiales de DocDigital (configurable por el administrador) y (3) gestionar expedientes como cualquier funcionario(a). Lea también el **Manual del funcionario(a)**, que describe las funciones comunes.

## 1. Visar y firmar documentos

Desde el **Inicio**, en las columnas **Por firmar** y **Por visar**:

1. Abra el documento y revise el texto y el PDF de la versión actual.
2. **Visar** o **Rechazar** (con comentario) si el turno es de visación.
3. **Firmar**: ingrese el OTP de 6 dígitos de FirmaGob. Debe estar **habilitado(a) para firmar** por el administrador.

Cuando es la **segunda firma del flujo clásico** de un decreto alcaldicio, normalmente usted firma después del alcalde (o delegado). Al completarse todas las firmas el documento queda **vigente** con número correlativo, código de validación y copia pública.

> No puede visar y firmar el mismo documento. Los turnos son estrictos.

## 2. Comunicaciones oficiales recibidas (DocDigital)

**Qué ocurre automáticamente:** cada comunicación recibida por DocDigital crea un **expediente** con **identificador único** (`CÓDIGO-INSTITUCIÓN-EXP-AAAA-NNNNNN`) en su unidad. El PDF original queda en el expediente, cifrado, con su huella SHA-256.

### Paso a paso

1. **Detectar:** en la **Bandeja** aparece "Comunicaciones oficiales recibidas por gestionar", y también en el menú **Comunicaciones oficiales**.
2. **Abrir el expediente.** Verá un recuadro azul con el asunto, la institución de origen, el ID de DocDigital y el estado del **acuse de recibo**.
3. **Acusar recibo:**
   - Si DocDigital está en **modo API**: presione **Acusar recibo por DocDigital**.
   - Si está en **modo outbox**: emita el acuse en la plataforma DocDigital y escriba la **referencia** en "Registrar acuse".
   - Hasta que el acuse esté registrado **no se pueden crear documentos de respuesta** (sí asignarse el expediente, comentar o derivar).
4. **Asignarse** el expediente ("Asignarme este expediente") para quedar como responsable.
5. **Gestionar internamente:** comente, cargue antecedentes y **derive** a otras unidades o personas que deban opinar.
6. **Responder (si corresponde):** cree el documento que corresponda — **decreto, oficio, reglamento, instructivo u otro** — con su plantilla y siga el flujo normal de visación y firma. Los campos `{{institucion_origen}}`, `{{asunto_origen}}` y `{{folio_docdigital}}` ayudan a redactar.
7. **Despachar:** con el documento vigente, en **Comunicaciones oficiales (DocDigital)** elija el documento y el **destinatario** (se sugiere la institución de origen, pero puede elegir cualquier otra o enviar con copia a varias). El envío queda enlazado con la comunicación recibida.
8. **Cerrar** el expediente al terminar.

> Un expediente de comunicación recibida **no tiene solicitante ciudadano**, por lo que no se "notifica" a un vecino: la respuesta se despacha por DocDigital.

## 3. Qué ve y qué no ve

Usted ve los expedientes de su unidad y aquellos en los que participa. El administrador **no** puede leer expedientes y el auditor solo tiene lectura.

## 4. Problemas frecuentes

| Problema | Qué hacer |
|---|---|
| No aparecen comunicaciones recibidas | Se sincronizan cada 10 minutos en modo API. Verifique con el administrador que su unidad sea la receptora (`DOCDIGITAL_UNIDAD_RECEPCION`). |
| "Debe acusar recibo ... antes de gestionarla y responderla" | Registre el acuse (paso 3). |
| "DocDigital exige un PDF con firma electrónica avanzada" | El documento no tiene firma real (por ejemplo, fue firmado en ambiente de pruebas). Debe firmarse con FirmaGob real. |
| El envío quedó "fallida" | Use **Reintentar**; si persiste, avise al administrador (credenciales o servicio de DocDigital). |
| Quiero usar otro tipo de documento | El administrador puede crear nuevos tipos y plantillas. |

## Firmar y visar por lote
En **Mis firmas** puede resolver varios documentos a la vez (o todos los pendientes de su turno): marque los documentos, elija el tipo de firma y confirme que los revisó. Con firma avanzada se usa un mismo código OTP para el lote; si el servicio de firma rechaza uno, el lote se detiene y se le informa. La visación por lote solo aprueba: para rechazar, abra el documento.
