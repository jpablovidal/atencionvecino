# SGD — Sistema de Gestión Documental · I. Municipalidad de Santo Domingo

Plataforma de atención al vecino y gestión de expedientes electrónicos alineada con la **Ley 21.180** de Transformación Digital del Estado, diseñada bajo la **Ley 21.663** (Marco de Ciberseguridad), **OWASP Top 10:2025** y **OWASP Top 10 for LLM Applications 2025**.

- **Vía 1 – Ciudadano:** ClaveÚnica, solicitud con anexos, folio, comprobante PDF con hash/QR, seguimiento, devoluciones con plazo, descarga de documentos notificados.
- **Vía 2 – Funcionarios:** bandeja por unidad, expediente electrónico (metadatos MGDEE/MGDDE), plantillas con TinyMCE, visación y firma electrónica avanzada (FirmaGob), correlativo por tipo/año, copia pública con certificado y validación `/validar/{codigo}`, notificaciones con evidencia.
- **Interoperabilidad:** PISEE (consulta de servicios), Codificador (instituciones y procesos) y DocDigital (comunicaciones oficiales), apagadas/seguras por defecto y con contratos **A VALIDAR**.
- **Stack:** PHP 8.3 (compatible 8.2) · Laravel 12 · MySQL 8/MariaDB 10.6+ · sin Node ni procesos permanentes en producción.

