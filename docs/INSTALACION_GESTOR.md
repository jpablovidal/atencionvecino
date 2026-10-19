# Guía de instalación — https://gestor.santodomingo.cl

Instalación en **2 pasos**: (1) subir el ZIP con el Administrador de archivos y (2) pegar **un solo comando** en la Terminal de cPanel. El comando crea el subdominio, la configuración con claves nuevas, las tablas, los datos de demostración, las cuentas de prueba, el cron y verifica el sitio. Se probó de punta a punta sobre MariaDB 10.11 (instalación nueva, actualización sobre la misma base y flujos completos en navegador).

> Esta instalación queda como **AMBIENTE DE PRUEBAS** (`SGD_AMBIENTE=pruebas`): integraciones simuladas (ClaveÚnica, FirmaGob, DocDigital, PISEE), correo solo a registro y ingreso con usuario y contraseña (vence a los 30 días). Para operar con integraciones reales vea «Pasar a producción».

## Antes de empezar (ya lo tiene hecho)
- Base de datos y usuario MySQL **`santod85_gestor2`** (ambos con ese nombre) con **todos los privilegios** sobre la base.
- PHP 8.3 disponible en cPanel (el comando lo comprueba y se detiene con un mensaje claro si falta algo).
- Archivo **`sgd-release.zip`** (SHA-256 `19be5c1818f037ab5e097e576dfc9e570d3f56bd693f684b1d9151c38bebeb47`).

## Paso 1 — Subir el ZIP (único paso manual)
cPanel → **Administrador de archivos** → carpeta personal (`/home/su_usuario`, **no** `public_html`) → **Cargar** → `sgd-release.zip`.

## Paso 2 — Instalar (un solo comando)
cPanel → **Terminal**. Pegue y presione Enter; cuando lo pida, escriba la **contraseña del usuario MySQL** `santod85_gestor2` (no se ve al escribir):
```bash
cd ~ && sha256sum sgd-release.zip && unzip -q -o sgd-release.zip && rm -f sgd-release.zip && bash ~/sgd/scripts/instalar-cpanel.sh
```
(Compare el SHA-256 que imprime con el de esta guía.) El instalador hace, en orden: comprueba PHP y extensiones → comprueba la conexión a la base → crea el subdominio `gestor.santodomingo.cl` con raíz `sgd/public`, PHP 8.3 y solicita el certificado AutoSSL → carpetas y permisos → `.env` con claves únicas → tablas y datos de demostración → **muestra una tabla con las 14 cuentas de prueba y sus contraseñas aleatorias (se muestran una sola vez: anótelas)** → cron → verificación.

Si el subdominio `gestor` ya existía con otra raíz, en cPanel → **Dominios** deje su raíz del documento en `sgd/public`. Si el certificado no se emite solo, cPanel → **SSL/TLS Status** → *Run AutoSSL* (hasta 15 minutos).

## Paso 3 — Probar
Abra **https://gestor.santodomingo.cl** (verá el aviso amarillo «AMBIENTE DE PRUEBAS»). Ingreso: `/ingreso` → **«Ingresar con usuario y contraseña»**; usuario = RUN completo con guion.

