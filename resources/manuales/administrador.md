# Manual del administrador(a) del sistema

El administrador gestiona los **mantenedores**, la **seguridad** y el **diagnóstico**. Por diseño de mínimo privilegio **no puede leer expedientes** ni descargar sus archivos (eso corresponde a los funcionarios que participan y, en solo lectura, al auditor). Para la instalación técnica en cPanel vea el `README.md` del proyecto.

## 1. Menú del administrador

Unidades · Usuarios · Trámites · Tipos de documento · Plantillas · Instituciones · Procesos · Servicios PISEE · Codificador · Diagnóstico · Seguridad.

En todos los mantenedores **no existe el borrado**: se **desactiva** (botón *Desactivar/Activar*). Cada cambio queda en la bitácora de auditoría.

## 2. Unidades

Cree las unidades municipales (código y nombre). Las unidades responsables de cada trámite y la unidad de cada funcionario salen de aquí.

## 3. Usuarios

1. **Nuevo**: ingrese **RUN** (con dígito verificador; se valida), nombres, apellidos, correo, cargo, **rol** y **unidad**.
2. Roles: `admin`, `funcionario`, `alcalde`, `secretario`, `auditor`.
3. **Habilitado para firmar**: solo para funcionario, alcalde y secretario. Admin y auditor **nunca** firman.
4. **Desactivar** quita el acceso en la siguiente petición.

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

## 15. Problemas frecuentes

| Problema | Qué hacer |
|---|---|
| Un funcionario no puede ingresar | Verifique RUN, que esté **activo** y el rol. |
| La aplicación no arranca en producción | Revise `.env`: debug, https, modos mock, claves, JSON de la cuenta de servicio. |
| Notificaciones por correo "fallidas" | Cron activo, cola, credenciales Google, delegación de dominio. |
| No llegan comunicaciones de DocDigital | Modo API, credenciales, unidad receptora, cron. |
| Alerta de cadena rota | Plan de incidentes: preservar evidencia, no modificar. |