> **Estado real:** ver [`docs/LIMITACIONES.md`](docs/LIMITACIONES.md). Todo lo que depende de credenciales reales (ClaveÚnica, FirmaGob, Casilla Única, cuenta de servicio de Google) está implementado contra contratos públicos y probado con clientes simulados, pero **no** se ha probado contra los servicios reales.

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/NORMATIVA.md`](docs/NORMATIVA.md) | Resumen normativo con enlaces y puntos "A VALIDAR" |
| [`docs/SEGURIDAD.md`](docs/SEGURIDAD.md) | Matrices OWASP Top 10:2025, OWASP LLM 2025 y Ley 21.663 |
| [`docs/DECISIONES.md`](docs/DECISIONES.md) | Decisiones de diseño y supuestos |
| [`docs/LIMITACIONES.md`](docs/LIMITACIONES.md) | Lo no verificado / dependiente de credenciales |
| [`docs/MANUAL_USUARIO.md`](docs/MANUAL_USUARIO.md) | Índice de **manuales por perfil** (ciudadano, funcionario, secretario, alcalde, administrador, auditor, público): fuentes en `resources/manuales/`, publicados en `/ayuda`, PDF con `php scripts/build-manuales-pdf.php` |
| [`docs/ACCESIBILIDAD.md`](docs/ACCESIBILIDAD.md) | Elementos de accesibilidad (WCAG 2.2 AA), verificación y limitaciones; página pública `/accesibilidad` |
| [`docs/INSTALACION_GESTOR.md`](docs/INSTALACION_GESTOR.md) | **Guía de instalación en 2 pasos** (ZIP + un comando en la Terminal de cPanel, con actualización y operación) para `gestor.santodomingo.cl` |
| [`docs/PENTEST.md`](docs/PENTEST.md) | Pruebas de seguridad, QA y consistencia: método, hallazgos corregidos y matriz OWASP Top 10:2025 |
| [`docs/INCIDENTES.md`](docs/INCIDENTES.md) | Plan de respuesta a incidentes |
| [`docs/sbom.cdx.json`](docs/sbom.cdx.json) | SBOM CycloneDX |
| [`docs/capturas/`](docs/capturas) | Capturas de la verificación en navegador real |

---

## Instalación en cPanel

> **Con Terminal de cPanel (recomendado, 2 pasos):** suba `sgd-release.zip` y ejecute `cd ~ && unzip -q -o sgd-release.zip && rm -f sgd-release.zip && bash ~/sgd/scripts/instalar-cpanel.sh` (instala o actualiza: subdominio, `.env`, tablas, cuentas de prueba, cron y verificación). Detalle en `docs/INSTALACION_GESTOR.md`. Lo que sigue es la guía general **sin Terminal**.

### Ambientes
`SGD_AMBIENTE=produccion` (por defecto): solo ClaveÚnica y servicios reales; los modos simulados están prohibidos y la aplicación se niega a arrancar si faltan. `SGD_AMBIENTE=pruebas`: permite modos simulados y el ingreso con usuario/contraseña (`pruebas:credenciales`) **solo hasta `SGD_ACCESO_PRUEBAS_HASTA`** (máx. 45 días), con aviso permanente. No use datos reales en pruebas.

Todo se instala **subiendo archivos + importando SQL + editando `.env`**. No se necesita `php artisan` en el servidor.

### 0. Generar el paquete de despliegue (en su equipo, una vez por versión)

```bash
scripts/build-release.sh          # produce sgd-release.zip (+ .sha256), con vendor/ (--no-dev), sin .env ni tests
```

### 1. Estructura de carpetas (segura)

Solo `public/` queda expuesto. Todo lo demás está **fuera del docroot**:

```
/home/USUARIO/
├── sgd/                  ← contenido del ZIP (app/, vendor/, config/, storage/, .env ...)
│   └── public/           ← ÚNICO docroot del subdominio
├── sgd_storage/          ← archivos subidos, cifrados (permisos 750)
└── sgd_secrets/          ← gmail-sa.json y otros secretos (permisos 700; archivos 600)
```

### 2. Crear el subdominio y fijar el docroot

1. cPanel → **Dominios** (o "Subdominios") → **Crear un dominio/subdominio**: `sgd.santodomingo.cl`.
2. En **Raíz del documento** escriba **`sgd/public`** (queda `/home/USUARIO/sgd/public`). **No** use `public_html`.
3. Cree también las carpetas `sgd_storage` y `sgd_secrets` en `/home/USUARIO/` (Administrador de archivos → *Carpeta nueva*).

### 3. Versión de PHP y extensiones

cPanel → **Seleccionar versión de PHP** (CloudLinux) o **MultiPHP Manager**: elija **PHP 8.3** (8.2 también funciona) para el subdominio.
En **Extensiones** active: `pdo_mysql, mbstring, openssl, sodium, intl, curl, gd (o imagick), zip, fileinfo` (además de `ctype, json, tokenizer, xml, dom`).
En **Opciones** (INI) sugeridas: `memory_limit=256M`, `max_execution_time=60`, `upload_max_filesize=12M`, `post_max_size=40M`, `expose_php=Off`, `display_errors=Off`.
Después de instalar, ingrese como administrador a `/app/diagnostico`: verifica extensiones, permisos, modo mock, etc. (solo administradores).

### 4. Base de datos con privilegios mínimos

1. cPanel → **Bases de datos MySQL**: cree la BD `cpanelusr_sgd` y el usuario `cpanelusr_sgdapp` (contraseña larga y aleatoria).
2. Asigne el usuario a la BD con privilegios: `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES` durante la instalación (el SQL ya está importado vía phpMyAdmin, de modo que luego bastan `SELECT, INSERT, UPDATE, DELETE`).
3. **Bitácora append-only (defensa en profundidad).** Si su hosting permite `GRANT` por tabla (phpMyAdmin → pestaña *SQL*, o soporte del hosting), ejecute para el usuario de aplicación:

   ```sql
   REVOKE UPDATE, DELETE ON `cpanelusr_sgd`.`audit_log` FROM 'cpanelusr_sgdapp'@'localhost';
   REVOKE UPDATE, DELETE ON `cpanelusr_sgd`.`documento_versiones` FROM 'cpanelusr_sgdapp'@'localhost';
   -- (si el grant fue a nivel de BD, conceda por tabla en su lugar:)
   -- GRANT SELECT, INSERT ON `cpanelusr_sgd`.`audit_log` TO 'cpanelusr_sgdapp'@'localhost';
   ```

   La interfaz de cPanel solo asigna privilegios a nivel de base completa; si no puede hacer `GRANT` por tabla, use **triggers** (si el hosting concede `TRIGGER`): importe `database/sql/03_triggers_opcional.sql`. La aplicación además impide UPDATE/DELETE a nivel de modelo y detecta cualquier alteración con la **cadena HMAC** (`audit:verify` / `/app/seguridad`). Ver limitaciones en `docs/LIMITACIONES.md`.

### 5. Importar el esquema en phpMyAdmin

1. cPanel → **phpMyAdmin** → seleccione `cpanelusr_sgd` → pestaña **Importar**.
2. Suba `database/sql/01_schema.sql` (del ZIP) → *Continuar*. Crea todas las tablas InnoDB `utf8mb4_unicode_ci` con claves foráneas e índices.
3. *(Opcional, según §4)* importe `03_triggers_opcional.sql`.
4. **Solo desarrollo/pruebas:** `02_seed_demo.sql` (datos de demostración, usuarios ficticios). **No importar en producción.**
5. Si `01_schema.sql` supera `upload_max_filesize` de phpMyAdmin, divídalo por tablas o use el campo "Examinar" con compresión zip.

### 6. Subir el ZIP y descomprimir

1. **Administrador de archivos** → carpeta `/home/USUARIO/` → **Subir** `sgd-release.zip`.
2. Clic derecho → **Extract**. Debe quedar `/home/USUARIO/sgd/` con `app/`, `vendor/`, `public/`, etc. Borre el ZIP.
3. Verifique que el docroot del subdominio sigue siendo `sgd/public`.

### 7. Configurar `.env`

1. Copie `/home/USUARIO/sgd/.env.example` a `/home/USUARIO/sgd/.env` (Administrador de archivos → *Copy*) y edítelo.
2. Genere las claves (cualquier equipo con PHP 8, o la terminal de cPanel si la tiene):

```bash
php -r 'echo "APP_KEY=base64:".base64_encode(random_bytes(32)),"\n";'
php -r 'echo "AUDIT_HMAC_KEY=".bin2hex(random_bytes(32)),"\n";'
php -r 'echo "FILE_ENCRYPTION_KEYS=\x27".json_encode(["k1"=>base64_encode(random_bytes(32))])."\x27\n";'
```

3. Complete `APP_URL=https://sgd.santodomingo.cl`, `DB_*`, `SGD_STORAGE_PATH=/home/USUARIO/sgd_storage`, ClaveÚnica, FirmaGob, y correo (§10).
4. **Respalde las claves** (`APP_KEY`, `FILE_ENCRYPTION_KEYS`, `AUDIT_HMAC_KEY`) en un gestor de secretos: sin `FILE_ENCRYPTION_KEYS` los archivos **no se pueden recuperar**.
5. En producción la aplicación **se niega a arrancar** si: `APP_DEBUG=true`, `APP_URL` no es https, `CLAVEUNICA_MODE=mock`, `FIRMAGOB_MODE=mock`, faltan claves/secretos o falta el JSON de la cuenta de servicio.

