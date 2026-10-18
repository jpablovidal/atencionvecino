# Manual del funcionario(a)

Para personas con rol **funcionario** (también aplica, con las particularidades de sus manuales, a secretario(a) municipal y alcalde/alcaldesa). Explica la bandeja, el expediente electrónico, los documentos, el flujo de visación y firma, las notificaciones y las comunicaciones oficiales.

> **Requisito:** su RUN debe estar registrado y activo en el sistema por el administrador. Ingrese por **Ingresar con ClaveÚnica → Acceso funcionarios**. Solo verá los expedientes de **su unidad** o aquellos en los que participe (derivación, visación o firma).

## 1. Ingreso y bandeja

Después de ingresar verá su **Inicio** (menú lateral con íconos y un panel con indicadores): arriba, **tarjetas con sus pendientes** (visaciones y firmas, solicitudes por asignar, comunicaciones por gestionar, expedientes abiertos y, si su rol lo permite, trámites publicados y en borrador); luego **Requieren su atención** (cada tarea es una tarjeta que lleva directo al documento o la solicitud), **Accesos rápidos** a los módulos que su rol permite y su lista de expedientes. En el celular el menú se abre con el botón ☰. Contenido del Inicio:

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

Antes de redactar puede revisar los **formatos disponibles para su unidad** en el menú **Formatos y plantillas** (vista previa y PDF de ejemplo). En **Nuevo documento** elija el **tipo** (decreto alcaldicio, decreto SIAPER, oficio, ordenanza, reglamento, instructivo u otro creado por el administrador), una **plantilla** y un **título**. El editor se abre con el texto de la plantilla.

**Campos de combinación** (se reemplazan solos, ya escapados por seguridad): `{{fecha}}`, `{{expediente}}`, `{{folio}}`, `{{solicitante_nombre}}`, `{{solicitante_run}}`, `{{tramite}}`, `{{unidad}}`, y para comunicaciones recibidas `{{institucion_origen}}`, `{{asunto_origen}}`, `{{folio_docdigital}}`.

![Editor de documentos](/assets/manual/08-editor-tinymce.png)

### 4.2 Editar y versiones

Edite el texto con el editor completo (títulos, tipo y tamaño de letra, colores, listas, **tablas**, **imágenes**, enlaces, saltos de página, buscar y reemplazar, pantalla completa). Para insertar una imagen use el botón de imagen → *Cargar* o arrástrela al editor (PNG, JPG o WebP hasta 2 MB; escriba el texto alternativo). Complete resumen, palabras clave, nivel de acceso (**público/reservado**) y **sensible** (un documento sensible nunca aparece en el listado público). Cada **Guardar nueva versión** crea una versión **inmutable** (HTML y PDF con su huella SHA-256), visible en el historial.

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


## Trámites y servicios de su dirección (si su rol lo permite)

Si el administrador le asignó un rol con **Trámites y servicios**, verá ese ítem en el menú. Solo ve y administra los trámites de **su dirección**; los de otras direcciones no existen para usted (aparecen como «no encontrado»).

1. **Nuevo trámite:** escriba el **nombre**, la **descripción** (qué es y quién puede solicitarlo; mínimo 30 caracteres para publicar), las **instrucciones** (opcional) y el **plazo de respuesta** (1 a 365 días).
2. **Documentos o archivos requeridos:** agregue una fila por cada documento que el vecino deberá cargar, con una **ayuda** opcional y la marca **Obligatorio**. Máximo 15. Para quitar uno, deje su nombre en blanco.
3. **Guardar como borrador.** Un borrador no es visible para la ciudadanía. Use **Vista previa** para ver cómo lo verá el vecino y **Historial** para consultar las versiones.
4. **Publicar** (requiere el permiso de publicación): el trámite aparece de inmediato en el **catálogo público** (`/tramites`, visible sin iniciar sesión, con buscador y filtro por dirección) y queda disponible en *Mis solicitudes → Ingresar una nueva solicitud*, agrupado por dirección. Cuando un vecino lo solicita con todos los adjuntos obligatorios, la solicitud llega a su bandeja y usted la toma como siempre (**Asignármela y crear expediente**).
5. **Deshabilitar** deja de aceptar solicitudes nuevas (las ya ingresadas continúan). **Reactivar y publicar** lo vuelve a poner disponible. **Duplicar como borrador** copia un trámite como base de otro.

> Si no tiene permiso de publicación, puede crear y editar borradores, pero no publicarlos ni modificar uno ya publicado (sí puede deshabilitarlo). Los textos se guardan como texto plano: no use HTML ni enlaces.

## DocDigital (comunicaciones oficiales)

El menú **DocDigital** muestra el estado de la conexión con DocDigital y las comunicaciones oficiales de su unidad.

