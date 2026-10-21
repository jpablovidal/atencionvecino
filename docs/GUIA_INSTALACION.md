# Guía de instalación — https://gestor.santodomingo.cl

Instalación en **2 pasos**: (1) subir el ZIP con el Administrador de archivos y (2) pegar **un solo comando** en la Terminal de cPanel. El comando crea el subdominio, la configuración con claves nuevas, las tablas, los datos de demostración, las cuentas de prueba, el cron y verifica el sitio. Se probó de punta a punta sobre MariaDB 10.11 (instalación nueva, actualización sobre la misma base y flujos completos en navegador).

> Esta instalación queda como **AMBIENTE DE PRUEBAS** (`SGD_AMBIENTE=pruebas`): integraciones simuladas (ClaveÚnica, FirmaGob, DocDigital, PISEE), correo solo a registro y ingreso con usuario y contraseña (vence a los 30 días). Para operar con integraciones reales vea «Pasar a producción».

## Antes de empezar (ya lo tiene hecho)
- Base de datos y usuario MySQL **`santod85_gestor2`** (ambos con ese nombre) con **todos los privilegios** sobre la base.
- PHP 8.3 disponible en cPanel (el comando lo comprueba y se detiene con un mensaje claro si falta algo).
- Archivo **`sgd-release.zip`** (SHA-256 `e1266e7fb4cad5dbfbeb86b932ad4edec8c45b385bd924b19bc7c1ea80cb3145`).

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
Luego, en el sitio: **Administración → APIs e integraciones → DocDigital**: elija el **modo** `demo` (la URL de DemoDoc, `https://api-demodoc.digital.gob.cl/api`, ya viene configurada; el token OAuth2 y las rutas siguen la especificación OpenAPI v3.2.514 que entregó). Después abra **DocDigital** en el menú lateral y use **Probar conexión**.

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

## Novedades: administración, versiones, visor y paso a producción
- **Segundo factor obligatorio para administradores:** la primera vez que ingrese la cuenta de administración (`10000001-6` en pruebas) el sistema pedirá escanear un QR con Google/Microsoft Authenticator y entregará **8 códigos de respaldo** (guárdelos). Es una vez por cuenta; después pide un código en cada ingreso.
- **Menú Administración (nuevo):** *Archivos: pesos y formatos*, *Áreas del municipio* (municipal, salud, educación, corporación; cada usuario en una o más), *Codificación y normas* (municipio ante el Codificador, patrón del identificador y matriz de los Decretos 7, 8, 10, 11 y 12, **A VALIDAR**: no se pudieron leer desde el entorno de desarrollo) y *Pruebas y producción*.
- **Versiones:** en cada archivo del expediente, *Versiones* permite subir una versión nueva con el motivo; se ve qué cambió, quién la subió y las firmas que trae. En cada documento, *Historial* muestra quién editó, visó y firmó cada versión. Los archivos se abren en una **ventana modal** con botón *Abrir en una pestaña nueva*.
- **Botón ClaveÚnica:** hay un componente único. Para certificar, copie el **botón oficial** de Gobierno Digital en `~/sgd/public/assets/img/claveunica/boton-claveunica.svg` (ver `LEEME.txt` y `docs/CLAVEUNICA_BOTON.md`); mientras tanto se muestra un botón de texto provisional.
- **Datos simulados:** *Administración › Pruebas y producción › Cargar datos simulados* o `cd ~/sgd && $PHP artisan sgd:simular` (solo pruebas).
- **Pasar a producción (irreversible):** desde *Pruebas y producción*. **Antes**, en `~/sgd/.env` ponga `CLAVEUNICA_MODE=real`, `FIRMAGOB_MODE=prod`, correo real (`MAIL_MAILER=gmail_api`), `APP_DEBUG=false`, `SGD_ADMIN_2FA=true`, cree un administrador real (`sgd:crear-admin`, ver Operación) e inscriba su segundo factor, y **respalde** la base y `~/sgd_storage`. El panel lista lo que falta y solo habilita el botón cuando todo está listo. Al confirmar **borra todos los procesos y expedientes de prueba** (con sus archivos), reinicia correlativos, desactiva los usuarios de demostración y conserva la configuración y la auditoría.
- **Al actualizar:** repita el comando del Paso 2 (crea tablas nuevas y conserva todo); no hay otros pasos manuales.

