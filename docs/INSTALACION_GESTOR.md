# Guía de instalación — https://gestor.santodomingo.cl (ambiente de pruebas)

Todo se hace desde la **Terminal de cPanel** (Herramientas avanzadas → Terminal). Cada bloque se pega completo y se ejecuta. Se verificó el procedimiento completo en un servidor de pruebas con MariaDB 10.11 y PHP 8.3.

> **Este despliegue queda como AMBIENTE DE PRUEBAS** (`SGD_AMBIENTE=pruebas`): integraciones simuladas (ClaveÚnica, FirmaGob, DocDigital, PISEE), correo solo a registro, ingreso con usuario y contraseña y un aviso permanente en pantalla. El acceso de pruebas **vence solo a los 30 días**. **No cargue datos reales.** Para producción vea el §9.

## Requisitos
- Cuenta cPanel con Terminal, PHP 8.3 (MultiPHP) y MySQL.
- Base de datos y usuario ya creados: **`santod85_gestor2`** (ambos con ese nombre) con **todos los privilegios** sobre la base (cPanel → Bases de datos MySQL → «Agregar usuario a la base de datos»).
- El archivo **`sgd-release.zip`** (SHA-256 `el indicado junto con la entrega`).

## 1. Subir el ZIP
cPanel → **Administrador de archivos** → carpeta personal (`/home/su_usuario`) → **Cargar** → `sgd-release.zip`. (No lo suba a `public_html`.)

## 2. Subdominio, PHP y SSL
```bash
# ---- 0. Subdominio con raíz en sgd/public + PHP 8.3 + certificado (pegue todo el bloque) ----
# Crea gestor.santodomingo.cl apuntando a ~/sgd/public (aún no existe: se llena en el paso 2).
mkdir -p ~/sgd/public
uapi SubDomain addsubdomain domain=gestor rootdomain=santodomingo.cl dir=sgd/public
# PHP 8.3 para ese dominio
uapi LangPHP php_set_vhost_versions version=ea-php83 vhost=gestor.santodomingo.cl
# Pide el certificado SSL gratuito (AutoSSL) – puede tardar unos minutos
uapi SSL start_autossl_check 2>/dev/null || echo "Si el comando anterior no existe: cPanel > SSL/TLS Status > gestor.santodomingo.cl > Run AutoSSL"
```
Si algún comando `uapi` falla (por ejemplo, el dominio principal no es `santodomingo.cl`), hágalo desde cPanel: **Dominios → Crear dominio** `gestor.santodomingo.cl` con **raíz del documento `sgd/public`**; **MultiPHP Manager → PHP 8.3**; **SSL/TLS Status → Run AutoSSL**.

## 3. Comprobar PHP y base de datos
```bash
# ---- 1. Variables y comprobaciones (pegue todo el bloque) ----
export DOMINIO="gestor.santodomingo.cl"
export DB_NAME="santod85_gestor2"
export DB_USER="santod85_gestor2"
read -rsp "Escriba la contraseña del usuario MySQL $DB_USER (no se muestra): " DB_PASS; echo
export DB_PASS
# PHP 8.3 de cPanel (si no existe, usa el php por defecto)
export PHP="$(ls /opt/cpanel/ea-php83/root/usr/bin/php 2>/dev/null || command -v php)"
echo "PHP en uso: $PHP"; "$PHP" -v | head -1
"$PHP" -r 'foreach(["pdo_mysql","mbstring","openssl","sodium","intl","curl","gd","zip","fileinfo","ctype","json","tokenizer","xml","dom"] as $e) echo str_pad($e,12), extension_loaded($e)?"OK":"FALTA <<<<<", "\n";'
# La conexión a la base de datos debe responder OK:
mysql -u "$DB_USER" -p"$DB_PASS" -h localhost "$DB_NAME" -e "SELECT 'conexion OK' AS estado, VERSION() AS version;"
```
Todas las extensiones deben decir `OK` y la última línea `conexion OK`. Si falta una extensión: cPanel → *Select PHP Version* → *Extensions*.

## 4. Descomprimir y fijar permisos
```bash
# ---- 2. Descomprimir y ordenar carpetas (el ZIP ya debe estar en su carpeta personal: ~/sgd-release.zip) ----
cd ~ || exit 1
sha256sum sgd-release.zip          # compare con el SHA-256 indicado en la guía
mkdir -p ~/sgd_storage ~/sgd_secrets
unzip -q -o sgd-release.zip && rm -f sgd-release.zip
# carpetas de trabajo de Laravel (si faltan)
mkdir -p ~/sgd/storage/framework/{cache/data,sessions,views,htmlpurifier,dompdf} ~/sgd/storage/logs ~/sgd/bootstrap/cache
# Permisos: todo privado, salvo public/ (lo sirve el servidor web) y el paso (711) para llegar a public/
chmod 711 ~/sgd
find ~/sgd -mindepth 1 -type d -not -path "$HOME/sgd/public*" -exec chmod 750 {} +
find ~/sgd -type f -not -path "$HOME/sgd/public/*" -exec chmod 640 {} +
find ~/sgd/public -type d -exec chmod 755 {} +
find ~/sgd/public -type f -exec chmod 644 {} +
chmod 750 ~/sgd_storage; chmod 700 ~/sgd_secrets
ls ~/sgd | head -20
```
Compare el SHA-256 mostrado con el de esta guía.