- **Estado:** modo (Outbox, DemoDoc, Producción), servidor, tipo de credenciales (sus valores nunca se muestran) y el resultado de la última sincronización.
- **Recibidas y enviadas:** las últimas 15 de su unidad, con el estado del acuse, los intentos y el último error; cada una lleva a su expediente. La auditoría ve las de todas las unidades.
- **Quien tiene el permiso de gestión** (por defecto, el/la Secretario(a) Municipal y el administrador) puede **Probar conexión** (obtiene el token y consulta la ruta de recibidos, sin leer documentos), **Sincronizar ahora** (descarga lo recibido y crea un expediente por cada comunicación) y **Reintentar envíos fallidos** (hasta 20). El administrador no ve el contenido de las comunicaciones.
- Para **enviar** una comunicación abra el expediente, elija un documento vigente y use *Comunicar por DocDigital*. DocDigital exige un PDF con firma electrónica avanzada.
- Si el modo es **Outbox**, el sistema no se conecta: el envío y el acuse se hacen en DocDigital y aquí se registra la evidencia.

## Requerimientos de transparencia

Si la unidad de Transparencia requiere información a su dirección para responder una solicitud de acceso a la información, verá un aviso en su tablero y el menú **Requerimientos de transparencia**. Cada requerimiento muestra la consulta original (sin datos del solicitante), la instrucción y el plazo. Escriba su **respuesta** (texto plano); si debe entregar documentos, hágalos llegar a Transparencia por los canales institucionales.

## Su tablero de inicio

Al ingresar, **Inicio** muestra su **tablero**: indicadores, sus solicitudes a cargo por etapa (barras) y los plazos de su dirección (vencidas, por vencer y en plazo), además de lo que requiere su atención.

## Pasos, barra de carga y subida de archivos

Los formularios largos (nuevo trámite, registrar microforma) se completan **por pasos**, con revisión final antes de guardar. Toda carga de página muestra una **barra de progreso** superior, y cada subida de archivos (expediente, archivo municipal, comunicaciones) muestra una ventana con el **avance real**, los pasos *Subiendo → Verificando y protegiendo → Listo* y el botón **Cancelar**.

## Casilla Única (notificaciones a la ciudadanía)

El menú **Casilla Única** (si su rol puede notificar) muestra las notificaciones por Casilla Única de los expedientes de su unidad.

- **Estado:** modo (Outbox o API) y tipo de credenciales; los valores nunca se muestran.
- **Pendientes y fallidas:** en modo **Outbox** el sistema no envía nada: haga el envío en Casilla Única y registre aquí el **ID** como evidencia. La notificación queda «enviada» solo con esa evidencia.
- **Probar conexión** y **Reintentar envíos** (hasta 20) los hace quien tiene el permiso de gestión (por defecto, Secretario(a) Municipal y administrador).
- Para notificar: cierre el expediente y use *Notificar al ciudadano* con el canal Casilla Única.

## Si usted también es vecino(a): inicio dual

Al ingresar como funcionario(a) verá el **inicio dual** con dos espacios: **Espacio funcionario** (su trabajo) y **Espacio vecino(a)** (sus propios trámites, con su mismo RUN). Cambie de espacio con **Cambiar de espacio** en el menú o con el botón de su nombre. Para evitar conflictos de interés, **no puede tramitar como funcionario(a) una solicitud que usted presentó como vecino(a)**: la sigue solo desde *Mis solicitudes*; otra persona de la unidad la atiende.

## Archivo municipal: microformas (si su rol lo permite)

El menú **Archivo municipal** aparece solo si su rol tiene permisos `archivo.*`. Hay cuatro funciones, que el administrador asigna con roles personalizados: **ver** (consultar), **gestionar** (registrar y cargar), **certificar** (ministro de fe) y **autorizar disposición**.

1. **Registrar** (gestionar): *Registrar microforma* → título, soporte (microfilm 16/35 mm, microficha o digital), unidad productora, serie, extremos, cantidades y ubicación física. Queda en **borrador** con un código `MF-AAAA-NNNNNN`.
2. **Cargar archivos maestros**: PDF, JPG, PNG o TIFF (máx. 10 MB c/u, hasta 40 por vez). Se cifran y se calcula su **SHA-256**. Si algo falla, no queda nada cargado.
3. **Indexar documentos**: referencia, descripción, fecha, fojas y posición (fotograma) de cada documento contenido.
4. **Enviar a revisión**: fija el conjunto de archivos y lo pasa al ministro de fe.
5. **Certificar** (certificar): verifica la integridad de todos los archivos, pide el **número de acta** y el **acta en PDF** (se informa si trae firma electrónica) y deja la microforma **certificada e inmutable** con el hash del conjunto. **Quien registró la microforma no puede certificarla.** También puede **devolverla a borrador** con un motivo.
6. **Verificar integridad**, **descargar** un archivo y emitir la **constancia de registro (PDF)**: lo puede hacer quien tenga permiso de ver. Cada consulta queda en la **cadena de custodia**.
7. **Disposición de originales** (solo microformas certificadas): quien gestiona la **solicita** con su fundamento (eliminación o transferencia); **otra persona** con permiso de disposición la **autoriza** (con el N.º del acto administrativo) o la rechaza; luego se **registra la ejecución**. El sistema solo registra: no elimina ni traslada nada.

