# Manual del funcionario(a)

Para personas con rol **funcionario** (también aplica, con las particularidades de sus manuales, a secretario(a) municipal y alcalde/alcaldesa). Explica la bandeja, el expediente electrónico, los documentos, el flujo de visación y firma, las notificaciones y las comunicaciones oficiales.

> **Requisito:** su RUN debe estar registrado y activo en el sistema por el administrador. Ingrese por **Iniciar sesión** (ClaveÚnica): el sistema reconoce si usted figura como funcionario(a) en el directorio institucional; no hay un acceso aparte. Si lo prefiere, también puede entrar con su **usuario y contraseña** del directorio institucional (enlace «Ingresar con usuario y contraseña» en la página de ingreso; no sirve para la administración). Solo verá los expedientes de **su unidad** o aquellos en los que participe (derivación, visación o firma).

## 1. Ingreso y bandeja

Después de ingresar verá su **Inicio** (menú lateral con íconos y un panel con indicadores): arriba, **tarjetas con sus pendientes** (visaciones y firmas, solicitudes por asignar, comunicaciones por gestionar, expedientes abiertos y, si su rol lo permite, trámites publicados y en borrador); luego **Requieren su atención** (cada tarea es una tarjeta que lleva directo al documento o la solicitud), **Accesos rápidos** a los módulos que su rol permite y su lista de expedientes. En el celular el menú se abre con el botón ☰. Contenido del Inicio:

- **Por firmar y Por visar:** en el Inicio, los documentos donde es su turno aparecen en **dos columnas separadas** (con contador y acceso por lote); el detalle y la firma por lote están en *Mis firmas y visaciones*.
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

Los documentos que esperan su acción están en el Inicio, separados en **Por firmar** y **Por visar**.

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

**Documentos vigentes** lista **todos** los documentos que completaron su flujo (decretos, oficios y demás), de todas las direcciones, en una tabla con **N°, fecha, tipo, dirección, materia, tipo de materia, macrotema, expediente** y el botón **Ver aquí**. Por defecto muestra el **año actual**.

- **Filtros:** búsqueda de texto (materia, N°, expediente, palabras clave), **año** (o «Todos»), **tipo de documento** (*Decretos*, *Oficios* o un tipo en particular), dirección, tipo de materia, macrotema, rango de fechas de firma y cantidad por página. «Quitar filtros» vuelve al año actual.
- **Orden:** haga clic en el título de cualquier columna para ordenar de forma ascendente o descendente.
- **Acceso:** ver que un documento existe no da acceso a su contenido. «Ver aquí» aparece solo si usted puede abrirlo; los documentos con datos sensibles de otra dirección muestran «Contiene datos sensibles» en lugar de la materia, y los restringidos se muestran como «Documento restringido».
- **Clasificación:** al redactar un documento puede indicar su **tipo de materia** y **macrotema** (opcionales); aparecen y se pueden filtrar en este listado.

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

## Mis firmas: firmar o visar por lote
En **Mis firmas** (y desde el aviso «Firmar o visar por lote» de su Inicio) ve los documentos en los que **ya le toca** firmar o visar. Marque **uno o varios** documentos, use la casilla del encabezado para **seleccionar todos** los de la lista, o marque «**todos los pendientes**» (se procesa un tramo de hasta 25 por vez; repita si quedan más). Antes, abra los documentos que quiera revisar con su enlace.
- **Para firmar:** elija el tipo de firma (**avanzada** con un código OTP de 6 dígitos, o **simple** en los documentos que la admiten), confirme que revisó los documentos y presione **Firmar lo seleccionado**. Con firma avanzada, si el servicio de firma rechaza un documento el lote se detiene (lo ya firmado queda firmado) y se le informa el resultado de cada uno.
- **Para visar:** presione **Visar lo seleccionado** (puede dejar un comentario común). Para **rechazar** un documento, ábralo y rechácelo con su comentario.
- Los documentos **restringidos** a los que usted no está autorizado(a) aparecen sin título y no se pueden firmar por lote.

## Archivos con versiones y visor
- **Ver un archivo:** al hacer clic en un archivo del expediente se abre en una **ventana (modal)**; el botón **Abrir en una pestaña nueva** lo despliega a pantalla completa y **Descargar** lo guarda. PDF, imágenes JPG/PNG y texto se ven en el navegador; Word, Excel y similares solo se descargan.
- **Subir un cambio:** en el archivo, entre a **Versiones** y use **Subir una versión nueva**: adjunte el archivo corregido e indique el **motivo del cambio**. El sistema no pisa el anterior: crea la versión 2, 3… y conserva todas.
- **Qué cambió:** cada versión muestra quién la subió, cuándo, el motivo, un resumen de **qué cambió respecto de la versión anterior** (tamaño, páginas, líneas agregadas o quitadas en texto) y las firmas electrónicas que trae el archivo. Para imágenes y documentos ofimáticos el contenido no se compara automáticamente y el resumen lo indica.
- **Historial de un documento:** junto a cada documento del expediente, **Historial** muestra por versión qué párrafos cambiaron y quién **editó, visó, rechazó o firmó**.

