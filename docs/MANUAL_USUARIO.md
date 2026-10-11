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
