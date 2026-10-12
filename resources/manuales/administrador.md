# Manual del administrador(a) del sistema

El administrador gestiona los **mantenedores**, la **seguridad** y el **diagnóstico**. Por diseño de mínimo privilegio **no puede leer expedientes** ni descargar sus archivos (eso corresponde a los funcionarios que participan y, en solo lectura, al auditor). Para la instalación técnica en cPanel vea el `README.md` del proyecto.

## 1. Menú del administrador

El menú se agrupa en **Administración** (Unidades, Usuarios, Roles y permisos, Trámites, Tipos de documento, Plantillas, Folios, Estilo y posición de firmas, Instituciones, Procesos, Codificador, Servicios PISEE, APIs e integraciones, Repositorio, Apariencia, Barra de menú) y **Seguridad y operación** (Seguridad, Registro de logs, Usuarios conectados, Diagnóstico). Cada persona ve solo lo que su rol permite; los grupos se despliegan con Enter o Espacio y se cierran con Esc.

En todos los mantenedores **no existe el borrado**: se **desactiva** (botón *Desactivar/Activar*). Cada cambio queda en la bitácora de auditoría.

## 2. Unidades

Cree las unidades municipales (código y nombre). Las unidades responsables de cada trámite y la unidad de cada funcionario salen de aquí.

## 3. Usuarios

1. **Nuevo**: ingrese **RUN** (con dígito verificador; se valida), nombres, apellidos, correo, cargo, **rol** y **unidad**.
2. Roles: los del sistema (`admin`, `funcionario`, `alcalde`, `secretario`, `auditor`) y los **personalizados** que cree en *Roles y permisos* (§17). Use el buscador y los filtros de rol/estado para ubicar personas.
3. **Habilitado para firmar**: solo para funcionario, alcalde y secretario. Admin y auditor **nunca** firman.
4. **Desactivar** quita el acceso en la siguiente petición y **cierra sus sesiones abiertas**. Cambiar el rol de alguien también cierra sus sesiones (el nuevo rol rige desde el siguiente ingreso).
5. Resguardos: no puede desactivar **su propia** cuenta y siempre debe quedar **al menos un administrador activo**.
6. La columna *Último acceso* muestra el último ingreso con ClaveÚnica.

Para el primer administrador vea el README (alta por SQL). Los demás usuarios ingresan después con ClaveÚnica: **solo entra quien está registrado y activo**.

## 4. Trámites

Campos: código interno, nombre, descripción, **código CPAT** (formato validado, p. ej. `PA-UNI00002-00001`; use los reales del catálogo), plazo en días, **proceso del Codificador**, **unidades responsables (1 o más)** y **anexos requeridos** (una línea por anexo: `Nombre|1` obligatorio, `Nombre|0` opcional).

La solicitud llega a la bandeja de **todas** las unidades responsables; la primera que la toma crea el expediente.

## 5. Tipos de documento y reglas de firma

Cada tipo define: código, nombre, prefijo de numeración, **si la primera firma es del alcalde/delegado**, **firmas del flujo ágil** y **firmas del flujo clásico**. Ajuste las reglas según la normativa del municipio (por defecto 1 y 2). Puede crear tipos nuevos (por ejemplo "Resolución").

## 6. Plantillas

Redacte plantillas con el editor. Use campos de combinación: `{{fecha}}`, `{{expediente}}`, `{{folio}}`, `{{solicitante_nombre}}`, `{{solicitante_run}}`, `{{tramite}}`, `{{unidad}}`, `{{institucion_origen}}`, `{{asunto_origen}}`, `{{folio_docdigital}}`. El HTML se **sanitiza en el servidor** (se eliminan scripts y atributos peligrosos).

## 7. Codificador de instituciones y procesos

Menú **Codificador**:

1. Configure `CODIFICADOR_INSTITUCION_CODIGO` con el código de la Municipalidad.
2. **Importe CSV** (UTF-8, con cabecera): instituciones `codigo,nombre,tipo,codigo_padre`; procesos `codigo,nombre,institucion_codigo`. Los códigos existentes se actualizan; las filas inválidas se informan.
3. Con `CODIFICADOR_MODE=api` puede **sincronizar por API** (formato A VALIDAR).
4. Revise y edite en **Instituciones** y **Procesos**.

Las instituciones del catálogo son las opciones de **destinatario** al despachar por DocDigital.

## 8. DocDigital

