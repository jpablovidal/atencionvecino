# Limitaciones y cosas no verificadas

**No se presenta como hecho lo que no se probó.**

## Depende de credenciales o ambientes reales (NO probado contra el servicio real)
| Integración | Estado | Qué falta |
|---|---|---|
| **ClaveÚnica** | Implementado (OIDC) y probado con simulación (`/dev/login`, `ClaveUnicaService` sin prueba HTTP real). | `client_id/secret`, URL de retorno registrada; validar formato de `userinfo`. |
| **FirmaGob v2** | `HttpFirmaClient` probado con `Http::fake` (JWT HS256, OTP, checksum, 5 MB, sin redirecciones). | Credenciales y ambiente `test`; **validar URLs de prod/test, nombres de claims y de la respuesta (A VALIDAR)**; verificar firma atendida con OTP real; lógica de reintentos ante 429. |
| **Casilla Única / Notificador** | Modo `outbox` (operativo, sin envío real) y panel de gestión; modo `api` genérico (token fijo u OAuth2) **no basado en el contrato oficial**: la guía de integración no fue accesible desde el entorno. | Contrato y credenciales (A VALIDAR). |
| **Gmail API (cuenta de servicio)** | Transporte probado con cliente HTTP simulado: JWT RS256, único scope, `sub`=buzón, éxito, fallo, reintento, cuota, inyección de cabeceras. | JSON real, delegación de dominio autorizada, envío real. |
| **LLM** | Probado con respuestas simuladas; apagado por defecto. | Clave de API y revisión de la política de uso de IA del municipio. |
| **Códigos CPAT/OAE** | El seed usa **ejemplos** con el formato `PA-UNI00002-00001`. | Cargar los códigos reales del catálogo CPAT. |

## Reglas de firma asumidas
Ver `DECISIONES.md` #1, #2 y #5 (ágil=1 / clásico=2 por defecto; primera firma del decreto alcaldicio por alcalde o delegado; número tras la última firma).

## Técnicas
- **MySQL/MariaDB no disponible** en el entorno de construcción: las pruebas corren sobre **SQLite**. `01_schema.sql` se genera desde las migraciones con gramática MySQL y se verifica sintaxis/estructura por pruebas, pero **no se ejecutó en un servidor MySQL real**: importarlo en un entorno de pruebas antes de producción. La concurrencia del correlativo se probó con procesos paralelos en SQLite; el mismo patrón (UPDATE atómico + bloqueo InnoDB) es válido en MySQL pero **no se probó en MySQL**.
- **Triggers / GRANT por tabla:** pueden no estar disponibles en hosting compartido (cPanel asigna privilegios a nivel de BD completa). Sin ellos, la inmutabilidad de `audit_log` se apoya en el modelo y en la **detección** por cadena HMAC; un atacante con acceso directo a MySQL puede alterar y recalcular la cadena **solo si conoce `AUDIT_HMAC_KEY`** (que no está en la BD).
- **PDF/A y firma PAdES de la copia pública:** la copia pública y los PDF de versión se generan con Dompdf (no PDF/A). Con FirmaGob real el PDF firmado está disponible solo como documento de trabajo; la copia pública incluye la hoja de certificado pero no fusiona la firma PAdES (A VALIDAR si la norma exige copia autorizada con firma visible).
- **Antivirus:** no hay ClamAV; la validación de subidas es estructural. PDF con contenido activo legítimo (formularios con JS) serán rechazados.
- **Archivos en memoria:** se cifra/descifra en memoria (límite 10 MB por archivo, 5 MB para firmar con FirmaGob); `memory_limit` ≥ 256 MB recomendado.
- **TinyMCE:** requiere `style-src 'unsafe-inline'`. Idioma `es` desde `tinymce-i18n` (licencia A VALIDAR).
- **Accesibilidad:** se verificó con revisión manual básica (foco visible, etiquetas, "saltar al contenido", contraste del color primario sobre blanco). **No** se ejecutó una auditoría WCAG automatizada completa (axe/lighthouse) ni pruebas con lector de pantalla.
- **Larastan:** ver `DECISIONES.md` #21.
- **Concurrencia de sesiones, bloqueo de cuentas, rotación automática de claves y reporte automático al CSIRT:** no implementados.
- **Interoperabilidad (PISEE), integración con SIAPER/otros sistemas:** no implementadas.
- **Sesiones `__Host-`:** la cookie requiere HTTPS; en `http://localhost` Chromium la acepta (verificado), otros navegadores pueden no hacerlo.
- El **panel de incidentes** registra hitos pero no envía reportes; plazos legales A VALIDAR.