## 5. Crear el archivo de configuración `.env`
```bash
# ---- 3. Archivo de configuración .env (genera claves nuevas; GUARDE una copia segura de ~/sgd/.env) ----
cd ~/sgd || exit 1
APP_KEY_V="base64:$("$PHP" -r 'echo base64_encode(random_bytes(32));')"
HMAC_V="$("$PHP" -r 'echo bin2hex(random_bytes(32));')"
FILEKEY_V="$("$PHP" -r 'echo base64_encode(random_bytes(32));')"
HASTA_V="$(date -d '+30 days' +%F)"
cat > .env <<ENVFILE
APP_NAME="SGD Santo Domingo"
APP_ENV=production
APP_KEY=${APP_KEY_V}
APP_DEBUG=false
APP_URL=https://${DOMINIO}
APP_LOCALE=es
APP_TIMEZONE=America/Santiago
LOG_CHANNEL=single
LOG_LEVEL=warning
TRUSTED_PROXIES=

# AMBIENTE DE PRUEBAS (vence solo el ${HASTA_V}). Para pasar a producción: SGD_AMBIENTE=produccion y modos reales (ver guía)
SGD_AMBIENTE=pruebas
SGD_ACCESO_PRUEBAS_HASTA=${HASTA_V}

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD='${DB_PASS}'

SESSION_DRIVER=sgd_database
SESSION_LIFETIME=30
QUEUE_CONNECTION=database
CACHE_STORE=database

SGD_STORAGE_PATH=${HOME}/sgd_storage
FILE_ENCRYPTION_KEY_ID=k1
FILE_ENCRYPTION_KEYS='{"k1":"${FILEKEY_V}"}'
AUDIT_HMAC_KEY=${HMAC_V}

# Conexiones (mapa y ranking de IP): contadores por hora, sin URL ni contenido; se eliminan a los 90 días
SGD_REGISTRAR_CONEXIONES=true
SGD_CONEXIONES_RETENCION_DIAS=90

# Integraciones SIMULADAS (solo permitidas en SGD_AMBIENTE=pruebas)
CLAVEUNICA_MODE=mock
FIRMAGOB_MODE=mock
PISEE_MODE=mock
DOCDIGITAL_MODE=mock
DOCDIGITAL_UNIDAD_RECEPCION=SECMUN
CASILLA_MODE=outbox
MAIL_MAILER=log
LLM_ENABLED=false
REPOSITORIO_MODO=local
ENVFILE
chmod 600 .env
grep -E "^(APP_URL|APP_ENV|SGD_AMBIENTE|SGD_ACCESO_PRUEBAS_HASTA|DB_DATABASE|DB_USERNAME)=" .env
```
Genera claves nuevas y únicas. **Guarde una copia de `~/sgd/.env` en un lugar seguro** (sin `FILE_ENCRYPTION_KEYS` los archivos subidos no se pueden recuperar).

## 6. Crear tablas, datos de demostración y cuentas de prueba
```bash
# ---- 6. Tablas y datos de demostración + cuentas de prueba (las contraseñas se muestran UNA sola vez: anótelas) ----
cd ~/sgd || exit 1
"$PHP" artisan config:clear
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=DemoSeeder --force
"$PHP" artisan pruebas:credenciales --demo
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) AS tablas FROM information_schema.tables WHERE table_schema='$DB_NAME'; SELECT COUNT(*) AS cuentas_prueba FROM cuentas_prueba;"
```
Debe indicar «Cuentas de prueba creadas/actualizadas: 10».

## 7. Tareas programadas y verificación
```bash
# ---- 5. Tareas programadas (cola, verificación de auditoría, histórico de recursos) ----
( crontab -l 2>/dev/null | grep -v "sgd/artisan schedule:run"; echo "* * * * * $PHP $HOME/sgd/artisan schedule:run >> /dev/null 2>&1" ) | crontab -
crontab -l | grep schedule:run
```
```bash
# ---- 6. Verificación ----
cd ~/sgd && "$PHP" artisan about --only=environment | head -12
"$PHP" artisan schedule:run
curl -sS -o /dev/null -w "HTTP %{http_code} -> %{url_effective}\n" -L "https://${DOMINIO}/ingreso"
curl -sSI "https://${DOMINIO}/ingreso" | grep -iE "^(HTTP|strict-transport|content-security|set-cookie: __Host)" | cut -c1-100
```
Debe aparecer `HTTP 200` y las cabeceras de seguridad. Abra **https://gestor.santodomingo.cl** : verá el aviso amarillo «AMBIENTE DE PRUEBAS».