Todo se configura en el `.env` (ver README): `DOCDIGITAL_MODE` (`outbox` por defecto; `demo` para DemoDoc; `prod`), URL, token y rutas de la guía API MW V2, y `DOCDIGITAL_UNIDAD_RECEPCION` (unidad que recibe lo entrante).

- Requisitos de la Secretaría de Gobierno Digital: alta de la institución, **Administrador Principal** e interlocutor.
- El sistema solo envía PDF con **firma electrónica avanzada real**.
- Cada comunicación recibida crea un expediente con identificador único en la unidad receptora (revise que esa unidad exista y esté activa).
- El cron ejecuta `docdigital:sincronizar` cada 10 minutos. Si falla, el sistema no pierde estado: reintente o revise credenciales.

## 9. PISEE

1. Adhiera a los términos y condiciones de PISEE e instale el nodo de interoperabilidad (Gobierno Digital).
2. Configure en `.env` el nodo (`PISEE_BASE_URL`, https), la cabecera de identificación, el certificado de cliente y la CA; active `PISEE_MODE=api`.
3. En **Servicios PISEE** registre **solo** los servicios autorizados: código, nombre, institución, **ruta** relativa (validada), método y el parámetro que lleva el RUN. Es una **lista blanca**.
4. Cada consulta exige finalidad, solo al RUN del solicitante del expediente, y queda auditada.

## 10. Seguridad y auditoría

Menú **Seguridad**:

- **Verificar cadena de auditoría**: comprueba la cadena HMAC. Debe decir "Cadena íntegra". Si está **rota**, no borre nada: registre un incidente y siga el plan de respuesta (`docs/INCIDENTES.md`).
- **Incidentes**: registre incidentes (título, descripción sin datos personales, severidad) y marque si es **reportable al CSIRT Nacional**; registre los hitos: alerta temprana, segundo reporte e informe final (plazos A VALIDAR en la normativa vigente).
- **Últimos eventos**: login, firmas, accesos denegados, CSRF fallidos, errores, etc.

## 11. Diagnóstico

**Diagnóstico** revisa extensiones PHP, versión, permisos del directorio de archivos, que no haya modos *mock*, `APP_DEBUG`, HTTPS, claves y la cuenta de servicio de Google. Todo debe estar en "OK" antes de producción.

## 12. Correo institucional (Google Workspace)

El correo sale por la **Gmail API** con una cuenta de servicio y delegación de dominio (alcance `gmail.send`, buzón `no-responder@...`). Verifique el envío creando una notificación por correo en un expediente de prueba. Rotación de clave: README y `docs/INCIDENTES.md`.

## 13. Respaldo y restauración

Respalde la base de datos y `sgd_storage` (cifrado) a diario y guarde las **claves** (`APP_KEY`, `FILE_ENCRYPTION_KEYS`, `AUDIT_HMAC_KEY`) por separado. **Pruebe la restauración** periódicamente. Detalle en el README.

## 14. Accesibilidad

Puede cambiar `SGD_ACCESIBILIDAD_CONTACTO` (correo que aparece en la declaración de accesibilidad). Revise periódicamente el canal de contacto y atienda los reportes.

## 15. Apariencia: logo, colores, encabezado y pie

**Administración → Apariencia.**

- **Logo:** suba PNG, JPG o WebP (hasta 1 MB, mínimo 32×16 px). El sistema lo **re-codifica** (elimina metadatos) y rechaza SVG por seguridad. Escriba el **texto alternativo** (obligatorio si el logo se muestra) y su alto en pantalla.
- **Colores:** cada par texto/fondo se valida con **contraste ≥ 4,5:1** (WCAG AA). Si el contraste no alcanza, el sistema no guarda y le indica el par que falla.
- **Encabezado:** título y subtítulo. **Pie:** texto, texto legal y enlaces (rutas internas `/…` o direcciones `https://`).
- **Restablecer** vuelve a los colores y textos originales sin borrar el logo.

## 16. Barra de menú

**Administración → Barra de menú.** Cambie el nombre, el orden y la visibilidad de los ítems del sistema; los marcados como protegidos (Inicio, Bandeja, Ayuda…) no se pueden ocultar. Agregue **enlaces propios** por área (público, ciudadano, funcionarios), opcionalmente solo para ciertos roles y con apertura en pestaña nueva. **El menú solo cambia la navegación: no otorga ni quita permisos.**

## 17. Roles y permisos