### 8. Permisos

Administrador de archivos → *Change Permissions*:

| Ruta | Permiso |
|---|---|
| Directorios (`sgd/`, `sgd_storage/`) | `750` |
| Archivos | `640` |
| `sgd/.env`, `sgd_secrets/*` | **`600`** (directorio `sgd_secrets` `700`) |
| `sgd/storage`, `sgd/bootstrap/cache`, `sgd_storage` | escribibles por el usuario (750) |

### 9. Cron de cPanel (tareas y cola)

cPanel → **Trabajos de Cron** → cada minuto:

```
* * * * * /usr/local/bin/php /home/USUARIO/sgd/artisan schedule:run >> /dev/null 2>&1
```

(Use la ruta de PHP 8.3 de su hosting, p. ej. `/opt/cpanel/ea-php83/root/usr/bin/php`.) El *scheduler* procesa la **cola `database`** con `queue:work --stop-when-empty --max-time=45` (sin supervisor ni Redis), verifica la cadena de auditoría cada noche y depura sesiones.

### 10. Correo institucional: Google Workspace (Gmail API)

El correo sale **solo** por la Gmail API con una **cuenta de servicio con delegación de todo el dominio** que suplanta un buzón dedicado (`no-responder@santodomingo.cl`). **No** se usan contraseñas de aplicación ni "aplicaciones menos seguras", ni el SMTP de cPanel.

1. **Proyecto en Google Cloud:** https://console.cloud.google.com → *Nuevo proyecto* (p. ej. `sgd-santo-domingo`).
2. **Habilitar la Gmail API:** *APIs y servicios → Biblioteca → Gmail API → Habilitar*.
3. **Cuenta de servicio:** *IAM y administración → Cuentas de servicio → Crear* (`sgd-mailer`). Sin roles de proyecto. Entre a la cuenta → pestaña *Claves* → *Agregar clave → JSON* y descargue el archivo. Anote el **ID de cliente** (número largo, "Client ID") en *Detalles*.
4. **Delegación de todo el dominio:** https://admin.google.com → *Seguridad → Acceso y control de datos → Controles de API → Administrar la delegación de todo el dominio → Agregar*. Pegue el **Client ID** y como alcance **únicamente** `https://www.googleapis.com/auth/gmail.send`. Guardar.
5. **Buzón dedicado:** cree el usuario `no-responder@santodomingo.cl` (sin acceso de personas; 2FA/llaves para el admin; cuota de envío de Workspace aplica). El *sub* del JWT se fija a este buzón en `GOOGLE_MAIL_SENDER`; la cuenta de servicio no puede suplantar a otros por configuración de la aplicación (un único alcance y un único buzón).
6. **Subir el JSON** a `/home/USUARIO/sgd_secrets/gmail-sa.json`, permisos **600**. Nunca en `public/` ni en el repositorio.
7. En `.env`:
   ```
   MAIL_MAILER=gmail_api
   GOOGLE_SERVICE_ACCOUNT_JSON=/home/USUARIO/sgd_secrets/gmail-sa.json
   GOOGLE_MAIL_SENDER=no-responder@santodomingo.cl
   ```