| Perfil | RUN (usuario) | Qué probar |
|---|---|---|
| Administrador(a) | `10000001-6` | Todos los mantenedores, roles, trámites, conexiones, recursos |
| Alcalde | `10000002-4` | Firma de decretos, delegaciones |
| Secretaria Municipal | `10000003-2` | Visación/firma, comunicaciones DocDigital |
| Funcionario DOM / Revisora DOM | `10000004-0` / `10000005-9` | Bandeja de Obras, expedientes, documentos |
| Funcionario DIDECO | `10000006-7` | Bandeja de Desarrollo Comunitario |
| Auditoría | `10000007-5` | Solo lectura, bitácora, seguridad |
| **Autoridad** | `10000013-K` | Seguimiento de solicitudes: por RUN, fechas, dirección, etapa o trámite (solo lectura) |
| **Transparencia activa y pasiva** | `10000015-6` | Consultas de acceso a la información, expedientes, requerimientos, plazos, transparencia activa y reportes |
| **Oficina de Partes** | `10000016-4` | Respuestas finales instruidas: notificar por Casilla Única con copia al correo o registrar la entrega física |
| **Encargado(a) de pagos** | `10000017-2` | Decretos de pago: expediente de pago y proceso de compra, generar o cargar el PDF, enviar a firma |
| **Tesorero(a) municipal** | `10000018-0` | Egresos de los decretos de pago, con firma simple o avanzada |
| **OIRS** | `10000014-8` | Solicitudes, responsables, plazos vencidos y por vencer (solo lectura) |
| Director de Obras | `10000008-3` | Funcionario con firma |
| **Directora DIDECO** | `10000009-1` | Crea, publica y deshabilita trámites de su dirección |
| **Redactor DOM** | `10000010-5` | Crea borradores de trámites; no publica |
| **Encargada del Archivo** | `10000011-3` | Registra microformas, carga archivos, indexa, solicita disposición de originales |
| **Ministro de Fe** | `10000012-1` | Certifica microformas con acta y autoriza la disposición |
| Vecina/o 1 y 2 | `20000001-3` / `20000002-1` | Catálogo público, solicitar trámites con adjuntos |

Flujo sugerido: (1) como **Directora DIDECO**: *Trámites y servicios* → *Nuevo trámite* → guardar borrador → *Vista previa* → *Publicar*. (2) Sin iniciar sesión: **/tramites** muestra el catálogo con buscador y filtro; abra el trámite y presione *Realizar este trámite en línea* → ingrese como **Vecina 1** y volverá directo al formulario; adjunte los documentos y envíe (recibe folio y comprobante). (3) **Funcionario DIDECO** la ve en su bandeja y se la asigna (se crea el expediente); redacta un oficio con el editor, la **Secretaria** lo firma (FirmaGob simulado, OTP de 6 dígitos) y se valida públicamente con el código SDD. (4) **Auditoría** revisa la bitácora y su cadena HMAC. (5) **Archivo municipal** (menú lateral): como **Encargada del Archivo** registre una microforma, cargue un PDF, indexe un documento y envíela a revisión; como **Ministro de Fe** (otra persona: quien registra no puede certificar) certifíquela con un N.º de acta y un PDF; como **Auditoría** verifique la integridad, descargue y emita la constancia. La constancia **no reemplaza el certificado legal** (ver `docs/NORMATIVA.md` §7, A VALIDAR).

## Actualizar a una versión nueva (mismo procedimiento)
Suba el nuevo ZIP a `~/` y pegue **el mismo comando del Paso 2**. Si detecta `~/sgd/.env`, entra en modo **actualización**: conserva `.env`, archivos y datos, aplica solo las migraciones nuevas y limpia cachés (no pide contraseña ni vuelve a cargar datos de demostración). **Si su sitio es de pruebas y aún no tiene el módulo de archivo, al actualizar el instalador crea además (solo si faltan) la unidad «Archivo Municipal», los roles y las 2 cuentas del archivo (`10000011-3` Encargada del Archivo y `10000012-1` Ministro de Fe) y muestra sus contraseñas una sola vez: anótelas.** No toca las demás cuentas.