## Novedades: portal de trámites más claro
- La **ficha de cada trámite** ahora muestra «Última actualización», cómo se puede realizar (Online / Presencial), la descripción y secciones plegables (documentos, instrucciones, plazo y dirección, pasos), más «Publicado en» y trámites relacionados. El **inicio** suma «Lo más buscado», «Servicios municipales por temas» y «Preguntas frecuentes». No hay pasos manuales al actualizar.

## Novedades: vecino, tiempos de respuesta y DocDigital
- **Mis solicitudes**, el **detalle de cada solicitud** y el **formulario del trámite** siguen ahora el mismo estilo del portal (migas «Usted está en», secciones plegables, «Lo más buscado»).
- **Inicio de autoridades, alcaldía, OIRS y administración:** nuevo indicador de **cuántos días hábiles demora el municipio en responder**, con el detalle por **área** y por **dirección** (últimos 12 meses; los feriados no cuentan).
- **DocDigital** tiene un panel con volumen, acuses atrasados, envíos fallidos, tiempos de acuse y de respuesta, e instituciones con más movimiento. No hay pasos manuales al actualizar.

## Novedades: DocDigital v3 con su especificación real
- Con la **especificación OpenAPI** que entregó, la conexión a DocDigital quedó alineada: token OAuth2 (`/oauth/token`, Client ID y Client Secret), envío de documentos firmados, recepción, **acuse de recibo** y **devolución** (nuevo botón en el expediente, con motivo). La URL de DemoDoc ya viene configurada.
- **Para enviar a otra institución** hace falta el **id de su entidad en DocDigital**: búsquelo en *DocDigital → Buscar entidades* y anótelo en *Administración → Instituciones* (campo «Id de la entidad en DocDigital»). Si el nombre coincide exactamente con uno solo, el sistema lo resuelve solo.
- Al actualizar, repita el comando del Paso 2 (agrega una columna) y, en `~/sgd/.env`, puede **borrar** las líneas `DOCDIGITAL_PATH_*`, `DOCDIGITAL_SCOPE` y `DOCDIGITAL_TOKEN_URL` (ya no se usan). **No se probó contra DemoDoc real**: haga primero *Probar conexión*.

## Novedades: anexos de DocDigital
- Al crear una comunicación a otra institución puede **marcar archivos del expediente como anexos** (hasta 10; cambie `DOCDIGITAL_MAX_ANEXOS` en `~/sgd/.env`). Los anexos que **reciba** se guardan en el expediente si su formato está habilitado en *Administración → Archivos*; lo demás se omite y se informa el motivo.
- La URL de **producción** de DocDigital ahora viene por defecto (`https://api-doc.digital.gob.cl/api`, el servidor del Swagger); confírmela con Gobierno Digital antes de pasar a `prod`. Al actualizar, repita el comando del Paso 2 (agrega una tabla).

## Novedades: estado real de lo enviado a DocDigital
- Un documento cargado en DocDigital **queda pendiente de despacho** hasta que la **Oficina de Partes lo despache en DocDigital**; el sistema no puede hacerlo. Ahora el expediente y el panel de DocDigital muestran el estado real («cargada; pendiente de despacho», «despachada; pendiente de acuse», «recibida y acusada»), y se actualiza solo con cada sincronización. Al actualizar, repita el comando del Paso 2 (agrega dos columnas).
- Para **devolver** un documento recibido, el usuario de la integración necesita ese permiso en DocDigital (si no, DocDigital responde 403): pídalo a Gobierno Digital.
- En DemoDoc su entidad aparece con el id **284** (`DOCDIGITAL_ENTIDAD_ID`); en producción confírmelo con la prueba de conexión.