8. **Verificar el envío de prueba:** inicie sesión como administrador → `/app/diagnostico` (debe mostrar "Cuenta de servicio Google presente"). Cree una notificación por **correo** a un expediente de prueba cerrado; en 1–2 minutos (cron) la notificación pasa a *enviada* con el **ID de mensaje de Gmail** como evidencia. Si falla queda *fallida* y se reintenta (5 intentos con *backoff*).
9. **SPF, DKIM y DMARC** (Consola de administración de Google + DNS del dominio):
   - **SPF** (TXT en la raíz de `santodomingo.cl`): `v=spf1 include:_spf.google.com ~all` (si ya tiene SPF, agregue el `include`; un solo registro SPF).
   - **DKIM:** *Aplicaciones → Google Workspace → Gmail → Autenticar correo electrónico* → generar clave (2048 bits) para `santodomingo.cl`, publicar el TXT `google._domainkey` y pulsar *Iniciar autenticación*.
   - **DMARC** (TXT `_dmarc.santodomingo.cl`): comience con `v=DMARC1; p=none; rua=mailto:dmarc@santodomingo.cl` y suba a `p=quarantine` y luego `p=reject` al verificar alineación.
10. **Alternativa documentada:** SMTP de Google con **OAuth2 (XOAUTH2)** — no implementada aquí; requiere un transporte adicional. **Prohibido** usar contraseñas de aplicación.
11. **Rotación de la clave de la cuenta de servicio:** crear clave nueva → reemplazar el JSON (600) → probar envío → eliminar la clave antigua en Google Cloud. Ver `docs/INCIDENTES.md`.

### 10 bis. Interoperabilidad: PISEE, Codificador y DocDigital

Las tres integraciones vienen **apagadas/seguras por defecto** (`PISEE_MODE=disabled`, `DOCDIGITAL_MODE=outbox`, `CODIFICADOR_MODE=manual`). Los contratos de API **no pudieron verificarse** (ver `docs/NORMATIVA.md` §11–13 y `docs/LIMITACIONES.md`): valídelos con la Secretaría de Gobierno Digital antes de activar.

1. **Codificador:** `/app/admin/codificador` → importe el CSV de instituciones (`codigo,nombre,tipo,codigo_padre`) y de procesos (`codigo,nombre,institucion_codigo`). Defina `CODIFICADOR_INSTITUCION_CODIGO` (código de la Municipalidad) y asocie un proceso a cada trámite. Opcional `CODIFICADOR_MODE=api` + `codificador:sincronizar`.
2. **DocDigital:** solicite a la SGD el alta (Administrador Principal e interlocutor) y el acceso a **DemoDoc**. Con las credenciales: `DOCDIGITAL_MODE=demo`, `DOCDIGITAL_URL_DEMO`, `DOCDIGITAL_API_TOKEN`, `DOCDIGITAL_PATH_ENVIAR/RECIBIDOS` (de la guía API MW V2); ajuste `HttpDocDigitalClient` a la especificación y pruebe en DemoDoc; luego `prod`. La unidad receptora de lo entrante es `DOCDIGITAL_UNIDAD_RECEPCION`. Solo se envían PDF con **FEA real** (FirmaGob). Sin API (outbox), el funcionario envía por la web de DocDigital y registra el folio como evidencia.
   **Recepción:** cada comunicación recibida crea un **expediente** (identificador `CODIGO_INSTITUCION-EXP-AAAA-NNNNNN`) en la unidad `DOCDIGITAL_UNIDAD_RECEPCION`; el funcionario acusa recibo (`DOCDIGITAL_PATH_ACUSE` o referencia manual en outbox), lo gestiona, responde con el documento que corresponda y lo despacha por DocDigital al destinatario elegido. El cron ejecuta `docdigital:sincronizar` cada 10 minutos.
3. **PISEE:** adhiera a los T&C PISEE e instale el nodo de interoperabilidad; configure `PISEE_BASE_URL` (https), `PISEE_HEADER_CLIENTE/VALOR_CLIENTE`, certificado de cliente `PISEE_MTLS_CERT/KEY` (en `sgd_secrets`, 600) y `PISEE_CA_BUNDLE`; registre los servicios autorizados en `/app/admin/pisee` y active `PISEE_MODE=api`.

### 11. AutoSSL y HTTPS

cPanel → **SSL/TLS Status** → ejecute **AutoSSL** para `sgd.santodomingo.cl`. `public/.htaccess` fuerza HTTPS (301), activa HSTS, bloquea dotfiles y extensiones `.sql/.env/.log/.bak`, desactiva `Indexes` y fija cabeceras de seguridad. Verifique con https://www.ssllabs.com y https://securityheaders.com.

### 12. Primer administrador

El esquema vacío no trae usuarios. En phpMyAdmin → SQL (reemplace datos; el RUN con DV válido):

```sql
INSERT INTO unidades (codigo, nombre, activo, created_at, updated_at) VALUES ('TI','Informática',1,NOW(),NOW());
INSERT INTO usuarios (run, nombres, apellidos, correo, cargo, rol, unidad_id, puede_firmar, activo, created_at, updated_at)
VALUES ('12345678-5','NOMBRE','APELLIDO','admin@santodomingo.cl','Administrador','admin',NULL,0,1,NOW(),NOW());
```