## Interoperabilidad (PISEE, Codificador, DocDigital) — **no verificada contra los servicios reales**
- **Sin acceso a las especificaciones oficiales** (los hosts de Gobierno Digital no eran alcanzables desde el entorno de construcción; solo se pudieron consultar resúmenes de búsqueda). Los contratos no se inventaron: son configurables y están marcados **A VALIDAR** en el código (`HttpDocDigitalClient`, `PiseeService`, `CodificadorService::sincronizar`) y en `docs/NORMATIVA.md` §11–13.
- **PISEE:** solo consumidor, con mTLS y una cabecera de identificación configurable. Si el nodo exige otro mecanismo (firma de mensajes, cabeceras adicionales, SOAP, etc.), hay que adaptar `PiseeService::llamar()`. Requiere adhesión a los T&C PISEE y un nodo instalado en la municipalidad. No se publican servicios propios como proveedor.
- **Codificador:** catálogo local; formato de códigos y esquema de la API no validados. La vía confiable hoy es la **carga CSV**.
- **DocDigital:** rutas/URLs/token y formas JSON a validar en **DemoDoc**; requiere Administrador Principal e interlocutor ante la SGD. Solo se envían PDF con FEA real (`pdf_firmado_*`, devuelto por FirmaGob); la numeración propia de DocDigital no se integra con el correlativo del SGD. No se implementa visación/firma dentro de DocDigital ni acuse de recepción/lectura (solo el ID de envío).
- La sincronización de recibidos asume que la API entrega el PDF en base64 en la lista (forma A VALIDAR); los entrantes no crean expedientes automáticamente: quedan en la bandeja de la unidad receptora (`DOCDIGITAL_UNIDAD_RECEPCION`).

- **Recepción (expediente por comunicación entrante):** depende de que la API de DocDigital entregue el PDF en la lista de recibidos y de un endpoint de **acuse de recibo** (`DOCDIGITAL_PATH_ACUSE`, forma A VALIDAR). El acuse a través de la API no se probó contra DocDigital real; en `outbox` es una referencia manual. No se interpreta el contenido de la comunicación (sin clasificación automática ni plazos de respuesta). El identificador único usa el código de institución del Codificador (A VALIDAR el esquema oficial).