## 8. Usuarios y contraseñas de prueba
Ingreso: https://gestor.santodomingo.cl/ingreso → **«Ingresar con usuario y contraseña»**. Usuario = RUN completo con guion.

| Perfil | RUN (usuario) | Contraseña | Qué probar |
|---|---|---|---|
| (las contraseñas se muestran una sola vez en el paso 6) | | | |

Flujo sugerido: (1) **Vecina 1** ingresa una solicitud de «Permiso de edificación» con 2 anexos PDF → (2) **Funcionario DOM** la ve en su bandeja, se la asigna, crea un *Oficio* desde plantilla, edita con el editor (tabla, imagen) e inicia el flujo con la **Secretaria** como firmante → (3) **Secretaria** firma con cualquier OTP de 6 dígitos distinto de `000000` (la firma es simulada) → (4) el documento queda vigente; valídelo en `/validar` con su código y véalo en `/publico` → (5) **Auditoría** revisa el registro de logs y la cadena HMAC; **Administrador** revisa roles, plantillas, apariencia, APIs, folios, repositorio, recursos del servidor, componentes y el **mapa de conexiones** (Seguridad y operación → *Conexiones y mapa*: tras navegar con distintos perfiles verá su IP y los servicios a los que se conectó el sistema; las visitas locales aparecen como «Red interna / local»).
- Para probar DocDigital entrante: Administrador → *APIs e integraciones* / *Comunicaciones* (modo simulado).
- Las contraseñas se guardan solo como hash. **Cámbielas** cuando termine (§9). Tras 5 intentos fallidos la cuenta se bloquea 15 minutos.

## 9. Operación
**Cambiar contraseñas de prueba** (muestra las nuevas una sola vez):
```bash
cd ~/sgd && "$PHP" artisan pruebas:credenciales --rotar
```
**Desactivar el acceso de pruebas** (inmediato):
```bash
cd ~/sgd && "$PHP" artisan pruebas:credenciales --desactivar
```
**Crear el administrador real** (ingresará con ClaveÚnica cuando esté configurada):
```bash
cd ~/sgd && "$PHP" artisan sgd:crear-admin 12345678-5 "Nombre" "Apellido" correo@santodomingo.cl
```
**Respaldo** (base de datos + archivos + `.env`):
```bash
cd ~ && mysqldump -u "$DB_USER" -p"$DB_PASS" --single-transaction "$DB_NAME" | gzip > respaldo-bd-$(date +%F).sql.gz
tar czf respaldo-archivos-$(date +%F).tgz sgd_storage sgd/.env
```
**Actualizar a una versión nueva** (conserva `.env` y archivos): suba el nuevo ZIP a `~/` y ejecute
```bash
cd ~ && cp sgd/.env /tmp/.env.sgd && unzip -q -o sgd-release.zip && rm sgd-release.zip && cp /tmp/.env.sgd sgd/.env && rm /tmp/.env.sgd \
 && chmod 600 sgd/.env && cd sgd && "$PHP" artisan migrate --force && "$PHP" artisan config:clear && "$PHP" artisan view:clear
```
(Vuelva a aplicar los permisos del paso 4.)

**Pasar a producción real** (cuando tenga credenciales de ClaveÚnica y FirmaGob): edite `~/sgd/.env` → `SGD_AMBIENTE=produccion`, `CLAVEUNICA_MODE=real`, `FIRMAGOB_MODE=prod`, claves/URLs de cada servicio, `MAIL_MAILER=gmail_api`; **vacíe la base y vuelva a migrar sin cargar datos de demostración** (`php artisan migrate:fresh --force`), cree el administrador real y desactive las cuentas de prueba. Detalle en `README.md`.

## 10. Si algo falla
| Síntoma | Qué hacer |
|---|---|
| Página 500 | `tail -n 40 ~/sgd/storage/logs/laravel.log`. Si dice «Configuración insegura para producción: …» corrija el `.env` según el mensaje. |
| 403 / 404 en todo | La raíz del dominio no es `~/sgd/public` (cPanel → Dominios). |
| Error de permisos en `storage` | `chmod -R u+rwX ~/sgd/storage ~/sgd/bootstrap/cache` |
| «could not find driver» | Active `pdo_mysql` en *Select PHP Version* y confirme PHP 8.3. |
| Redirige a https con error de certificado | Espere AutoSSL (hasta 15 min) o ejecútelo desde *SSL/TLS Status*. |
| No ingresa con contraseña | Verifique la fecha de vencimiento (`grep ACCESO ~/sgd/.env`) y que el RUN lleve guion. Bloqueo: espere 15 min. |
| No se procesa la cola | `crontab -l` debe mostrar `schedule:run` cada minuto. |