Ingrese en `https://sgd.santodomingo.cl/ingreso` → *funcionario* (ClaveÚnica). Desde `/app/admin/usuarios` cree alcalde, secretario, funcionarios, auditor; en `/app/admin/tipos` y `/app/admin/plantillas` los tipos de documento (cargar los tipos base: decreto alcaldicio, decreto SIAPER, oficio, ordenanza, reglamento, instructivo) y en `/app/tramites` (administrador, o roles con el permiso *Trámites y servicios*) los trámites con su **código CPAT real** y direcciones responsables. Para que cada dirección administre los suyos, cree un rol personalizado con *Trámites y servicios* y asígnelo a su director(a) (la persona debe tener unidad asignada). El alcalde crea las delegaciones de firma en `/app/delegaciones`.

### 13. Lista de verificación previa a producción

- [ ] `/app/diagnostico` sin elementos "REVISAR".
- [ ] `APP_DEBUG=false`, `APP_URL` https, sin modo mock (la app no arranca si no).
- [ ] ClaveÚnica real configurada y URL de retorno registrada (`/claveunica/callback`).
- [ ] FirmaGob **probado en ambiente `test`** antes de `prod`; contrato validado (ver LIMITACIONES).
- [ ] Cuenta de servicio Google con un solo alcance; envío de prueba exitoso; SPF/DKIM/DMARC publicados.
- [ ] Permisos 750/640/600; `.env` y `sgd_secrets` fuera del docroot; `sgd_storage` fuera del docroot.
- [ ] `open_basedir` configurado a `/home/USUARIO/`, `expose_php=Off`, `.user.ini` aplicado (§Hardening).
- [ ] Respaldo de BD y de `sgd_storage` probado **con restauración real**; claves respaldadas por separado.
- [ ] `audit:verify` (panel `/app/seguridad`) = cadena íntegra.
- [ ] GRANT sin UPDATE/DELETE en `audit_log` **o** triggers (o riesgo aceptado documentado).
- [ ] Cron de cPanel activo; cola procesándose.
- [ ] Protección de `/phpmyadmin` por IP/2FA; WAF/CDN opcional.
- [ ] Plan de incidentes (`docs/INCIDENTES.md`) revisado con el encargado de ciberseguridad.

### Hardening cPanel

- **Permisos:** 750 directorios / 640 archivos / 600 `.env` y secretos.
- **`.user.ini`** (en `sgd/public/`, para PHP-FPM/CloudLinux):
  ```ini
  open_basedir = /home/USUARIO/sgd:/home/USUARIO/sgd_storage:/home/USUARIO/sgd_secrets:/tmp
  expose_php = Off
  display_errors = Off
  log_errors = On
  session.use_strict_mode = 1
  allow_url_fopen = Off
  ```
  (`allow_url_include=Off` por defecto.) Si la Gmail API/ClaveÚnica fallan por `allow_url_fopen`, recuerde que la app usa cURL, no streams.
- **`disable_functions` recomendadas (solo PHP web/FPM):** `exec,passthru,shell_exec,system,popen,parse_ini_file,show_source`. **No deshabilite `proc_open` para el PHP de la línea de comandos (cron)**: `schedule:run` lanza `queue:work` como subproceso. En cPanel el PHP CLI del cron usa un `php.ini` distinto del de PHP-FPM; si ambos comparten ajuste, programe en su lugar dos entradas de cron: `schedule:run` y `queue:work --stop-when-empty --max-time=45` (y retire esa línea de `routes/console.php`).
- **ModSecurity (OWASP CRS):** compatible. Falsos positivos típicos: (a) TinyMCE envía HTML en campos `html`/`contenido_html` → excluir reglas 941xxx/942xxx para esos parámetros en las rutas `/app/documentos/*` y `/app/admin/plantillas*`; (b) subidas multipart de PDF → reglas 920420/200002 solo si el tamaño supera `SecRequestBodyLimit`. Ejemplo (WHM → ModSecurity Tools → *Rules List*): `SecRuleUpdateTargetById 941100 "!ARGS:html"` y análogo para `ARGS:contenido_html`.
- **phpMyAdmin:** restrinja por IP en cPanel (*IP Blocker*/`.htaccess` de `/phpmyadmin`) y use 2FA de cPanel.
- **WAF/CDN opcional** (Cloudflare u otro): si se usa, configure `TRUSTED_PROXIES` y mantenga HTTPS de extremo a extremo.

### Respaldo y restauración