## Anexos en DocDigital
Al **crear una comunicación** puede marcar los **archivos del expediente** que se envían como anexos (hasta 10 por defecto, máx. 50 MB cada uno; se envía la versión vigente). Los anexos que **recibe** quedan en el expediente, en «Anexos recibidos»; si alguno no se pudo guardar (formato no habilitado, con contenido activo, enlace externo o falla de descarga) se muestra con el motivo: pídalo de nuevo por DocDigital si lo necesita.

## Panel de DocDigital
En **DocDigital** verá, además del estado de la conexión, un panel con las comunicaciones recibidas y enviadas de su dirección, los **acuses atrasados**, los envíos fallidos, el **tiempo medio para registrar el acuse** y para responder lo recibido, el movimiento de los últimos 6 meses y las instituciones con más movimiento. Auditoría ve toda la municipalidad.

## Solicitudes sin autorización de datos personales
Al ingresar su solicitud, el vecino(a) indica si autoriza el tratamiento de sus datos personales. Si **no lo autoriza**, igual se tramita, pero el expediente muestra un **aviso amarillo** en la parte superior: trate solo los datos estrictamente necesarios para resolver el trámite y avise al Oficial de Protección de Datos (DPO) si necesita usarlos para otro fin. Quien tenga el permiso de Datos personales puede ver el listado en el menú **Datos personales (DPO)**.

## Recorrido guiado y manuales
En el menú de su nombre están **Recorrido guiado** y **Manuales de usuario**, que lista solo los manuales de su perfil (los manuales son para el personal municipal: el público y los vecinos solo ven el Manual del ciudadano).

El recorrido guiado se muestra **sobre su pantalla real**, sin ventana aparte: resalta cada parte (los indicadores de la bandeja, el menú lateral, un expediente, las partes y la notificación) con una tarjeta de instrucciones y lo lleva por las pantallas que corresponden. Avance con **Siguiente**, vuelva con **Anterior** o cierre con Esc. Se ofrece la primera vez y puede repetirlo cuando quiera.

## Gestionar una comunicación recibida por DocDigital
Al abrir el expediente de una comunicación recibida verá su documento original, sus firmas y el **acuse de recibo**:
1. **Acusar recibo** (o devolverla a la entidad de origen con un motivo). Hasta acusar recibo no se pueden crear documentos de respuesta.
2. **Cambiar el estado de gestión** de la comunicación: *En gestión*, *Respondida* o *Archivada* (con una nota opcional). Queda como comentario del expediente y en la auditoría.
3. **Derivar el expediente** a la persona que debe actuar: elija *a quién*, la **acción que debe realizar** (preparar la respuesta, informar, revisar, tomar conocimiento, redactar un documento, archivar u otra), un **plazo** opcional y las instrucciones.
4. La persona derivada ve la tarea en su bandeja, en **Acciones que le derivaron** (las vencidas se marcan), abre el expediente y **actualiza el estado** de la derivación: *En proceso*, *Cumplida* o *Devuelta (no corresponde)*, con una nota (obligatoria al cumplir o devolver). Cada cambio queda como comentario del expediente.

Un(a) superadministrador(a) también puede acusar recibo, cambiar el estado y derivar cualquier expediente; no se asigna el expediente a sí mismo(a) (el responsable es de la unidad receptora).

## Iniciar un expediente de oficio
Si su cuenta tiene el permiso **«Iniciar expedientes de oficio»** y está asignada a una unidad, en el **Inicio** verá el botón **Iniciar un expediente de oficio**. Sirve para comenzar un procedimiento administrativo que no nace de una solicitud del vecino y que puede concluir en un decreto, un reglamento, un oficio u otro documento.
1. Indique el **asunto** (una línea), una descripción, **con qué se espera concluir** y el fundamento legal, si lo hay.
2. El expediente queda **a su cargo, en su unidad**. Desde allí redacta los documentos con las plantillas, los envía a firma, los comunica por DocDigital y lo cierra como cualquier otro expediente.

### Partes y notificación al concluir
En la sección **Partes y notificación** del expediente registre a quienes interesa o se dirige el procedimiento:
- **Persona (vecino/a):** nombre, RUN y, según el canal, correo. Se notifica por Casilla Única, correo o entrega física.
- **Funcionario(a) municipal:** se le notifica con un **aviso interno**: le queda una acción pendiente «Tomar conocimiento» en su inicio.
- **Persona o entidad externa:** por correo (con enlace de verificación del documento, sin adjuntos) o entrega física.
- **Organismo público en DocDigital:** búsquelo por nombre en el directorio y, si quiere, elija **personas** de ese organismo. La notificación sale como comunicación oficial por DocDigital.

