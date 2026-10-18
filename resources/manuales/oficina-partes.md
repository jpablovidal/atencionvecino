# Manual Oficina de Partes

Este manual es para quien **responde a los solicitantes**. Cuando un funcionario termina la gestión de una solicitud, indica la **respuesta final de notificación** y los documentos con los que se responde; usted los ve en **Respuestas finales** y los notifica.

## 1. Qué ve en «Respuestas finales»

Una bandeja con las respuestas **por despachar**. Cada fila muestra la solicitud, el expediente, el solicitante, cuántos documentos se entregan y el canal indicado. Use el filtro para ver las despachadas o buscar por folio, RUN o expediente.

## 2. Responder una solicitud

1. Abra **Responder**. Verá el número de solicitud, el número de expediente, los datos del solicitante, las **indicaciones** de quien gestionó y la **lista de documentos que debe entregar** (puede abrirlos para revisarlos o imprimirlos).
2. Si el expediente aún está **abierto**, quien lo gestiona debe cerrarlo primero; el botón de despacho queda deshabilitado hasta entonces.
3. **Casilla Única con copia al correo (lo normal):** presione **Despachar por Casilla Única**. El sistema crea una notificación por cada documento y envía una **copia del aviso al correo** del solicitante por la Gmail API de Google Workspace (sin adjuntos).
4. **Entrega física (excepcional):** solo si quien gestionó indicó el motivo. Registre el **acta** (quién recibe, fecha y hora) y presione **Registrar entrega física**.

## 3. Evidencia de envío

El sistema **no afirma haber enviado nada sin evidencia**. En el correo la evidencia es el ID de mensaje de Gmail y se guarda sola. En Casilla Única, mientras la conexión esté en modo *outbox*, registre en cada fila el **ID de la notificación** cuando exista el acuse. Si un envío falla, use **Reintentar envíos pendientes o fallidos**. El vecino solo puede descargar un documento cuando su notificación tiene evidencia.

## 4. Anular una instrucción

Si falta un documento o hay un error, anule la instrucción con su motivo (solo antes de despachar). Quien gestionó la solicitud podrá indicar otra.

## 5. Qué recibe el vecino

Un aviso en su Casilla Única con copia a su correo, y en «Mis solicitudes» ve el **número de solicitud**, el **número de expediente**, la **dirección municipal responsable**, la **fecha de cada movimiento** y los documentos de la respuesta. Recibe un aviso **por cada movimiento** de su solicitud.

## 6. Accesibilidad

Todas las pantallas funcionan con teclado y lector de pantalla; use los atajos y el panel de accesibilidad del encabezado.