| Qué | Cómo | Frecuencia |
|---|---|---|
| Base de datos | cPanel → **Respaldo** → *Descargar copia de base de datos*, o phpMyAdmin → *Exportar* (SQL, "Rápido"). | Diaria; retención 30 días |
| Archivos (`sgd_storage`) | cPanel → **Respaldo** (home) o ZIP del directorio. **Cifrar el respaldo** (p. ej. `gpg -c`, 7-Zip AES-256) y guardarlo fuera del hosting. Los archivos ya están cifrados en reposo con `FILE_ENCRYPTION_KEYS`, pero **respalde esas claves por separado**. | Diaria |
| `.env` y `sgd_secrets` | Copia cifrada en gestor de secretos. | En cada cambio |

**Restauración:** (1) restaurar BD (phpMyAdmin → Importar el SQL, o cPanel *Restaurar*); (2) restaurar `sgd_storage` (permisos 750); (3) restaurar `.env`/secretos (600); (4) `audit:verify` desde `/app/seguridad` para comprobar la cadena; (5) abrir un expediente con documentos y comprobar que el PDF se descifra (verifica clave + integridad). **Pruebe la restauración al menos trimestralmente.**

## Administración del sistema (módulos del menú)

Todos requieren el rol **administrador** (o el permiso específico) y quedan en la bitácora de auditoría. Detalle operativo en `resources/manuales/administrador.md`.