## Conectar DocDigital (credenciales)
Las credenciales **no vienen en el ZIP ni en el repositorio**. Ingréselas una sola vez, en la Terminal de cPanel (el secreto no se muestra al escribirlo ni queda en el historial):
```bash
cd ~/sgd && PHP=$(ls /opt/cpanel/ea-php83/root/usr/bin/php 2>/dev/null || command -v php) && read -rp "Client ID: " CID && read -rsp "Client Secret: " CSEC && echo && grep -v '^DOCDIGITAL_CLIENT_' .env > .env.nuevo && printf "DOCDIGITAL_CLIENT_ID='%s'
DOCDIGITAL_CLIENT_SECRET='%s'
" "$CID" "$CSEC" >> .env.nuevo && chmod 600 .env.nuevo && mv .env.nuevo .env && unset CID CSEC && $PHP artisan config:clear && echo "Credenciales guardadas"
```
Luego, en el sitio: **Administración → APIs e integraciones → DocDigital**: indique el **modo** (`demo` o `prod`) y, si no usa los valores por defecto, la **URL del servidor** (DemoDoc ya viene: `https://api-demodoc.digital.gob.cl/api`; la de producción no figura en la especificación), el **id de la entidad** y el **tipo de documento** por defecto. El token OAuth2 y las rutas ya vienen según la especificación (https://api-doc.digital.gob.cl/api/swagger-ui/index.html) y **el sistema aún no los trae validados** (ver `docs/LIMITACIONES.md`). Después abra **DocDigital** en el menú lateral y use **Probar conexión**.

> Si compartió el Client Secret por chat o correo, considérelo expuesto: pida a DocDigital que lo **rote** cuando termine las pruebas. No use credenciales de producción para pruebas con envíos reales.

## Novedades de esta versión: Casilla Única e inicio dual
- **Casilla Única** (menú lateral, para quien puede notificar): estado de la conexión, notificaciones pendientes/fallidas de su unidad, registro de la evidencia (ID) y reintentos. Los permisos se ven en *Administración → Roles y permisos* (nuevo `casilla.gestionar`: Secretaria y administración por defecto; ya aplica a los roles existentes sin hacer nada).
- **Inicio dual**: al ingresar como funcionario(a) aparece una página con dos tarjetas, *Espacio funcionario* y *Espacio vecino(a)*; se cambia con **Cambiar de espacio**. Pruébelo ingresando como **Funcionario DOM**. Quien presentó un trámite como vecino(a) no puede tramitarlo como funcionario(a). Se desactiva con `SGD_ESPACIO_DUAL=false` en `~/sgd/.env`.
- **Conectar Casilla Única (credenciales)**: la guía de integración (https://wikiguias.digital.gob.cl/es/Manuales/integracion-casillaunica) **no fue accesible desde el entorno de construcción**, así que el contrato de la API **no está validado** y el modo por defecto sigue siendo **Outbox** (nada se envía sin evidencia manual). Cuando tenga las credenciales: ingréselas con el mismo método seguro, cambiando los nombres de variable:
```bash
cd ~/sgd && PHP=$(ls /opt/cpanel/ea-php83/root/usr/bin/php 2>/dev/null || command -v php) && read -rp "Client ID: " CID && read -rsp "Client Secret: " CSEC && echo && grep -v '^CASILLA_CLIENT_' .env > .env.nuevo && printf "CASILLA_CLIENT_ID='%s'\nCASILLA_CLIENT_SECRET='%s'\n" "$CID" "$CSEC" >> .env.nuevo && chmod 600 .env.nuevo && mv .env.nuevo .env && unset CID CSEC && $PHP artisan config:clear && echo "Credenciales guardadas"
```
  y complete en *Administración → APIs e integraciones → Casilla Única / Notificador* la URL, la URL del token y el modo `API`; luego use **Probar conexión** en el menú *Casilla Única*.

## Novedades: roles Autoridad y OIRS, y tablero por rol
- Dos roles nuevos de **solo lectura**: **Autoridad** (menú *Seguimiento de solicitudes*: filtros por RUN, fechas, dirección, etapa y trámite; muestra etapa, estado, quién la tiene y si terminó) y **OIRS** (menú *Panel OIRS*: solicitudes, responsables, plazos vencidos y por vencer). No ven documentos ni firman.
- Cada persona ve su **tablero** al ingresar (autoridad, OIRS, funcionario, secretaría, alcalde, auditoría, administración).
- **Al actualizar un sitio de pruebas** el instalador crea (solo si faltan) las cuentas `10000013-K` (Autoridad) y `10000014-8` (OIRS) con contraseña aleatoria que se muestra **una sola vez**: anótelas (o use `pruebas:credenciales --rotar`). En instalaciones nuevas aparecen con las demás cuentas.
- Para generar datos de ejemplo de plazos: ingrese solicitudes como vecino y asígnelas como funcionario; los plazos salen del plazo en días de cada trámite y se cuentan en **días administrativos** (lunes a viernes, sin feriados; ver `docs/NORMATIVA.md` §8, **A VALIDAR**).

## Novedades: rol Transparencia activa y pasiva
- Rol nuevo **Transparencia activa y pasiva** (cuenta `10000015-6`): menú *Transparencia* (consultas de acceso a la información con su plazo), *Requerimientos*, *Transparencia activa*, *Plazos y feriados* y *Reportes*. En el expediente de una solicitud de acceso aparece el cuadro **Transparencia pasiva (Ley 20.285)** para prorrogar el plazo (una vez), requerir información a una dirección y derivar a otro órgano.
- Las demás direcciones ven el menú **Requerimientos de transparencia** para responder lo que se les pide (solo ven los de su dirección, sin datos del solicitante).
- **Al actualizar un sitio de pruebas** el instalador crea (solo si faltan) la unidad *Unidad de Transparencia*, el trámite *Solicitud de acceso a la información pública (Ley 20.285)* (20 días hábiles) y la cuenta del rol, con contraseña aleatoria que se muestra **una sola vez**: anótela.
- **Importante:** los **feriados no vienen precargados**. Ingréselos en *Plazos y feriados* (Transparencia) para que los 20 días hábiles los excluyan. Los plazos legales son **A VALIDAR** (`docs/NORMATIVA.md` §9).

## Novedades: feriados, plantillas por secciones, restringidos y anuncios
- **Feriados** (menú *Feriados*, administración y Transparencia): los días registrados **no cuentan como días administrativos** en los plazos de **todos** los trámites y servicios (ahora lunes a viernes sin feriados es el valor por defecto; la casilla «días administrativos» del trámite lo controla). Al agregar o quitar un feriado se recalculan los plazos en curso. **Los feriados no vienen precargados**: ingréselos (uno a uno o pegando `AAAA-MM-DD;Nombre;tipo`) y use el simulador para comprobar.
- **Anuncios** (menú *Anuncios*, administración y OIRS): título, descripción, imagen (PNG/JPG hasta 2 MB) o video (MP4/WebM hasta 20 MB), enlace opcional, vigencia y audiencia (vecinos, funcionarios o todos). Se muestran en un **modal al cargar el sitio**, una vez por visita. El tamaño real de un video lo limita PHP: si necesita videos grandes, cPanel → *MultiPHP INI Editor* → suba `upload_max_filesize` y `post_max_size` (p. ej. 32M).
- **Expedientes y archivos restringidos**: quien opera el expediente puede restringirlo (o un documento/archivo), con motivo, y autorizar solo a quien necesita conocerlo (con vencimiento de hasta 1 año). Quien no está autorizado ve «archivo restringido». **Administración del sistema nunca ve el contenido**, pero en *Accesos a restringidos* (administración y auditoría) ve **quién vio o intentó ver** esos archivos (nombre, cargo, RUN parcial, sin IP). Enfoque de protección de datos personales (Ley 19.628 / 21.719: **A VALIDAR**).
- **Plantillas por secciones**: materia, vistos, considerando, cuerpo (decreto, reglamento, ordenanza, instructivo u oficio), firmas y **cadena de responsabilidad** (cargos que visan; sus iniciales se insertan al pie). Al actualizar, el instalador crea (solo si faltan) las plantillas de ejemplo de decreto, reglamento, ordenanza, instructivo y oficio (`php artisan plantillas:ejemplos`). Su texto es referencial: valídelo con jurídica.
- Las tablas nuevas se crean solas al repetir el comando del Paso 2 (modo actualización); no hay pasos manuales.

## Novedades: respuesta final de notificación y avisos por movimiento
- **Respuesta final**: en el expediente, quien gestionó indica *Respuesta final al solicitante* (elige uno o más documentos y archivos del expediente, el canal y confirma que enviará la respuesta). Quien responde (rol nuevo **Oficina de Partes**, menú *Respuestas finales*) ve qué responder y con qué documentos y lo **despacha por Casilla Única (notificador) con copia al correo**; excepcionalmente registra una **entrega física con acta**. Requiere el expediente **cerrado**.
- **Avisos por cada movimiento**: el vecino recibe un aviso (Casilla Única + copia por correo) cuando su solicitud ingresa, se abre el expediente, se deriva, se devuelve, se prorroga, termina la gestión o se despacha la respuesta. En *Mis solicitudes* ve **número de solicitud, número de expediente, dirección municipal responsable y fecha de cada movimiento**.
- **Cuenta de prueba nueva** al actualizar: `10000016-4` (Oficina de Partes); el instalador la crea (solo si falta) y muestra su contraseña **una sola vez**: anótela (o `php artisan pruebas:credenciales --rotar`). Para dar el rol a una persona real: *Administración → Usuarios* → rol **Oficina de Partes**.
- **Correo con Google Workspace**: los avisos salen por la **Gmail API** con la cuenta de servicio y delegación de todo el dominio (README §10: habilitar Gmail API, delegar el scope `https://www.googleapis.com/auth/gmail.send` al ID de cliente de la cuenta de servicio, subir el JSON a `~/sgd_secrets/gmail-sa.json` con permisos 600 y fijar `MAIL_MAILER=gmail_api`, `GOOGLE_MAIL_SENDER=no-responder@santodomingo.cl` en `~/sgd/.env`). Sin esa configuración el correo queda «fallido» y se reintenta; los demás pasos funcionan. La cola se procesa con el cron (`schedule:run`) que ya instala el comando.
- **Casilla Única**: mientras la conexión esté en modo *outbox* (valor por defecto) los avisos y notificaciones quedan **pendientes** hasta registrar el acuse (panel *Casilla Única* o la pantalla de la respuesta). El formato del mensaje de aviso es **A VALIDAR** con la guía oficial. Si el volumen es alto, puede desactivar solo los avisos de movimiento con `SGD_AVISOS_MOVIMIENTOS=false` en `.env` (el correo de la respuesta final se mantiene).

## Novedades: asociar expedientes y microformas, decreto de pago y egreso
- **Asociar expedientes y microformas**: dentro de un expediente abierto, *Buscar y asociar expedientes o microformas* permite reutilizar procesos y antecedentes. Solo se encuentran los expedientes que la persona ya puede ver; las microformas exigen el permiso de consulta del archivo municipal. Se pueden **incorporar copias** del contenido (con su origen y huella); lo restringido solo se copia con autorización y la copia queda restringida.
- **Decretos de pago** (menú *Decretos de pago*): el Encargado(a) de pagos carga el expediente de pago y el proceso de compra, y **genera el PDF con la plantilla** «Decreto de pago» (con visación y firma en orden) o **carga el PDF ya emitido**. **Tesorería** genera el **egreso** (fecha, medio de pago, monto, detalle) y lo firma con **firma simple o avanzada** según se configure.
- **Configuración** (menú *Decreto de pago: correlativo y firmantes*, administración): correlativo propio del decreto y del egreso, y firmantes en orden. **Debe revisarla** antes de usar el módulo con datos reales: cantidad de firmantes (1 o 2 firmas por tipo), cargos y tipo de firma. El valor legal de la firma simple para egresos es **A VALIDAR**.
- **Cuentas de prueba nuevas** al actualizar: `10000017-2` (Encargado de pagos) y `10000018-0` (Tesorera). El instalador las crea (solo si faltan), crea los firmantes de demostración y muestra las contraseñas **una sola vez**: anótelas (o `php artisan pruebas:credenciales --rotar`). La plantilla de ejemplo se crea con `plantillas:ejemplos` (ya lo ejecuta el instalador).
- Las tablas y roles nuevos (Tesorero(a) municipal y Encargado(a) de pagos) se crean solos al repetir el comando del Paso 2.

## Novedades: tablero de pagos y consulta de proveedores
- **Tablero de pagos** (menú *Tablero de pagos* y bloque en el *Inicio* del tesorero): decretos por egresar y su saldo, facturas con más de 30 días (alerta referencial, **A VALIDAR** con Jurídica), egresos en firma y del mes, por medio de pago, antigüedad y proveedores con más saldo; exporta la lista a CSV.
- **Consulta pública para proveedores**: `https://gestor.santodomingo.cl/pagos-proveedores`. La persona **ingresa con ClaveÚnica** (queda registrado su RUN) y luego indica **RUT de su empresa, número y fecha de la factura**; los tres deben coincidir con lo registrado. No muestra datos bancarios; límites: 12 consultas por minuto por IP y 30 por hora por persona. **Ojo:** ClaveÚnica identifica a la persona, no acredita que represente a la empresa (ver `docs/LIMITACIONES.md`). En el ambiente de pruebas el ingreso con ClaveÚnica es el simulado; en producción requiere `CLAVEUNICA_MODE=real` con las credenciales de ClaveÚnica.
- **Importante para que funcione**: el decreto de pago ahora pide la **fecha de la factura** (junto con RUT y número). Los decretos creados antes de esta versión no la tienen: complétela en el decreto (mientras sea borrador) o no aparecerá en la consulta pública. Los datos de demostración de los decretos de ejemplo no incluyen proveedor.
- Si quiere publicar el enlace en el sitio web municipal, use la dirección anterior; también aparece en el menú del portal («Pago a proveedores»).
- La tabla nueva no se crea: solo se agrega una columna (`factura_fecha`) al repetir el comando del Paso 2.

## Novedades: panel de Alcaldía
- El alcalde (`10000002-4` en pruebas) tiene el menú **Panel de Alcaldía** y su Inicio muestra: **firmas y visaciones pendientes** (con su turno y los días de espera), **decretos de pago por firmar**, **quién tiene hoy los documentos** en visación y firma, las **solicitudes** de toda la municipalidad (etapas, plazos, por dirección) y los **pagos** (estado, antigüedad, por egresar, egresos del mes).
- Se le agregó, por defecto, el permiso de **solo lectura** de pagos (`decretopago.ver`). Si no desea que vea montos y proveedores, quítelo en *Administración → Roles* (el rol «alcalde» es del sistema: cree un rol personalizado con base alcalde sin ese permiso).
- Para entregar el panel al secretario municipal u otra persona de apoyo, asigne el permiso `alcaldia.panel` en un rol personalizado.
- No hay pasos manuales al actualizar (no hay cambios de base de datos).

## Novedades: firma y visación por lote («Mis firmas»)
- Menú **Mis firmas** (y aviso en el Inicio): quien firma o visa elige **uno, varios o todos** los documentos en los que ya le toca, en vez de uno por uno. Cada documento sigue las reglas de la firma individual (turno, restricción, tipo de firma, correlativo, auditoría). Máximo **25 por lote** (cambie `SGD_FIRMA_LOTE_MAX` en `~/sgd/.env`, hasta 100).
- **Firma avanzada con FirmaGob**: el lote usa el **mismo OTP** para todos los documentos y se **detiene** si el servicio rechaza uno. Si la FirmaGob real exige un código por documento (o tiene firma masiva propia), **A VALIDAR** con la documentación vigente: en ese caso repita con un código nuevo para el resto.
- La visación por lote solo aprueba; rechazar se hace documento por documento. Los documentos restringidos sin autorización no se firman por lote.
- Para probar: ingrese como Secretaria (`10000003-2`) o Alcalde (`10000002-4`) con documentos en flujo y use *Mis firmas*. No hay pasos manuales al actualizar.

## Operación
```bash
PHP=$(ls /opt/cpanel/ea-php83/root/usr/bin/php 2>/dev/null || command -v php)
cd ~/sgd && $PHP artisan pruebas:credenciales --rotar        # nuevas contraseñas de prueba (se muestran una vez)
cd ~/sgd && $PHP artisan pruebas:credenciales --desactivar   # corta de inmediato el acceso de pruebas
cd ~/sgd && $PHP artisan sgd:crear-admin 12345678-5 "Nombre" "Apellido" correo@santodomingo.cl   # administrador real (ClaveÚnica)
```
**Respaldo** (cambie `USUARIO_BD`/contraseña por los suyos):
```bash
cd ~ && mysqldump -u santod85_gestor2 -p --single-transaction santod85_gestor2 | gzip > respaldo-bd-$(date +%F).sql.gz && tar czf respaldo-archivos-$(date +%F).tgz sgd_storage sgd/.env
```
**Guarde una copia segura de `~/sgd/.env`**: sin `FILE_ENCRYPTION_KEYS` los archivos subidos no se pueden recuperar.

## Pasar a producción (cuando tenga credenciales reales)
Edite `~/sgd/.env`: `SGD_AMBIENTE=produccion`, `CLAVEUNICA_MODE=real`, `FIRMAGOB_MODE=prod`, claves/URLs de cada servicio, `MAIL_MAILER=gmail_api`. Luego vacíe la base **sin** datos de demostración (`cd ~/sgd && php artisan migrate:fresh --force`), cree el administrador real (comando anterior), desactive las cuentas de prueba y ejecute `php artisan config:clear`. Detalle en `README.md`; el sistema se niega a arrancar en producción con una configuración insegura y le dice qué corregir.

## Si algo falla
| Síntoma | Qué hacer |
|---|---|
| Aviso «sin extensión sodium» | No hace falta hacer nada: el cifrado de archivos usa OpenSSL (mismo formato). Solo se detiene si tampoco hay OpenSSL con ChaCha20-Poly1305 |
| El comando se detiene con «Faltan extensiones» | cPanel → *Select PHP Version* → PHP 8.3 → *Extensions* y vuelva a pegar el mismo comando (es seguro repetirlo) |
| «No se pudo conectar» a la base | cPanel → *Bases de datos MySQL* → agregue el usuario a la base con **todos los privilegios** y repita |
| Página 500 | `tail -n 40 ~/sgd/storage/logs/laravel.log`. Si dice «Configuración insegura para producción: …» corrija `~/sgd/.env` según el mensaje |
| 403/404 en todo | La raíz del dominio no es `~/sgd/public` (cPanel → Dominios) |
| Error de permisos en `storage` | `chmod -R u+rwX ~/sgd/storage ~/sgd/bootstrap/cache` |
| Error de certificado al abrir | Espere AutoSSL (hasta 15 min) o ejecútelo en *SSL/TLS Status* |
| No ingresa con contraseña | Revise la fecha de vencimiento (`grep ACCESO ~/sgd/.env`) y que el RUN lleve guion. Bloqueo por intentos: espere 15 min |
| No se procesa la cola | `crontab -l` debe mostrar `schedule:run` cada minuto; si no, cPanel → *Cron Jobs* → `* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/SU_USUARIO/sgd/artisan schedule:run` |
| Olvidó las contraseñas de prueba | `cd ~/sgd && php artisan pruebas:credenciales --rotar` |