**Administración → Roles y permisos.** Los 5 roles del sistema son de solo lectura (puede ver sus permisos). Para crear un rol:

1. **Nuevo rol**: clave (minúsculas, números y `_`), nombre y **rol base**. El rol base fija las reglas de negocio (quién puede firmar, ser asignado a expedientes, etc.) y el **techo** de permisos.
2. Marque los permisos necesarios. Los no admitidos por el rol base aparecen deshabilitados; `roles.gestionar` y `delegaciones.crear` están reservados a los roles del sistema.
3. Asigne el rol en *Usuarios*. Al editar permisos, las sesiones de quienes tienen ese rol se cierran para aplicar los cambios.
4. No se puede cambiar la base ni eliminar un rol con usuarios asignados; desactívelo solo si no tiene usuarios activos.

## 18. APIs e integraciones

**Administración → APIs e integraciones.** Para cada servicio (ClaveÚnica, FirmaGob, casilla, Gmail, DocDigital, PISEE, Codificador, asistente IA, repositorio) puede ver el estado efectivo, editar URL/modo, **probar la conexión** y **volver a la configuración del `.env`**. Los secretos (tokens, claves) **nunca se muestran**; por defecto solo se configuran en el `.env`. En producción están prohibidos los modos simulados.

## 19. Folios por tipo de documento

**Administración → Folios.** Por tipo defina el **formato** con las fichas `{PREFIJO}`, `{TIPO}`, `{N}`, `{AAAA}`, `{AA}` (p. ej. `{PREFIJO} N° {N}/{AAAA}`), los dígitos, si el número **se reinicia cada año** o es continuo, y el número inicial. El folio se asigna al finalizar el documento y **no cambia nunca**. El contador solo se puede ajustar hacia arriba.

## 20. Estilo y posición de las firmas

**Administración → Estilo y posición de firmas.** Defina estilos (colores con contraste ≥ 4,5:1, tamaño, textos) y posiciones (primera página con coordenadas en mm, o al final del texto con alineación) por tipo de documento y número de firma. Use **Vista previa** para generar un PDF de prueba. El sello es la representación visual en la copia pública; la validez la da la firma electrónica del PDF firmado.

## 21. Repositorio de documentos (Google Drive)

**Administración → Repositorio.** Los documentos pueden guardarse en el disco del servidor o en una **unidad compartida de Google Drive** (pasos de preparación en el README). Siempre se guardan **cifrados**.

1. Configure el modo y los identificadores; pulse **Probar conexión** (debe indicar éxito en todos los pasos).
2. **Migrar** mueve en lotes los archivos locales a Drive; cada archivo se verifica antes de borrar el original local.
3. **Verificar integridad** comprueba una muestra descargando y validando el contenido; también se ejecuta a diario.
4. Si Drive no responde, las subidas **fallan** con aviso (no se guardan en otra parte). Reintente cuando se restablezca.
5. Conserve un **respaldo de las claves de cifrado** (`sgd_secrets`): sin ellas los archivos no se pueden recuperar.

## 22. Registro de logs

**Seguridad y operación → Registro de logs.** Filtre por fecha, acción (empieza con), rol, RUN exacto, entidad, IP o sensibilidad. Los RUN se muestran enmascarados. **Exportar CSV** (quien tenga el permiso) deja un registro sensible en la bitácora. **Log de aplicación** muestra las últimas líneas del log técnico con datos personales y secretos redactados. La verificación de la cadena HMAC está en *Seguridad*.

## 23. Usuarios conectados

**Seguridad y operación → Usuarios conectados.** Lista las sesiones con actividad reciente (funcionarios y ciudadanos con RUN enmascarado), su IP, navegador, inicio y última actividad. **Cerrar sesión** fuerza la salida de esa persona (queda auditado); su propia sesión se cierra con *Salir*.

## 24. Problemas frecuentes

| Problema | Qué hacer |
|---|---|
| Un funcionario no puede ingresar | Verifique RUN, que esté **activo** y el rol. |
| La aplicación no arranca en producción | Revise `.env`: debug, https, modos mock, claves, JSON de la cuenta de servicio. |
| Notificaciones por correo "fallidas" | Cron activo, cola, credenciales Google, delegación de dominio. |
| No llegan comunicaciones de DocDigital | Modo API, credenciales, unidad receptora, cron. |
| Alerta de cadena rota | Plan de incidentes: preservar evidencia, no modificar. |