| Módulo | Ruta | Qué permite |
|---|---|---|
| Apariencia | `/app/admin/apariencia` | Diseño tipo intranet (barra superior oscura, menú lateral, ingreso centrado) sobre el Framework kit de Gobierno. Logo (PNG/JPG/WebP re-codificado; sin SVG; por defecto el escudo municipal), colores con **validación de contraste WCAG ≥ 4,5:1**, encabezado y pie (textos y enlaces https/internos). |
| Barra de menú | `/app/admin/menu` | Renombrar, reordenar u ocultar ítems del sistema (los protegidos no se ocultan) y agregar enlaces propios por área/rol. El menú **no concede ni quita permisos**. |
| APIs e integraciones | `/app/admin/api` | Ver estado, editar URL/modo, probar conexión y volver a `.env` para ClaveÚnica, FirmaGob, casilla, Gmail, DocDigital, PISEE, Codificador, LLM y repositorio. Los secretos **no se muestran**; solo se guardan en BD si `SGD_API_SECRETOS_EN_BD=1`. |
| Folios | `/app/admin/folios` | Formato (`{PREFIJO} {TIPO} {N} {AAAA} {AA}`), dígitos, reinicio anual o continuo y número inicial por tipo de documento. |
| Firmas | `/app/admin/firmas` | Estilos (colores, tamaño, texto) y posiciones de las firmas en la copia pública; vista previa en PDF. |
| Repositorio | `/app/admin/repositorio` | Local o **unidad compartida de Google Drive**: probar, migrar archivos existentes y verificar integridad. |
| Roles y permisos | `/app/admin/roles` | Roles personalizados con permisos acotados por su rol base. |
| **Trámites y servicios** | `/app/tramites` | Cada **dirección municipal** crea, edita, publica y deshabilita sus trámites (descripción, instrucciones, plazo y **documentos requeridos**). Borrador → publicado ↔ deshabilitado; el ciudadano lo solicita adjuntando todo y se genera el expediente. **Catálogo público** `/tramites` (sin sesión, buscador, filtro por dirección y ficha con requisitos; solo publicados). Permisos `tramites.gestionar` / `tramites.publicar` (solo administrador por defecto; se entregan con roles personalizados), alcance limitado a la dirección propia, versiones inmutables y auditoría. |
| **Casilla Única (panel)** | `/app/casilla` | Notificador de la ciudadanía: estado de la conexión, notificaciones pendientes/fallidas de su unidad, registro de evidencia y reintentos (permiso `casilla.gestionar`). Contrato de API A VALIDAR; modo outbox por defecto. |
| **Portal del vecino** | `/`, `/tramites` | Portal estilo trámites en línea: buscador con sugerencias, trámites más solicitados, exploración por dirección, catálogo con filtros y orden, ficha con requisitos y «Mis solicitudes». |
| **Feriados** | `/app/feriados` | Administrador de feriados: los días registrados no cuentan como días administrativos en los plazos de los trámites y servicios; simulador y carga masiva. |
| **Decretos de pago** | `/app/pagos` | Expediente de pago + proceso de compra, decreto de pago (generado con plantilla o cargado), egreso de Tesorería con firma simple o avanzada; correlativo y firmantes configurables (`/app/pagos/configuracion`). |
| **Tablero de pagos** | `/app/pagos/tablero` | Tablero de Tesorería: decretos por egresar, facturas con más de 30 días, egresos en firma y del mes, por medio de pago; exportación CSV. |
| **Pago a proveedores** | `/pagos-proveedores` | Consulta pública: el proveedor ingresa RUT de su empresa, número y fecha de la factura y ve su situación de pago (sin datos bancarios). |
| **Asociar expedientes y microformas** | `/app/expedientes/{id}/asociar` | Buscar otros expedientes (que usted puede ver) y microformas, asociarlos con su motivo e incorporar copias de su contenido, con origen y huella. |
| **Respuestas finales** | `/app/respuestas` | Bandeja de **Oficina de Partes**: respuestas finales instruidas por quienes gestionaron los expedientes (qué responder y con qué documentos); despacho por Casilla Única con copia al correo, o entrega física con acta. |
| **Anuncios** | `/app/anuncios` | Anuncios con título, descripción, imagen o video que se muestran en un modal al cargar el sitio (vigencia y audiencia). |
| **Accesos a restringidos** | `/app/restringidos` | Quién vio o intentó ver expedientes, documentos o archivos restringidos (protección de datos personales). |
| **Transparencia** | `/app/transparencia` | Rol **Transparencia activa y pasiva**: consultas de acceso a la información (Ley 20.285), expedientes, prórroga, requerimientos a direcciones, derivación a otro órgano, calendario de transparencia activa, feriados y plazos, y reportes (CSV sin datos personales). |
| **Seguimiento de solicitudes** | `/app/seguimiento` | Rol **Autoridad** (solo lectura): consulta por RUN, fechas, dirección, etapa o trámite; etapa, estado, quién la tiene y si terminó. |
| **Panel OIRS** | `/app/oirs` | Rol **OIRS** (solo lectura): solicitudes, estado, responsables, plazos vencidos y por vencer. |
| **Tablero por rol** | `/app` | El inicio de cada persona muestra su tablero según su rol (indicadores y gráficos de barras accesibles). |
| **Inicio dual** | `/espacio` | Quien es funcionario(a) y vecino(a) elige su espacio al ingresar y cambia desde el menú; no puede tramitar como funcionario lo que pidió como vecino. `SGD_ESPACIO_DUAL=false` lo desactiva. |
| **DocDigital (panel)** | `/app/docdigital` | Sección para funcionarios: estado de la conexión, indicadores, comunicaciones recibidas y enviadas, **probar conexión**, **sincronizar ahora** y **reintentar envíos fallidos**. Autenticación OAuth2 «client credentials» o token fijo; credenciales en Administración → APIs o `.env` (nunca en el repositorio). Contrato de la API A VALIDAR. |
| **Archivo municipal** | `/app/archivo` | Gestión de **microformas**: registro (soporte, serie, extremos, ubicación), archivos maestros cifrados con SHA-256, índice de documentos, envío a revisión, **certificación** con acta (separación de funciones), verificación de integridad, constancia PDF, **disposición de originales con doble control** y cadena de custodia. Permisos `archivo.*` (opcionales; el administrador no los admite). No reemplaza la certificación legal (ver `docs/NORMATIVA.md` §7, A VALIDAR). |
| Usuarios | `/app/admin/usuarios` | Alta/edición con roles dinámicos, búsqueda y resguardos (no desactivarse; siempre un administrador activo). |
| Plantillas y formatos | `/app/admin/plantillas` | Editor TinyMCE completo (tablas, imágenes, colores, tipografías, saltos de página, variables), visibilidad por unidad (toda la municipalidad o direcciones como Salud/Educación) e historial de versiones. Catálogo para todo el personal en `/app/formatos`. |
| Conexiones y mapa | `/app/sistema/conexiones` | **Mapa del mundo** con el origen de las conexiones al sistema (y el destino de las que hace hacia ClaveÚnica, FirmaGob, Google, etc.), **ranking de las IP con más conexiones**, errores por IP (posible escaneo), evolución y tipo de uso. Solo contadores por hora: no guarda URL ni contenido; retención 90 días. |
| Recursos del servidor | `/app/sistema/recursos` | Dashboard de CPU, memoria (RAM), disco, memoria PHP/OPcache, base de datos, cola y sesiones, con estado en texto (Normal/Atención/Crítico) e histórico de 24 h, 7 y 30 días (muestra cada 5 min con `recursos:muestrear`). Respeta los límites del plan de hosting (cgroup) cuando los informa. |
| Componentes y librerías | `/app/sistema/componentes` | Versiones de plataforma, de cada librería PHP (Composer) y de cada activo front-end (con SHA-256), verificación de **recursos externos/CDN** y descarga del inventario en JSON. |
| Registro de logs | `/app/logs` | Filtros (fecha, acción, actor, entidad, IP), exportación CSV auditada y cola redactada del log de aplicación. |
| Usuarios conectados | `/app/sesiones` | Sesiones activas (RUN enmascarado) y cierre forzado. |

### Firmas electrónicas en archivos cargados