## Novedades: evidencias de DocDigital y credenciales de producción
- En **DocDigital → «Evidencias de las llamadas»** (solo quien gestiona DocDigital) puede **activar por 24 horas** el registro del JSON enviado y de la respuesta de cada llamada (ingreso, acuse, devolución, consultas), y descargarlo en **JSON** o **ZIP** (un archivo por llamada) para el documento de evidencias de la certificación. No guarda credenciales, el token ni los archivos (los base64 se reemplazan por su tamaño y huella SHA-256). Las capturas de pantalla de DemoDoc las debe tomar usted.
- DemoDoc y producción tienen **credenciales e ids distintos**. Ahora hay variables propias para producción (`DOCDIGITAL_PROD_*`) que reemplazan a las generales solo con modo `prod`; así puede dejar las de DemoDoc en `DOCDIGITAL_CLIENT_*`.
- Según el manual de integración, `entidadDestinataria` se envía ahora en la consulta **y** en el cuerpo del acuse y la devolución, y los nombres de archivo se normalizan a 3–150 caracteres.
- Al actualizar, repita el comando del Paso 2 (agrega una tabla).

### Configurar DocDigital PRODUCCIÓN (paso a paso)
1. **Guardar las credenciales de producción** (no se muestran ni quedan en el historial):
```bash
cd ~/sgd && read -rp "Client ID PRODUCCIÓN: " CID && read -rsp "Client Secret PRODUCCIÓN: " CSEC && echo && grep -v '^DOCDIGITAL_PROD_CLIENT_' .env > .env.nuevo && printf "DOCDIGITAL_PROD_CLIENT_ID='%s'
DOCDIGITAL_PROD_CLIENT_SECRET='%s'
" "$CID" "$CSEC" >> .env.nuevo && chmod 600 .env.nuevo && mv .env.nuevo .env && unset CID CSEC && echo "Credenciales de producción guardadas"
```
2. **Comprobar el acceso sin cambiar el modo** (solo lectura: pide el token y lista la entidad asociada; no envía ni descarga documentos):
```bash
cd ~/sgd && CID=$(grep '^DOCDIGITAL_PROD_CLIENT_ID=' .env | cut -d= -f2- | tr -d "'") && CSEC=$(grep '^DOCDIGITAL_PROD_CLIENT_SECRET=' .env | cut -d= -f2- | tr -d "'") && U=https://api-doc.digital.gob.cl/api && TOK=$(curl -s -u "$CID:$CSEC" -X POST "$U/oauth/token" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["access_token"] ?? "";') && if [ -z "$TOK" ]; then echo "SIN TOKEN: revise las credenciales"; else echo "Token OK"; curl -s -H "Authorization: Bearer $TOK" "$U/entidades/token"; echo; fi; unset CID CSEC TOK
```
   En la respuesta aparece `entidad_id` y `entidad_nombre`: **anote el id** (no es el 284 de DemoDoc).
