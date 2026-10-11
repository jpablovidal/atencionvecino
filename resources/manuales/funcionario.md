# Manual del funcionario(a)

Para personas con rol **funcionario** (también aplica, con las particularidades de sus manuales, a secretario(a) municipal y alcalde/alcaldesa). Explica la bandeja, el expediente electrónico, los documentos, el flujo de visación y firma, las notificaciones y las comunicaciones oficiales.

> **Requisito:** su RUN debe estar registrado y activo en el sistema por el administrador. Ingrese por **Ingresar con ClaveÚnica → Acceso funcionarios**. Solo verá los expedientes de **su unidad** o aquellos en los que participe (derivación, visación o firma).

## 1. Ingreso y bandeja

Después de ingresar verá su **Bandeja**:

- **Mis visaciones y firmas pendientes:** documentos donde es su turno.
- **Solicitudes de mi unidad por asignar:** solicitudes ciudadanas dirigidas a su unidad.
- **Comunicaciones oficiales recibidas por gestionar** (si su unidad es la receptora de DocDigital).
- **Mis expedientes:** los de su unidad o en los que participa.

![Bandeja del funcionario](/assets/manual/06-bandeja.png)

## 2. Tomar una solicitud y crear el expediente

1. En **Solicitudes por asignar** presione **Revisar** para ver los datos, el detalle y los anexos (cada descarga queda registrada).
2. Presione **Asignármela y crear expediente**. El sistema crea el expediente con un **código único** (`EXP-AAAA-NNNNNN`) y sus **metadatos** (identificación, descripción, temporalidad, caracterización, relaciones, poderes, seguridad, procedimiento/CPAT y versión).
3. Desde ahí gestiona todo: documentos, archivos, comentarios, derivaciones y devoluciones.

![Expediente electrónico](/assets/manual/07-expediente.png)

**Editar metadatos:** en *Editar metadatos* puede completar la descripción, la serie documental y el nivel de acceso. **Exportar metadatos (JSON)** descarga los metadatos e índice del expediente.

## 3. Cargar archivos, comentar, derivar y devolver

- **Cargar archivo:** elija un PDF, JPG, PNG o TIFF (máx. 10 MB) y complete **título, resumen, palabras clave, nivel de acceso** y marque **datos sensibles** si corresponde. El sistema rechaza archivos cuyo contenido real no es válido o que traen contenido activo.
- **Comentario u observación:** deja una nota interna en el expediente.
- **Derivar:** elija a cualquier funcionario activo y escriba el motivo; esa persona pasa a ver el expediente.
- **Devolver al ciudadano:** escriba la **observación** (qué debe corregir) y el **plazo (1 a 90 días)**. La solicitud queda "devuelta" hasta que la persona responda (o venza el plazo).

## 4. Documentos del expediente

### 4.1 Crear un documento

En **Nuevo documento** elija el **tipo** (decreto alcaldicio, decreto SIAPER, oficio, ordenanza, reglamento, instructivo u otro creado por el administrador), una **plantilla** y un **título**. El editor se abre con el texto de la plantilla.

**Campos de combinación** (se reemplazan solos, ya escapados por seguridad): `{{fecha}}`, `{{expediente}}`, `{{folio}}`, `{{solicitante_nombre}}`, `{{solicitante_run}}`, `{{tramite}}`, `{{unidad}}`, y para comunicaciones recibidas `{{institucion_origen}}`, `{{asunto_origen}}`, `{{folio_docdigital}}`.

![Editor de documentos](/assets/manual/08-editor-tinymce.png)

### 4.2 Editar y versiones

Edite el texto con la barra del editor (títulos, negrita, listas, tablas, enlaces). Complete resumen, palabras clave, nivel de acceso (**público/reservado**) y **sensible** (un documento sensible nunca aparece en el listado público). Cada **Guardar nueva versión** crea una versión **inmutable** (HTML y PDF con su huella SHA-256), visible en el historial.

> Solo se puede editar un documento en estado **borrador**. Si está en flujo de firma o vigente ya no se edita: si se rechaza, vuelve a borrador.

### 4.3 Definir el flujo de visación y firma

![Flujo de visación y firma](/assets/manual/09-flujo.png)

1. Elija el flujo: **Ágil** (normalmente 1 firma) o **Clásico** (normalmente 2 firmas). El tipo de documento define cuántas firmas exige cada uno.
2. Elija **0 a N visadores** (en orden) y los **firmantes** exigidos.
3. **Reglas importantes:**
   - Una persona **no puede visar y firmar el mismo documento**.
   - Los **decretos alcaldicios** deben ser firmados primero por el **alcalde o por quien tenga una delegación vigente** para ese tipo.
   - Solo pueden firmar personas **habilitadas para firmar** por el administrador.