Todo PDF que se carga (anexos del ciudadano, archivos del expediente, comunicaciones de DocDigital) se **analiza**: se reconoce si trae firma electrónica (PAdES/PKCS#7), quién firmó, con qué certificado y emisor, cuándo, si el contenido **coincide** con lo firmado (verificación criptográfica), si se **agregó contenido** después de firmar y, opcionalmente, si la cadena del certificado es de **confianza**. El archivo original **no se modifica** (la firma sigue válida) y el resultado se ve junto a cada archivo («Firmado electrónicamente», «Firma inválida o documento alterado», «Sin firma electrónica»…). Para afirmar confianza del emisor configure `SGD_FIRMA_CA_BUNDLE` con un PEM de las CA de prestadores acreditados (lista **A VALIDAR**); sin él se verifica la integridad pero no la confianza. No revisa revocación (CRL/OCSP). Los archivos anteriores se analizan al abrirse o con `php artisan archivos:analizar-firmas` (hay una tarea horaria).

### Repositorio en una unidad compartida de Google Drive

1. En Google Workspace cree una **Unidad compartida** (p. ej. «SGD Santo Domingo») y anote su ID (la URL `drive.google.com/drive/folders/<ID>`).
2. En Google Cloud: cree una **cuenta de servicio**, habilite la **Google Drive API** y descargue su clave JSON. Guárdela **fuera del docroot** (`/home/USUARIO/sgd_secrets/drive-sa.json`, permisos 600).
3. Agregue el correo de la cuenta de servicio como **miembro de la unidad compartida** con rol *Gestor de contenido* (no hace falta delegación de dominio ni compartir carpetas individuales).
4. En `.env`: `REPOSITORIO_MODO=drive`, `GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=/ruta/drive-sa.json`, `GOOGLE_DRIVE_SHARED_DRIVE_ID=<ID>` (y opcional `GOOGLE_DRIVE_ROOT_FOLDER_ID`).
5. En **Administración → Repositorio** pulse *Probar conexión* (crea, lee y borra un archivo de prueba). Luego la acción de migración para mover los archivos existentes (cola en segundo plano) y *Verificar integridad* (hay una verificación diaria programada).
6. Los archivos se **cifran antes de salir del servidor**: ni Google ni quien administre la unidad pueden leer los documentos sin la clave de `sgd_secrets`. **Respalde esas claves**: sin ellas los archivos en Drive son irrecuperables. Si Drive falla, las **escrituras fallan cerradas** (no se guarda en otro lugar sin avisar).

Prueba en la unidad compartida real: **pendiente** (ver `docs/LIMITACIONES.md`); las pruebas automáticas usan un Drive simulado.

---

## Geolocalización y mapa (sin servicios externos)

El mapa usa una base **local** de país por IP (`resources/geoip/ipv4.bin` / `ipv6.bin`, DB-IP «IP to Country Lite», **CC BY 4.0**) y trazos del mundo de Natural Earth (dominio público). No se consulta ningún servicio externo. Para actualizarla (mensual, opcional) descargue los CSV de https://db-ip.com/db/download/ip-to-country-lite y el GeoJSON de países de Natural Earth y ejecute `php scripts/build-geoip.php ipv4.csv ipv6.csv paises.geojson`. Las IP son datos personales: el panel es solo para administración/auditoría y los contadores se depuran a los `SGD_CONEXIONES_RETENCION_DIAS` (90) días con `conexiones:depurar` (tarea diaria). Detrás de un proxy/CDN configure `TRUSTED_PROXIES` para ver la IP real del visitante.

## Desarrollo local

```bash
composer install
cp .env.example .env   # y ajustar: APP_ENV=local, DB sqlite, CLAVEUNICA_MODE=mock, FIRMAGOB_MODE=mock (SOLO desarrollo)
php artisan key:generate && php artisan migrate --seed
php artisan serve
vendor/bin/phpunit            # 670 pruebas
vendor/bin/pint --test        # estilo
composer audit                # dependencias
```

- Regenerar SQL tras cambiar migraciones: `php scripts/generate-schema-sql.php > database/sql/01_schema.sql` (y `--seed`, `--triggers`). Una prueba falla si el SQL deriva de las migraciones.
- Análisis estático: `composer require --dev larastan/larastan` y descomente `includes` en `phpstan.neon`; `vendor/bin/phpstan analyse`.
- Regenerar SBOM: `php scripts/generate-sbom.php > docs/sbom.cdx.json`.
- Con `FIRMAGOB_MODE=mock` la firma es **simulada** (OTP `000000` provoca fallo, otro OTP de 6 dígitos "firma") y el documento lleva el sello **"FIRMA SIMULADA – sin validez legal"**. En ClaveÚnica `mock` se muestra un formulario de login simulado en `/dev/login` (la ruta no existe en producción).