- **Google Drive (unidad compartida):** probado solo contra un **Drive simulado** (`tests/Support/FakeDrive.php`) que reproduce el contrato de la API v3 (`supportsAllDrives`, carpetas, md5). **No se ha probado contra Google real**: valide con *Probar conexión* y una migración piloto. Dependencia: disponibilidad/cuotas de Drive; las escrituras fallan cerradas.
- **Cifrado + Drive:** si se pierden las claves de `sgd_secrets`, los archivos en Drive no se pueden recuperar. El respaldo de claves es responsabilidad de la institución.
- **Firmas en la copia pública:** el sello visual (estilo/posición) se estampa en la copia pública. **No** reemplaza la firma electrónica avanzada: el PDF firmado por FirmaGob conserva su propia representación y sus coordenadas en el proveedor son **A VALIDAR**.
- **Secretos en BD (opcional):** `SGD_API_SECRETOS_EN_BD=1` guarda secretos cifrados con `APP_KEY`; quien obtenga BD **y** `.env` los descifra. Por defecto está apagado y los secretos viven solo en `.env`/`sgd_secrets`.
- **Roles personalizados:** el techo por rol base y los permisos fijos del sistema son una decisión de diseño; no existen permisos por expediente ni por unidad (la visibilidad por unidad sigue las reglas de acceso existentes).
- **Usuarios conectados:** se basa en la tabla de sesiones (actividad dentro del tiempo de vida de sesión); no detecta usuarios con la pestaña abierta pero inactiva más allá de ese plazo.
- **Log de aplicación:** la redacción es por patrones (RUN, correos, tokens, rutas); puede dejar pasar datos con formatos no previstos. Revíselo antes de compartirlo fuera de la institución.
- **Logo y accesibilidad:** el sistema exige texto alternativo y valida contraste de colores, pero no puede juzgar si el contenido del logo es legible.
- **Editor (TinyMCE 7, GPL):** se omiten por seguridad los plugins de medios (`media`, iframes), `codesample` y emoticonos. El sanitizador del servidor es la autoridad: lo que el editor muestre y el servidor elimine (p. ej. un estilo no admitido) desaparece al guardar. Fuentes: solo las de la lista (Arial, Verdana, Tahoma, Georgia, Times, Courier, Roboto); en PDF Dompdf las aproxima con las fuentes que dispone.
- **Imágenes en PDF:** se incrustan con Dompdf; imágenes muy grandes o muchas imágenes aumentan memoria y tamaño del PDF. Las imágenes subidas no se pueden borrar (solo dejar de usarlas); no hay depuración de huérfanas.
- **Visibilidad de formatos:** se basa en la unidad del usuario (una unidad por persona); no hay jerarquía de unidades (una dirección no hereda a sus departamentos).
- **PDF de ejemplo de formatos:** usa datos ficticios y no tiene validez; no incluye firmas ni folio.
- **Reconocimiento de firmas:** solo PDF (PAdES/PKCS#7 desprendida y CAdES); otros formatos figuran «No aplica». No se revisa revocación (CRL/OCSP) ni sellos de tiempo cualificados; firmas con RSA-PSS o formatos heredados (`adbe.x509.rsa_sha1`) quedan «no verificable». Sin `SGD_FIRMA_CA_BUNDLE` la confianza del emisor **no se afirma**; la lista de CA de prestadores acreditados en Chile y qué política de certificado equivale a firma «avanzada» (Ley 19.799) son **A VALIDAR**. Probado con certificados autofirmados de prueba, no con firmas reales de FirmaGob/Acepta/E-Sign. Con `FIRMAGOB_MODE=mock` el PDF «firmado» no trae firma criptográfica y se informa como tal.
- **Panel de recursos:** en hosting compartido (CloudLinux/LVE) la CPU y la RAM leídas de `/proc` son las del **equipo**, no las de su plan, salvo que el hosting exponga cgroups; lo no legible se muestra «sin dato». La CPU se mide en una ventana de ~250 ms (instantánea, no promedio). El histórico depende de que el cron de cPanel ejecute `schedule:run` cada minuto.
- **Inventario de componentes:** lee archivos instalados; no consulta bases de vulnerabilidades (use `composer audit`). Las versiones del front-end se leen de los encabezados de cada archivo (si un proveedor cambia el formato, se muestra «—»). La detección de CDN es estática (vistas, CSS, JS propios y activos): no ve recursos que un script construya dinámicamente; la CSP del navegador es la barrera efectiva.
- **Ambiente de pruebas con contraseña:** `SGD_AMBIENTE=pruebas` evita ClaveÚnica y entra como el usuario real con ese RUN (incluido administrador y alcalde), sin segundo factor. Es una herramienta de **revisión temporal**: vence sola (máx. 45 días), se bloquea por RUN, y jamás debe convivir con datos reales. El procedimiento de instalación se verificó en MariaDB 10.11 (no en el MySQL del hosting): pruebe la migración en su servidor y revise el registro si algo falla.
- **Pentest:** la revisión (`docs/PENTEST.md`) la hizo el equipo con asistencia de IA; no sustituye a un pentest externo ni cubre la infraestructura del hosting (WAF, aislamiento entre cuentas, TLS del servidor).
- **Mapa de conexiones:** la geolocalización es **por país y aproximada** (base «IP to Country Lite», datos a octubre de 2026): VPN, proxy, redes móviles y CDN pueden aparecer en otro país; algunas direcciones se atribuyen a países vecinos o por registro. El mapa no muestra ciudades. Las conexiones **salientes** solo incluyen las que hace la aplicación con el cliente HTTP de Laravel (ClaveÚnica, FirmaGob, DocDigital, PISEE, Codificador, Google, IA); no ve el tráfico del hosting ni del navegador. La IP del visitante es la que informa el servidor: detrás de proxy/CDN requiere `TRUSTED_PROXIES`. El conteo agrega una escritura por petición (bajo costo; se puede desactivar con `SGD_REGISTRAR_CONEXIONES=false`). Las IP son datos personales (Ley 19.628/21.719): definir retención y base legal con el encargado de datos.
- **Código malicioso:** ninguna defensa elimina el riesgo de **contenido engañoso** (p. ej., un borrador de IA con texto falso o un enlace https a un sitio de *phishing* dentro de un documento): el sistema neutraliza código ejecutable, advierte y exige revisión humana, pero no valida la veracidad. Los enlaces `https://` se permiten en los documentos.

## Accesibilidad
Ver `docs/ACCESIBILIDAD.md`: pruebas automáticas (PHPUnit y axe-core en navegador real, 0 violaciones en las páginas auditadas) y revisión de teclado, **pero sin auditoría manual con lectores de pantalla ni certificación externa**; los PDF generados no son PDF/UA; el canal de contacto y su plazo de respuesta (`SGD_ACCESIBILIDAD_CONTACTO`) deben definirse por la Municipalidad.

## Gestión de trámites por dirección
- La **unidad del usuario** define el alcance; el sistema no valida que sea la correcta (dato administrativo). Un usuario sin unidad no puede crear trámites.
- Un trámite tiene **una dirección propietaria**; la co-gestión entre direcciones se hace mediante el administrador (que sí puede asignar varias direcciones *responsables* de la bandeja).
- Los textos son **texto plano** (sin formato enriquecido ni enlaces activos) por seguridad; los adjuntos del ciudadano usan los mismos tipos y límites ya existentes (PDF/JPG/PNG/TIFF, 10 MB).
- El catálogo público no tiene categorías temáticas (solo búsqueda y filtro por dirección), costos, horarios ni sucursales, y el trámite elegido solo se recuerda si se parte desde el botón de la ficha (no si se escribe la URL del formulario sin haber ingresado). No hay aprobación en dos pasos (quien tiene `tramites.publicar` publica directamente), ni expiración automática de trámites.
- No se verifica que el contenido sea *veraz* (ver riesgo residual en `docs/PENTEST.md` §9.5).
- **Sin extensión `sodium`:** se usa la alternativa con OpenSSL (misma salida que libsodium). Requiere OpenSSL ≥ 1.1 con `chacha20-poly1305`; el rendimiento es similar. La interoperabilidad se verificó contra libsodium en las pruebas, pero la alternativa no pasó por una auditoría criptográfica independiente.
- **Capturas de los manuales:** las imágenes de `public/assets/manual` corresponden al diseño anterior; el texto de los manuales está actualizado, pero las capturas deben regenerarse. El inicio no tiene los bloques «Noticias», «Cumpleaños» ni «Alertas normativas» de la intranet de referencia (no hay fuente de datos para ellos).

## Archivo municipal (microformas)
- **No sustituye la certificación legal.** El acta se adjunta como PDF; el sistema solo informa si detecta firma electrónica y **no** valida quién firmó ni su competencia. La normativa aplicable está en `docs/NORMATIVA.md` §7 (**A VALIDAR**).
- La disposición de originales (eliminación o transferencia) **solo se registra**; el sistema no ejecuta nada sobre documentos físicos.
- Una microforma certificada **no se puede anular ni modificar**; una corrección requiere registrar una nueva microforma (o intervención técnica documentada en base de datos).
- Los archivos maestros tienen el límite de 10 MB por archivo (PDF, JPG, PNG, TIFF) y 300 por microforma; no se procesan rollos de microfilm ni se hace OCR. No hay catálogo de series, tabla de retención ni préstamo de soportes físicos.
- El acceso es por permisos (no por unidad): quien tiene `archivo.ver` ve todas las microformas.

## Panel DocDigital
- **Contrato sin validar:** la especificación (api-doc.digital.gob.cl, Swagger) no fue accesible desde el entorno de construcción. La URL del token OAuth2, las rutas (`DOCDIGITAL_PATH_*`) y las formas JSON de enviar, recibidos y acuse son una propuesta mínima y **deben ajustarse a la especificación y probarse en DemoDoc** antes de usar el modo Producción. El panel solo gestiona esas tres operaciones (enviar, recibidos, acuse); otras funciones de la API (consultas de estado, anulaciones, etc.) no están implementadas.
- «Probar conexión» obtiene el token y consulta la ruta de recibidos solo para leer el código HTTP; un resultado correcto no garantiza que el formato de los documentos coincida con lo esperado.
- DocDigital exige PDF con firma electrónica avanzada y un Administrador Principal registrado; el sistema no lo verifica contra DocDigital.
- Las credenciales entregadas por la institución son de **producción**: no las use desde el ambiente de pruebas con envíos reales; use DemoDoc si existe.

## Casilla Única e inicio dual
- **Contrato sin validar:** la guía https://wikiguias.digital.gob.cl/es/Manuales/integracion-casillaunica no fue accesible desde el entorno de construcción. La URL, la autenticación (token u OAuth2) y el JSON de envío/confirmación son una propuesta mínima; hasta validarlos, mantenga el modo **outbox** (evidencia manual). «Probar conexión» solo obtiene el token: no envía notificaciones.
- El inicio dual asume que ingresar como funcionario(a) con ClaveÚnica acredita también su identidad de vecino(a) (mismo RUN). En el acceso de **pruebas** (usuario y contraseña) la identidad la declara la cuenta de prueba.
- El conflicto de interés se controla por **RUN**: no detecta parentesco ni solicitudes presentadas por terceros en nombre del funcionario.

## Progreso y pasos
- La barra de carga de vistas es **aproximada** (no mide bytes de la página): avanza por fases y se completa al terminar la carga. La barra de la **subida de archivos sí es real** (bytes enviados al servidor); la fase posterior de verificación y cifrado en el servidor no tiene porcentaje (se muestra como paso en curso).
- Tras una subida, la URL puede quedar en la de la acción si el servidor responde sin redirección (p. ej. error 419); recargar vuelve a la vista anterior.
- Los pasos se aplican a la solicitud del vecino, el trámite y la microforma; los demás formularios (breves) se envían de una vez.

## Roles Autoridad y OIRS, plazos y tableros
- **Plazos A VALIDAR:** la Ley 19.880 cuenta días **hábiles** y existen feriados y suspensiones de plazo; el sistema cuenta días corridos por defecto (o lunes a viernes con `SGD_PLAZOS_HABILES=true`) y **no modela feriados**. El criterio de suspensión mientras el solicitante responde una devolución es una decisión de diseño pendiente de validar con la asesoría jurídica.
- La **etapa** es una derivación del estado de la solicitud, el expediente y sus documentos; no distingue la sub-etapa dentro de la visación (solo quién debe visar o firmar a continuación).
- Los tableros calculan sobre **todas** las solicitudes en el servidor: con decenas de miles de registros convendrá agregar tablas de resumen.
- Autoridad y OIRS ven **nombre y RUN** del solicitante (necesario para la consulta); cada acceso queda auditado con el RUN enmascarado. No hay exportación a CSV/Excel en esta versión.
- Las instalaciones existentes reciben los roles al ejecutar las migraciones; las **cuentas de prueba** se crean con `pruebas:seguimiento` (el instalador lo hace al actualizar un sitio de pruebas). Si ya existía un rol personalizado con clave `autoridad` u `oirs`, la migración no lo reemplaza: renómbrelo antes.

## Rol Transparencia (activa y pasiva)
- **Plazos y reglas A VALIDAR:** 20 días hábiles de respuesta, prórroga de 10 días hábiles, derivación por incompetencia y suspensión por antecedentes son criterios de la Ley 20.285 tal como se entienden hoy; el sistema **no** modela la notificación y oposición de terceros, los plazos de amparo ante el Consejo para la Transparencia ni los plazos de derivación (2 días) como alertas automáticas. Valide con la asesoría jurídica.
- **Feriados:** no vienen precargados; mientras no se registren, los días hábiles cuentan de lunes a viernes.
- El **catálogo de transparencia activa** es referencial; la publicación en el portal de transparencia es **externa**: el sistema solo registra que se publicó (fecha y enlace), no publica ni verifica el enlace.
- La respuesta de una dirección a un requerimiento es **texto**; los documentos que deban entregarse se agregan al expediente por Transparencia. No hay recordatorios por correo de requerimientos próximos a vencer (se ven en el tablero).
- La derivación a otro órgano **no envía** el oficio: registra que se envió por DocDigital, oficio o correo y cierra el expediente; notifique al solicitante con el documento de derivación.
- Los reportes son un instrumento interno: **no reemplazan** el formato de reporte que exija el Consejo para la Transparencia.

## Portal del vecino
- **Diseño de referencia no verificado:** `tramites.santodomingo.cl` y ChileAtiende no fueron accesibles desde el entorno de construcción (política de red). El portal replica el patrón habitual de estos sitios (buscador destacado, más solicitados, exploración por dirección, ficha con requisitos), no su apariencia exacta. Si desea una réplica más fiel, adjunte capturas de las pantallas.
- La búsqueda es textual simple (nombre, descripción e instrucciones): no corrige errores de tipeo ni usa sinónimos. «Más solicitados» y «Más buscados» salen del número de solicitudes por trámite, no de las búsquedas.
- No hay categorías temáticas (p. ej. «vivienda», «salud») ni costos/arancel del trámite: no existen como datos; se explora por dirección municipal.

## Feriados, plantillas, restringidos y anuncios
- **Feriados:** no vienen cargados; hasta que se registren, los días administrativos se cuentan de lunes a viernes. La regla de días administrativos y las prórrogas deben validarse con la Ley 19.880 y las instrucciones internas (**A VALIDAR**). El sistema no distingue feriados regionales por comuna: se registran los que apliquen a la comuna.
- **Plantillas de ejemplo:** su texto es referencial (no es asesoría jurídica). Las iniciales de la cadena se calculan desde nombres y apellidos; si dos personas tienen las mismas iniciales no se desambiguan.
- **Restringidos:** la restricción oculta el contenido en el sistema (vistas, descargas, exportaciones, índices y consulta pública), pero **no cifra de forma distinta** los archivos ya almacenados (todos están cifrados en el repositorio). Quien está autorizado puede descargar y compartir el archivo fuera del sistema: la protección posterior depende de las políticas internas. La clasificación de datos personales/sensibles la decide la persona que restringe; el sistema no detecta datos personales automáticamente. Ley 19.628 / 21.719: **A VALIDAR** con el encargado de protección de datos.
- **Bitácora de accesos:** solo registra accesos hechos a través del sistema; no cubre accesos directos al servidor o a la base de datos.
- **Anuncios:** el tamaño real de un video lo limita `upload_max_filesize`/`post_max_size` de PHP (en cPanel suele ser bajo: se ajusta en *MultiPHP INI Editor*). No se transcodifican ni se generan subtítulos: la accesibilidad del video depende de quien lo publica (descripción obligatoria; subtítulos y audiodescripción **no** se generan). Las imágenes GIF/SVG no se admiten.

## Respuesta final y avisos por movimiento
- **Casilla Única:** el contrato del aviso (campos `asunto` y `mensaje`) y del envío de documentos es **A VALIDAR**; mientras la conexión esté en modo outbox, cada notificación y aviso queda pendiente hasta registrar el acuse a mano. El correo (Gmail API) es real, pero su evidencia es de **envío**, no de lectura ni de recepción.
- **Volumen en outbox:** cada movimiento genera un aviso por registrar; con mucho volumen conviene activar el modo API o desactivar los avisos de movimiento (`SGD_AVISOS_MOVIMIENTOS=false`) y dejar solo el correo.
- La respuesta final exige el expediente **cerrado**; si se reabre, se instruye de nuevo. Los movimientos anteriores a esta versión no existen (el historial muestra solo el ingreso).
- La entrega física se acredita con un acta de texto (quién recibe, fecha y hora): no hay firma manuscrita digitalizada.
- Las solicitudes sin solicitante ciudadano (comunicaciones recibidas por DocDigital) se responden por DocDigital, no por este flujo.

## Asociar expedientes, decreto de pago y egreso
- **Alcance de la búsqueda:** solo se encuentran expedientes que la persona ya puede ver (su dirección o participación). Para reutilizar un proceso de **otra dirección** hay que ser derivado(a) al expediente o pedir que se lo deriven: no existe una consulta transversal (decisión de protección de datos; ampliarla es una decisión institucional).
- **Copias:** lo incorporado es una **copia** (no se sincroniza con el original); si el original cambia después, la copia conserva la huella del momento. Los documentos del flujo (HTML) se copian como su PDF vigente.
- **Decreto de pago:** el contenido jurídico de la plantilla de ejemplo (vistos, considerandos, resolutivos) es **referencial**; el decreto y el egreso no reemplazan el sistema contable municipal: **no** se integra con el presupuesto, el devengado ni el banco (la imputación, la factura y la referencia del pago son texto informado). La disponibilidad presupuestaria no se verifica.
- **Egreso:** el valor legal de un comprobante de egreso con **firma electrónica simple** es **A VALIDAR** con Control, Jurídica y la normativa contable (Ley 19.799; normativa de la Contraloría). Si la institución exige firma avanzada, configure al firmante como «avanzada» (lo exige el sistema).
- **Firma simple:** acredita la voluntad de una persona autenticada en el sistema; no usa certificado. La reautenticación se aproxima con «ingreso en los últimos 30 minutos».
- **Montos:** solo pesos chilenos enteros; no hay multimoneda ni retenciones, impuestos o descuentos.
- **Decreto cargado:** su firma se reconoce, pero la validez de una firma externa no se verifica contra una autoridad certificadora (ver «Reconocimiento de firma electrónica en archivos»).

## Tablero de pagos y consulta de proveedores
- La consulta pública solo encuentra decretos con **RUT, número y fecha de factura** registrados; si Finanzas no los ingresó o los digitó distinto (p. ej. otro formato de número), el proveedor verá «sin información» aunque el pago exista. La fecha de la factura debe coincidir exactamente.
- El estado que ve el proveedor refleja el **sistema municipal** (decreto y egreso), no el banco: «Pagada» significa que existe un egreso firmado; el abono efectivo en su cuenta depende del banco.
- La consulta pública es un servicio sin autenticación: aunque exige tres datos y está limitada por tasa, quien conozca los tres datos de una factura ajena puede consultarla. No muestra cuentas bancarias ni datos personales; si la institución exige más garantías (clave del proveedor, ClaveÚnica de empresa), es una decisión posterior.
- El plazo de 30 días (Ley 21.131) se calcula en **días corridos desde la fecha de la factura** (no desde su recepción conforme): referencial, **A VALIDAR** con Jurídica y Finanzas. Sin fecha de factura se usa la fecha del decreto.
- El tablero no distingue facturas con pago retenido por reclamo, multas o retenciones; no hay multimoneda.