> **Importante:** la constancia del sistema **no reemplaza el certificado legal** de la microforma. La normativa aplicable debe ser validada por la asesoría jurídica (`docs/NORMATIVA.md` §7).

## Firmas electrónicas en los archivos

Junto a cada PDF verá si trae **firma electrónica**: «Firmado electrónicamente», «Sin firma electrónica», «Firmado, con cambios posteriores» o «Firma inválida o documento alterado» (en este caso no confíe en el documento y avise al administrador). *Ver firmas* muestra quién firmó, el emisor del certificado y la fecha. El sistema guarda el archivo tal como llegó, para no invalidar la firma.

## Asistente de redacción (IA) y contenido seguro

Si su institución habilita el asistente, la IA solo propone **texto**: usted decide qué insertar y **debe revisarlo** (normas, nombres, cifras). Si aparece la advertencia «contenía marcado, enlaces o direcciones», revise con especial cuidado: el sistema ya eliminó todo lo ejecutable, pero el texto podría ser engañoso. Lo mismo vale para el contenido que pegue desde otros programas: el sistema elimina scripts, imágenes externas y caracteres invisibles al guardar.

## Plazos en días administrativos
Los plazos de los trámites se cuentan en **días administrativos**: lunes a viernes, sin los feriados que registra la administración. La fecha de vencimiento que ve en las bandejas ya los descuenta.

## Plantillas por secciones
Al crear una plantilla (si su rol lo permite) la redacta en partes: **materia, vistos, considerando, cuerpo** (decreto, reglamento, ordenanza, instructivo u oficio), **firmas** y **cadena de responsabilidad** (cargos que deben visar el documento). Al crear un documento con ella, las **iniciales** de quienes visan y de quien elabora se insertan al pie y el flujo de visación se sugiere con esos cargos. Hay ejemplos de decreto, reglamento, ordenanza, instructivo y oficio para partir (su texto es referencial: valídelo con jurídica).

## Expedientes, documentos y archivos restringidos
Si un expediente contiene datos personales o sensibles, puede **restringirlo** (o solo un documento o archivo) indicando el motivo. Las personas que no estén autorizadas verán «archivo restringido» y no podrán abrirlo; cada intento queda registrado. Autorice solo a quien **necesita conocerlo**, con motivo y, si corresponde, fecha de vencimiento (máximo 1 año), y revoque la autorización cuando deje de ser necesaria. Solo una persona autorizada puede cambiar o levantar la restricción. Administración del sistema no ve el contenido.

## Respuesta final al solicitante
Cuando termine la gestión de una solicitud, en el expediente (sección **Respuesta final al solicitante**) siga tres pasos: (1) elija **uno o más documentos vigentes y archivos** del expediente que se entregarán; (2) elija cómo se notificará —**Casilla Única con copia al correo** (lo normal) o **entrega física** (excepcional, con motivo)—; (3) agregue indicaciones y **confirme que enviará la respuesta final de notificación**. Quien responde (por ejemplo, Oficina de Partes) la ve en su bandeja, sabe qué responder y con qué documentos, y la despacha cuando el expediente está **cerrado**. Puede anular su instrucción mientras no se haya despachado. Los documentos restringidos solo se pueden elegir si usted está autorizado(a).

## Avisos al vecino
El vecino recibe un aviso (Casilla Única y copia al correo) **por cada movimiento** de su solicitud: ingreso, apertura del expediente, derivación, devolución y su respuesta, prórroga, término de la gestión, reapertura, respuesta final en preparación y despachada. Los textos son simples y no incluyen datos internos.

## Asociar otros expedientes y microformas a su expediente
En la sección **Expedientes y microformas asociados** (dentro del expediente abierto) use **Buscar y asociar**: escriba un código, un folio, un trámite o un título y elija **Expedientes** o **Microformas** (estas últimas solo si su rol incluye la consulta del archivo municipal). Solo aparecen los expedientes que usted ya puede ver. Al asociar indique el **motivo**. Luego abra el contenido asociado y **incorpore copias** de los documentos y archivos que necesite: quedan en su expediente con su origen y su huella SHA-256. Lo restringido solo se copia si usted está autorizado(a), y la copia queda restringida. Quitar la asociación no borra lo ya incorporado.

## Decreto de pago (si su rol lo permite)
Desde el expediente de pago use **Crear decreto de pago** (o el menú **Decretos de pago**). Siga tres pasos: (1) elija el expediente de pago y, si corresponde, el **código del expediente del proceso de compra** (queda asociado); (2) ingrese proveedor, RUT, concepto y monto; (3) orden de compra, factura e imputación. Luego, en el decreto: **genere el PDF con la plantilla** (queda como borrador editable y se envía a visación y firma con los firmantes configurados) o **cargue el PDF ya emitido** indicando su número y fecha. Cuando el decreto está firmado queda **emitido** y Tesorería genera el egreso.