4. Presione **Iniciar flujo**. Los turnos son **estrictos y en orden**: primero las visaciones y luego las firmas.

### 4.4 Visar y firmar (cuando le toca)

Los documentos que esperan su acción están en **Mis visaciones y firmas pendientes**.

- **Visar:** puede **Visar** (aprobar) o **Rechazar** con un comentario; si rechaza, el documento vuelve a borrador.
- **Firmar:** ingrese el **código OTP de 6 dígitos** de FirmaGob y presione **Firmar**. El OTP **no se guarda**. Si FirmaGob falla, nada avanza y puede reintentar.

![Firma con OTP](/assets/manual/11-firma-otp.png)

Al completar la última firma el sistema asigna el **número correlativo** (por tipo de documento y año, reinicia cada 1 de enero), la **fecha de firma**, el **código de validación** y genera la **copia pública** con su hoja de certificado y QR. Si algo falla al final, aparece **Reintentar finalización**.

![Documento vigente](/assets/manual/12-documento-vigente.png)

> Si ve **"FIRMA SIMULADA – sin validez legal"** está en un ambiente de pruebas.

## 5. Cerrar, reabrir y notificar

1. **Cerrar expediente** cuando termine (no puede haber documentos en flujo).
2. Seleccione **qué documentos notificar** y por **qué canal**: *Casilla Única*, *correo* (aviso con enlace) o *presencial*.
3. Las notificaciones quedan **pendientes** hasta que exista **evidencia**:
   - **Correo:** automática (el ID de mensaje de Gmail); si falla queda **fallida** y se reintenta sola.
   - **Casilla Única / presencial:** registre el **ID de acuse** o el **acta de entrega** en "Evidencia".
4. El ciudadano ve y descarga los documentos solo cuando hay evidencia.
5. Un expediente cerrado puede **reabrirse** indicando el motivo.

## 6. Comunicaciones oficiales por DocDigital

### 6.1 Enviar (despachar) un documento a otra institución
1. Con el documento **vigente** (y con firma electrónica avanzada real), vaya a **Comunicaciones oficiales (DocDigital)** en el expediente.
2. Elija el documento, la **institución destinataria** (catálogo del Codificador) y el **asunto**.
3. En modo **API** se envía solo y reintenta ante fallas. En modo **outbox** queda **pendiente**: envíelo por la plataforma DocDigital y registre el **folio** como evidencia.
4. Puede enviar el mismo documento a otras instituciones (con copia), pero no repetir el mismo destino.

### 6.2 Comunicaciones recibidas
Cada comunicación recibida por DocDigital genera un **expediente** en la unidad receptora. Ver el manual del **secretario(a) municipal** (flujo: acuse de recibo → gestión → respuesta → despacho).

## 7. Consultas a servicios del Estado (PISEE)

Si PISEE está habilitado, en el expediente (abierto) puede **consultar un servicio** del Estado sobre el solicitante. Debe escribir la **finalidad** (obligatoria). Solo se consulta el RUN del solicitante del expediente; el resultado queda **cifrado** en el expediente y la consulta, auditada.

## 8. Asistente de redacción con IA (si está habilitado)

Escriba qué necesita y presione **Generar borrador**. Revise el resultado y presione **Insertar en el documento** solo si es adecuado. El asistente **no guarda ni firma** nada, puede equivocarse y **no debe inventar normas ni numeración** (verá [COMPLETAR]). Antes de enviar el texto se ocultan RUN, correos y teléfonos. Límite: 6 solicitudes por minuto y 30 por día.

## 9. Documentos vigentes

**Documentos vigentes** lista todos los que completaron su flujo, con número, fecha de firma y enlace público de validación.

## 10. Accesibilidad y atajos

Barra superior: tamaño de texto, alto contraste, texto legible, subrayar enlaces, sin animaciones. En el editor: **Alt+F10** va a la barra de herramientas y **Esc** vuelve al texto. Ver **Declaración de accesibilidad**.

## 11. Problemas frecuentes

| Problema | Qué hacer |
|---|---|
| "La solicitud no corresponde a su unidad" | Pídale a la unidad responsable que la tome o al administrador revisar las unidades del trámite. |
| "Esta persona no está habilitada para firmar" | Solicite al administrador habilitar la firma. |
| "Este tipo debe ser firmado por el alcalde o delegado vigente" | Pida al alcalde registrar la delegación o ponga al alcalde como primer firmante. |
| OTP inválido o expirado | Genere un nuevo OTP en FirmaGob; cuidado: 5 errores seguidos bloquean temporalmente. |
| No puedo editar el documento | Está en flujo o vigente; solo se editan borradores. |
| La notificación quedó "fallida" | Se reintenta sola; si persiste avise al administrador (revisión de correo/cola). |