3. **Guardar el id de la entidad de producción** (reemplace `NNN`):
```bash
cd ~/sgd && grep -v '^DOCDIGITAL_PROD_ENTIDAD_ID=' .env > .env.nuevo && echo "DOCDIGITAL_PROD_ENTIDAD_ID=NNN" >> .env.nuevo && chmod 600 .env.nuevo && mv .env.nuevo .env && echo OK
```
4. **No cambie a modo `prod` todavía** si este sitio sigue siendo de pruebas: con modo `prod` el sistema **importa documentos reales** cada 10 minutos y los envíos serían reales. Antes, Gobierno Digital debe **certificar la integración** (formulario https://digital.gob.cl/incidencia con el documento «Certificación de Integraciones para API DocDigital» y las evidencias de DemoDoc).
5. Cuando esté certificado y quiera operar de verdad: en el sitio, **Administración → APIs e integraciones → DocDigital → Modo: Producción** (o `DOCDIGITAL_MODE=prod` en `.env`), luego **DocDigital → Probar conexión** (solo lectura) y el id del tipo de documento (`DOCDIGITAL_PROD_TIPO_DOCUMENTO_ID`) que muestre «Tipos de documento». Para el sistema ya en producción vea «Pasar a producción».

## Novedades: botón oficial de ClaveÚnica
- El botón de ingreso ahora es el **botón oficial** del kit de Gobierno Digital (azul «Iniciar sesión» con el ícono; en el encabezado oscuro, la variante de alto contraste). No hay que subir ningún archivo.
- Se corrigió un punto que habría fallado la certificación: los datos del usuario se consultan en `accounts.claveunica.gob.cl` (antes `www.`).
- **Cierre de sesión:** «Cerrar sesión» ahora también cierra la sesión de ClaveÚnica. Para que ClaveÚnica vuelva al sitio, la **Logout URI** debe estar registrada; en `~/sgd/.env` puede fijarla con `CLAVEUNICA_LOGOUT_URI=https://gestor.santodomingo.cl/` (opcional; por defecto la portada).
- Para probar con **sandbox**: use las credenciales de sandbox y los RUN `44444444-4`, `55555555-5`, `88888888-8` o `99999999-9` (contraseña `testing`). Detalle en `docs/CLAVEUNICA_BOTON.md`.
- Al actualizar no hay pasos manuales nuevos.

## Novedades: carga de la clave JSON de Drive y Gmail
- En **Administración → APIs e integraciones → Repositorio de documentos (Google Drive)** (y en *Correo institucional*) ahora hay un botón **«Cargar la clave JSON de la cuenta de servicio»**: suba el archivo descargado de Google Cloud y el sistema lo valida y lo guarda en el servidor (carpeta `~/sgd_secrets`, permisos 600). La clave **no se muestra**; solo verá el correo de la cuenta cargada. Ya no necesita subirlo por el administrador de archivos ni escribir la ruta.
- Marque **«Usar esta configuración de la base de datos»** y guarde para que se aplique; luego **Validar configuración** o «Probar conexión con Drive».
- En los campos **ID de la unidad compartida** y **ID de la carpeta raíz** puede pegar la **URL completa** de Drive: el sistema extrae el ID.
- Al actualizar no hay pasos manuales nuevos.

## Novedades: todas las integraciones se configuran por pantalla
- En **Administración → APIs e integraciones** cada servicio (ClaveÚnica, FirmaGob, Correo/Gmail, DocDigital, Repositorio de Drive…) se configura **en pantalla**, en tarjetas, sin editar el archivo `.env`:
  - **ClaveÚnica:** ambiente activo (Sandbox / QA / Producción) y un bloque de credenciales por ambiente (client_id, client_secret, Redirect URI, Logout URI), datos para el trámite en Cerofilas y referencia del manual.
  - **FirmaGob:** credenciales de **pruebas** y de **producción**, entidad y propósito.
  - **Correo:** método de envío, remitente (correo del Workspace a suplantar), **carga del JSON** de la cuenta de servicio y **«Enviar correo de prueba»**.
  - **DocDigital:** bloque DemoDoc y bloque Producción, y **«Probar conexión»** (solo lectura).
- Los **secretos** (client_secret, secret, tokens) se escriben ahí y se guardan **cifrados** en la base (aparece «guardado, cifrado»); **nunca se vuelven a mostrar**. Déjelos en blanco para conservar el actual.
- Marque **«Usar esta configuración de la base de datos»** (viene marcada en la primera vez) y guarde. «Volver a .env» devuelve el control al archivo.
- Al actualizar no hay pasos manuales nuevos. Lo que ya tenía en `.env` sigue valiendo hasta que lo reemplace en pantalla.

## Novedades: validar las credenciales desde el panel
- Cada integración tiene **«Validar credenciales»** (en DocDigital, «Probar conexión»), que prueba de verdad si funcionan: **ClaveÚnica** consulta si acepta el client_id y client_secret (sin iniciar sesión de nadie), **Correo** pide un token a Google con la delegación de dominio, **DocDigital** consulta token, entidad y tipos, y el **Repositorio** prueba Drive. No hay evaluación de «etapa» (demo, pruebas o producción): usted ingresa las credenciales y el sistema le dice si funcionan.
- **FirmaGob:** «Probar la firma» firma un PDF de ejemplo con el RUN del titular y su OTP (no queda guardado). **Correo:** «Enviar correo de prueba».
- Ya no se exige que el dominio de ClaveÚnica termine en «.gob.cl».
- **Si una pantalla da error 500 después de actualizar:** pegue en la Terminal de cPanel `cd ~/sgd && php artisan route:clear && php artisan view:clear && php artisan config:clear` (la actualización ya lo hace sola desde esta versión).

## Novedades: superadministrador (ver todas las vistas)
- Un administrador puede ser **superadministrador**: ve **todas las vistas** del sistema y el contenido de todas las unidades (menos lo restringido), para que pueda probar que todo esté en orden. Mantiene el segundo factor obligatorio.
- Para asignarlo, en la Terminal de cPanel (reemplace el RUN): `cd ~/sgd && php artisan sgd:superadmin 12345678-5`. Para ver qué administradores hay: `php artisan tinker --execute='foreach (App\Models\Usuario::where("rol","admin")->get(["run","nombres","apellidos","activo"]) as $u) echo $u->run." ".$u->nombres." ".$u->apellidos."\n";'`. Después **cierre sesión y vuelva a entrar**.
- Quien ya es superadministrador también puede otorgarlo a otros en **Administración → Usuarios** (casilla «Superadministrador(a)»). Para quitarlo: `php artisan sgd:superadmin RUN --quitar`.
- Al actualizar, repita el comando del Paso 2 (agrega una columna). Asígnelo solo a pocas personas.

## Novedades: ingreso con el directorio de funcionarios (sistemas_usuarios)
- Ahora hay **un solo botón** de ClaveÚnica. Si el RUN figura **habilitado y vigente** en su base `sistemas_usuarios` (tabla `sistemas_users`), la persona entra como **funcionario(a)** (y puede elegir además su espacio de vecino(a)); si no figura, es vecino(a). La primera vez se crea su usuario automáticamente (rol «funcionario», unidad = su dirección) y quien deja de figurar pierde el acceso de funcionario(a).
- **Configúrelo** en **Administración → APIs e integraciones → Directorio de funcionarios**: servidor, base (`sistemas_usuarios`), usuario, contraseña (se guarda cifrada) y tabla. Pulse **Validar credenciales**. Luego marque «Activar el directorio de funcionarios» y guarde. Para cargos especiales (alcalde, secretario(a)) o firma, edite al usuario en **Administración → Usuarios**.
- **Seguridad:** el sistema nunca lee las contraseñas de esa base. Su tabla tiene una columna `password` que parece guardar claves en texto plano: elimínela o rotéelas, y use un usuario MySQL **de solo lectura** para esta conexión.
- Las cuentas creadas a mano (administración) no dependen del directorio.
- Al actualizar, repita el comando del Paso 2 (agrega dos columnas).

## Novedades: Codificador (API oficial) y municipio por defecto
- Por defecto el sistema queda con la **Municipalidad de Santo Domingo** (`PE-MUN-00243`, padre `PE-SUB-00573`): el instalador la deja en el catálogo del Codificador y su código identifica los expedientes y documentos (por ejemplo `PE-MUN-00243-EXP-2026-000001`). Si ya había elegido otro municipio en Administración → Codificación, no cambia.
- **Configuración:** en **Administración → APIs e integraciones → Codificador**: modo (Manual o Sincronización por API), URL (vacía = `https://codificacion.digital.gob.cl/api`), versión (`v1`) e id de la codificación «Instituciones» (1). Pulse **Validar credenciales**: confirma que la API responde y que `PE-MUN-00243` existe, mostrando su OID.
- **Sincronizar:** con modo «Sincronización por API», `cd ~/sgd && php artisan codificador:sincronizar` trae las instituciones al catálogo (no borra nada). La carga por CSV sigue disponible.
- No se pudo probar contra el servicio real; el documento tiene detalles por validar (ver `docs/interop/codificador-api.md`).
- Al actualizar, repita el comando del Paso 2 (agrega tres columnas y deja el municipio en el catálogo).

## Novedades: un solo ingreso en `/login`
- Hay **una sola ruta de ingreso, `https://gestor.santodomingo.cl/login`**, y **un solo botón** de ClaveÚnica. Las rutas `/ingreso/ciudadano` y `/ingreso/funcionario` quedaron reemplazadas (redirigen a `/login`). Si la persona figura en el directorio `sistemas_usuarios` entra como funcionario(a); si no, como vecino(a).
- Si su ClaveÚnica tiene registrada como Redirect URI `…/claveunica/callback`, no cambia nada: solo cambia la dirección que inicia el ingreso.
- Al actualizar no hay pasos manuales nuevos.


## Novedades: elegir la IA del asistente de redacción
- En **Administración → APIs → Asistente de redacción (IA)** elija el **proveedor**: Google AI Studio (Gemini), Alibaba Qwen, OpenRouter, Groq, Mistral, Anthropic u «Otro compatible con OpenAI» (con su dirección HTTPS).
- Pegue la **clave de ese proveedor** (se guarda cifrada) y, si quiere, el **nombre del modelo** (solo el nombre, por ejemplo `gemini-flash-latest` o `qwen-plus`; vacío usa el sugerido). Marque **Habilitado**, guarde y pulse **«Validar credenciales»**: hace una consulta mínima sin datos de personas.
- Para cambiar de IA basta cambiar el proveedor y la clave. Los niveles gratuitos pueden usar lo que se envía para mejorar sus modelos: el sistema quita RUN, correos y teléfonos, pero no use la IA con información reservada. No se probó contra los proveedores reales; los nombres de modelo sugeridos son **A VALIDAR**.
- Al actualizar no hay pasos manuales nuevos.

## Novedades: ClaveÚnica con sus URI de producción
Datos registrados en ClaveÚnica (coinciden con el sistema):
| Campo | Valor |
|---|---|
| Website | `https://gestor.santodomingo.cl/login` |
| Redirect_Uri producción | `https://gestor.santodomingo.cl` |
| Logout URI | `https://gestor.santodomingo.cl` |
| Redirect URI (callback) | `https://gestor.santodomingo.cl/auth/callback` |

- El sistema recibe la respuesta en **`/auth/callback`** (la ruta antigua `/claveunica/callback` sigue funcionando).
- **«invalid_client»** en *Validar credenciales* significa que ClaveÚnica rechaza el **client_id o el client_secret**: revise que sean los de **producción**, sin espacios al inicio o final, y que estén habilitados. Para ver la respuesta cruda (las claves no se muestran ni quedan en el historial):
```
read -rp "client_id: " CID; read -rsp "client_secret: " CSE; echo
curl -sS -X POST https://accounts.claveunica.gob.cl/openid/token/ -d "client_id=$CID" -d "client_secret=$CSE" -d "grant_type=authorization_code" -d "redirect_uri=https://gestor.santodomingo.cl/auth/callback" -d "code=prueba" -d "state=prueba"; echo; unset CID CSE
```
Si responde `invalid_grant` las credenciales fueron aceptadas; `invalid_client` indica que ClaveÚnica no las reconoce y debe pedir/confirmar las de producción a Gobierno Digital.
- Al actualizar no hay pasos manuales nuevos.

## Novedades: ingreso sin ClaveÚnica con usuario y contraseña del directorio
- En la página de ingreso aparece **«Ingresar con usuario y contraseña»** (`/login/usuario`) para funcionarios(as): usa el **`username`** y la contraseña de la tabla `sistemas_users` de la base `sistemas_usuarios`. Ya **no se usa el RUN** (el ingreso de pruebas por RUN deja de mostrarse mientras esta vía esté activa).
- La contraseña se verifica **solo** contra el hash de la columna **`pass`** (bcrypt, `$2y$…`); **nunca** contra la columna `password`. Si una cuenta no tiene hash en `pass`, no puede entrar por esta vía (use ClaveÚnica). **Le recomiendo eliminar la columna `password`** (texto plano) de esa base.
- Solo entra quien figura habilitado, con credencial «Vigente» y sin fecha de término vencida; la **administración no puede entrar así** (solo ClaveÚnica con segundo factor). Se bloquea tras 5 intentos fallidos por 15 minutos.
- Se enciende/apaga en **Administración → APIs → Directorio de funcionarios → «Permitir ingresar con usuario y contraseña»** (viene encendido cuando el directorio está activo).
- Corrección: una cuenta desactivada a mano que seguía en el directorio daba error 500 al ingresar; ya no.
- Al actualizar no hay pasos manuales nuevos.

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
