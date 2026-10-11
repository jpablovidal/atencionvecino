# Manual breve por rol

## Ciudadano (vecino/a)
1. Entre a `/ingreso` → *Ingresar como ciudadano* → ClaveÚnica. Su RUN y nombre vienen de ClaveÚnica y **no se pueden editar**.
2. **Mis solicitudes** → elija un trámite → complete correo, detalle, forma de notificación (correo, presencial o Casilla Única) y adjunte los anexos (PDF/JPG/PNG/TIFF, máx. 10 MB c/u).
3. Al enviar recibirá un **folio** y podrá descargar el **comprobante electrónico** (PDF con hash de anexos, código de verificación y QR).
4. En el detalle de la solicitud ve el estado. Si fue **devuelta** verá la observación y el **plazo**: responda con texto y nuevos antecedentes antes de que venza.
5. Cuando se le notifique, los documentos aparecen en **Documentos notificados** (solo los suyos). El correo solo trae folio, estado y un enlace; nunca adjuntos.
6. Para comprobar un documento: `/validar` con su código `SDD-XXXX-XXXX-XXXX`.

## Funcionario / Secretario / Alcalde
1. `/ingreso` → *funcionario* (ClaveÚnica; su RUN debe estar habilitado).
2. **Bandeja:** solicitudes de su unidad por asignar → *Revisar* → *Asignármela y crear expediente* (se crean los metadatos MGDEE).
3. En el **expediente**: editar metadatos, cargar archivos (título, resumen, palabras clave, acceso, sensibilidad), comentar/observar, **derivar** a cualquier usuario, **devolver al ciudadano** (observación y plazo 1–90 días).
4. **Documentos:** *Nuevo documento* → tipo y plantilla → editor (campos `{{fecha}}`, `{{expediente}}`, `{{solicitante_nombre}}`, `{{solicitante_run}}`, `{{folio}}`, `{{tramite}}`, `{{unidad}}`). Cada guardado crea una **versión inmutable** (HTML y PDF con SHA-256). Defina metadatos y acceso (público/reservado, sensible).
5. **Flujo:** elija *ágil* o *clásico*, 0 a N visadores (en orden) y los firmantes que exige el tipo. Una persona no puede visar y firmar el mismo documento. El decreto alcaldicio requiere como primera firma al alcalde o a un delegado vigente.
6. **Visar** (aprobar/rechazar) y **firmar** (OTP de 6 dígitos de FirmaGob; no se guarda) en su turno desde *Mis visaciones y firmas pendientes*. Al completarse: número correlativo `N°/año`, código de validación, enlace público y QR.
7. **Cerrar expediente** y **notificar**: seleccione los documentos y el canal. Quedan *pendientes* hasta que haya evidencia: correo (ID de mensaje de Gmail, automático), Casilla Única (registre el ID de acuse) o presencial (registre el acta). Un expediente cerrado puede **reabrirse con motivo**.
8. **Documentos vigentes:** listado final con enlace público.
9. **Asistente IA (si está habilitado):** pide un borrador; revíselo e **Inserte** manualmente. Nunca guarda ni firma.
10. El **alcalde** crea/revoca **delegaciones de firma** (decreto de referencia y vigencia) en `/app/delegaciones`.

## Administrador
Gestiona **mantenedores**: unidades, usuarios (roles, habilitación para firmar), trámites (código CPAT validado, plazo, anexos, 1..N unidades), tipos de documento (reglas de firma), plantillas (HTML sanitizado). **No puede leer expedientes.** Accede a `/app/diagnostico` y a `/app/seguridad` (verificación de la cadena de auditoría e incidentes). No hay borrado: se **desactiva**.

## Auditor
Solo lectura: expedientes, documentos vigentes, delegaciones y `/app/seguridad` (verificar cadena, ver eventos e incidentes). No puede modificar nada.

## Interoperabilidad (funcionarios)
- **Comunicaciones oficiales (DocDigital):** en el expediente, *Comunicaciones oficiales* → elija un documento **vigente**, la **institución destinataria** (Codificador) y el asunto. En modo *outbox* quedará **pendiente**: envíelo por DocDigital y registre el **folio**; en modo API se envía solo y reintenta. El menú **Comunicaciones oficiales** lista lo **recibido** por su unidad.
- **Consultas PISEE:** en el expediente (abierto) elija el servicio e indique la **finalidad**; solo se consulta al solicitante. El resultado queda cifrado en el expediente.
- **Administrador:** *Codificador* (carga CSV/sincronización, instituciones, procesos) y *Servicios PISEE* (lista blanca). En *Trámites* asocie el **proceso** del Codificador.

## Comunicaciones recibidas por DocDigital (unidad receptora)
1. Cada comunicación recibida genera automáticamente un **expediente** con identificador único (aparece en la bandeja *Comunicaciones oficiales recibidas por gestionar*).
2. Abra el expediente y **acuse recibo** (botón o, en modo outbox, la referencia del acuse emitido en DocDigital). Hasta entonces no se pueden crear documentos de respuesta.
3. **Asígnese** el expediente, derive, comente y cargue antecedentes.
4. **Responda** creando el documento que corresponda (decreto, oficio, reglamento, instructivo u otro), con su visación/firma habitual.
5. **Despache** el documento vigente desde *Comunicaciones oficiales (DocDigital)*: se sugiere la institución de origen, pero puede elegir cualquier destinatario (y enviar con copia a otros). Cierre el expediente al terminar.