Con el expediente **cerrado**, marque las partes y los documentos vigentes y pulse **Notificar**. Cada notificación queda con su estado y su evidencia; no se repite una notificación ya hecha y no se puede quitar una parte a la que ya se notificó.

## Despachar por DocDigital a entidades y personas del directorio
En el expediente, al crear una comunicación elija **Entidad y personas de DocDigital (buscar)**: escriba parte del nombre de la entidad y elíjala; después, si corresponde, elija personas habilitadas de esa entidad. El directorio se carga solo desde DocDigital (ver el manual de administración); si falta una entidad, pida que lo actualicen.

## Solicitudes presentadas a nombre de otra persona
Cuando un vecino presenta una solicitud **a nombre de otra persona** (representante, apoderado, familiar o quien le ayuda), el expediente muestra **«A nombre de»** con la persona, la relación y la acreditación que declaró. El sistema **no verifica el poder**: revise el documento en los anexos antes de resolver. Las notificaciones llegan a quien presentó la solicitud. Si la solicitud se hizo a nombre suyo, no puede tramitarla (conflicto de interés).

## Armar el formulario de un trámite (campos, listados y cálculos)
Si su rol administra trámites, en **Trámites y servicios › Nuevo trámite** (o al editar uno) hay un paso **Campos del formulario** para pedir datos propios además de los documentos. Máximo 40 campos por trámite. Tipos disponibles:
- **Texto corto** y **texto largo** (con largo máximo), **número** (mínimo, máximo, decimales, y un texto antes o después, como `$` o `m²`), **fecha** (puede exigir «desde hoy» o «hasta hoy», o fechas fijas) y **Sí / No**.
- **Lista de opciones** (elige una) o **varias** (con un máximo): escriba las opciones aquí, una por línea, o **use un listado guardado**.
- **Cálculo (fórmula):** se muestra en vivo al vecino mientras completa el formulario, y el sistema lo vuelve a calcular al recibir la solicitud. Los cálculos no se piden: se calculan solos.
- **Título de sección:** separa los campos en grupos.

**Clave:** cada campo tiene una clave (se genera sola a partir del nombre: `superficie`, `valor_m2`…) que se usa en las fórmulas.

**Fórmulas.** Operadores: `+ - * / %` (resto) `^` (potencia) y paréntesis; comparaciones `< <= > >= == !=` (dan 1 si se cumplen y 0 si no) y lógicos `&&` (y) `||` (o) `!` (no). Funciones: `si(condición; si_cumple; si_no)`, `min`, `max`, `suma`, `promedio`, `abs`, `redondear(x; decimales)`, `piso`, `techo`, `raiz`, `potencia(base; exponente)`, `dias(fecha1; fecha2)` y `anios(fecha1; fecha2)`. Se usan los campos por su clave (número, fecha, sí/no, lista con valores numéricos u otro cálculo). Un número vacío vale 0; una fecha vacía deja el cálculo sin resultado; `hoy` es la fecha de hoy. Ejemplos: `redondear(superficie * valor_m2 * 0.02; 0)`, `si(edad >= 60; tarifa * 0.5; tarifa)`, `anios(fecha_nacimiento; hoy)`, `dias(inicio; termino) * 3500`. Si una fórmula tiene un error, el sistema lo indica con la posición al guardar.

**Listados reutilizables.** En **Trámites › Listados de opciones** cree una vez un listado (por ejemplo «Tipo de vivienda» o «Tarifa por zona») y elíjalo en los campos de lista de cualquier trámite. Una opción puede ser `valor;texto` (por ejemplo `0.5;Media tarifa`): si el valor es un número se puede usar en cálculos. Los cambios al listado se reflejan en todos los trámites que lo usan; lo que ya envió la ciudadanía conserva su respuesta. Cada dirección edita sus listados (la administración, todos); todos pueden usarlos.

La **vista previa** del trámite muestra el formulario funcionando. Las respuestas aparecen en el expediente («Datos del formulario»), en el comprobante del vecino y en los metadatos del expediente; si cambia o quita un campo, las solicitudes ya enviadas conservan lo que respondieron.

## Subrogancia (cuando alguien lo reemplaza o usted reemplaza a alguien)
Si usted va a estar fuera de la oficina, pida a quien administra las subrogancias que registre quién lo reemplaza **entre dos fechas y horas**. Si a usted lo designan subrogante, en el **Inicio** aparece *Expedientes que subroga* con los expedientes del titular mientras dure; ábralos y gestiónelos como los suyos (el expediente indica que lo hace como subrogante). No incluye las firmas ni visaciones del titular ni los expedientes restringidos, y todo vence solo a la hora de término.
