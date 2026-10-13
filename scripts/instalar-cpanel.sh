#!/usr/bin/env bash
# Instalador/actualizador del SGD para cPanel (una sola ejecución). Uso, desde la Terminal de cPanel:
#   cd ~ && unzip -q -o sgd-release.zip && rm -f sgd-release.zip && bash ~/sgd/scripts/instalar-cpanel.sh
# - Primera vez: crea subdominio, .env con claves nuevas, tablas, datos de demostración, cuentas de prueba (contraseñas aleatorias
#   que se muestran UNA vez), cron y verificación.
# - Si ya existe ~/sgd/.env (actualización): conserva .env y archivos, aplica migraciones y limpia cachés. No toca los datos.
# Variables opcionales: DOMINIO, DB_NAME, DB_USER, DB_PASS, DB_HOST, SUBDOMINIO, DOMINIO_RAIZ.
set -uo pipefail
export DOMINIO="${DOMINIO:-gestor.santodomingo.cl}"
export DB_NAME="${DB_NAME:-santod85_gestor2}"
export DB_USER="${DB_USER:-$DB_NAME}"
DB_HOST_V="${DB_HOST:-localhost}"
SUBDOMINIO="${SUBDOMINIO:-${DOMINIO%%.*}}"
DOMINIO_RAIZ="${DOMINIO_RAIZ:-${DOMINIO#*.}}"
cd ~ || exit 1
paso() { echo; echo "==== $* ===="; }
fallo() { echo; echo "ERROR: $*" >&2; exit 1; }

paso "1/7 PHP y extensiones"
PHP="$(ls /opt/cpanel/ea-php83/root/usr/bin/php 2>/dev/null || command -v php)"; export PHP
"$PHP" -v | head -1
"$PHP" -r 'if (PHP_VERSION_ID < 80300) { fwrite(STDERR, "Se requiere PHP 8.3 o superior\n"); exit(1);} $f=[]; foreach(["pdo_mysql","mbstring","openssl","intl","curl","gd","zip","fileinfo","ctype","json","tokenizer","xml","dom"] as $e) if(!extension_loaded($e)) $f[]=$e; if($f){fwrite(STDERR,"Faltan extensiones: ".implode(", ",$f)."\nActívelas en cPanel > Select PHP Version > Extensions\n"); exit(1);} if (!extension_loaded("sodium")) { if (!in_array("chacha20-poly1305", openssl_get_cipher_methods(), true)) { fwrite(STDERR,"Falta la extensión sodium y OpenSSL no ofrece chacha20-poly1305: pida a su proveedor ea-php83-php-sodium.\n"); exit(1);} echo "AVISO: sin extensión sodium; el cifrado de archivos usará OpenSSL ChaCha20-Poly1305 (compatible y verificado). Sin acción necesaria.\n"; } echo "Extensiones OK\n";' || fallo "Corrija PHP y vuelva a ejecutar este mismo comando."
[ -f ~/sgd/artisan ] || fallo "No se encontró ~/sgd/artisan: descomprima sgd-release.zip en su carpeta personal (cd ~ && unzip -q -o sgd-release.zip)."

ACTUALIZACION=0; [ -f ~/sgd/.env ] && ACTUALIZACION=1
if [ "$ACTUALIZACION" = 1 ]; then
  echo "Se detectó una instalación previa (~/sgd/.env): modo ACTUALIZACIÓN (se conservan .env, archivos y datos)."
  DB_PASS="$(grep -E '^DB_PASSWORD=' ~/sgd/.env | head -1 | cut -d= -f2- | sed -e "s/^'//" -e "s/'$//")"
else
  if [ -z "${DB_PASS:-}" ]; then read -rsp "Contraseña del usuario MySQL $DB_USER (no se muestra): " DB_PASS; echo; fi
fi
export DB_PASS

paso "2/7 Conexión a la base de datos $DB_NAME"
DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" DB_HOST_V="$DB_HOST_V" "$PHP" -r '
try { $p=new PDO("mysql:host=".getenv("DB_HOST_V").";dbname=".getenv("DB_NAME").";charset=utf8mb4",getenv("DB_USER"),getenv("DB_PASS"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); echo "Conexión OK (".$p->query("select version()")->fetchColumn().")\n"; }
catch (Throwable $e) { fwrite(STDERR,"No se pudo conectar: ".$e->getMessage()."\nRevise que el usuario esté agregado a la base con TODOS los privilegios (cPanel > Bases de datos MySQL).\n"); exit(1); }' || fallo "Sin conexión a la base de datos."

paso "3/7 Subdominio, PHP 8.3 y certificado"
if command -v uapi >/dev/null 2>&1; then
  mkdir -p ~/sgd/public
  uapi SubDomain addsubdomain domain="$SUBDOMINIO" rootdomain="$DOMINIO_RAIZ" dir=sgd/public 2>&1 | grep -E "status|errors|reason|message" | head -4
  uapi LangPHP php_set_vhost_versions version=ea-php83 vhost="$DOMINIO" 2>&1 | grep -E "status|errors" | head -2
  uapi SSL start_autossl_check >/dev/null 2>&1 || echo "AutoSSL: si no se emite solo, ejecútelo en cPanel > SSL/TLS Status."
  echo "Si el subdominio ya existía con otra raíz, cPanel > Dominios > $DOMINIO > Raíz del documento = sgd/public"
else
  echo "uapi no disponible: cree el subdominio $DOMINIO con raíz del documento sgd/public desde cPanel."
fi

paso "4/7 Carpetas y permisos"
mkdir -p ~/sgd_storage ~/sgd_secrets ~/sgd/storage/framework/{cache/data,sessions,views,htmlpurifier,dompdf} ~/sgd/storage/logs ~/sgd/bootstrap/cache
chmod 711 ~/sgd
find ~/sgd -mindepth 1 -type d -not -path "$HOME/sgd/public*" -exec chmod 750 {} +
find ~/sgd -type f -not -path "$HOME/sgd/public/*" -exec chmod 640 {} +
find ~/sgd/public -type d -exec chmod 755 {} +
find ~/sgd/public -type f -exec chmod 644 {} +
chmod 750 ~/sgd_storage; chmod 700 ~/sgd_secrets
chmod 750 ~/sgd/scripts/instalar-cpanel.sh 2>/dev/null || true
echo "Permisos aplicados."

cd ~/sgd || exit 1
paso "5/7 Configuración (.env)"
if [ "$ACTUALIZACION" = 0 ]; then
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
DB_HOST=${DB_HOST_V}
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
  chmod 600 .env
  echo ".env creado con claves nuevas. GUARDE una copia segura de ~/sgd/.env (sin FILE_ENCRYPTION_KEYS los archivos subidos no se recuperan)."
else
  echo ".env existente conservado."
fi
chmod 600 .env
grep -E "^(APP_URL|APP_ENV|SGD_AMBIENTE|SGD_ACCESO_PRUEBAS_HASTA|DB_DATABASE|DB_USERNAME)=" .env

paso "6/7 Base de datos"
"$PHP" artisan config:clear >/dev/null
"$PHP" artisan migrate --force || fallo "Falló la migración (revise el mensaje anterior)."
if [ "$ACTUALIZACION" = 0 ]; then
  "$PHP" artisan db:seed --class=DemoSeeder --force || fallo "Falló la carga de datos de demostración."
  echo; echo "CUENTAS DE PRUEBA (usuario = RUN, contraseña aleatoria). ANÓTELAS AHORA: no se vuelven a mostrar."
  "$PHP" artisan pruebas:credenciales --demo
else
  "$PHP" artisan view:clear >/dev/null; "$PHP" artisan cache:clear >/dev/null 2>&1 || true
  echo "Actualización aplicada. Para ver/rotar cuentas de prueba: php artisan pruebas:credenciales --rotar"
fi

paso "7/7 Tareas programadas y verificación"
( crontab -l 2>/dev/null | grep -v "sgd/artisan schedule:run"; echo "* * * * * $PHP $HOME/sgd/artisan schedule:run >> /dev/null 2>&1" ) | crontab - 2>/dev/null && crontab -l | grep schedule:run || echo "No se pudo crear el cron: agréguelo en cPanel > Cron Jobs: * * * * * $PHP $HOME/sgd/artisan schedule:run"
"$PHP" artisan about --only=environment | head -8
if command -v curl >/dev/null 2>&1; then
  curl -sS -o /dev/null -m 20 -w "HTTP %{http_code} -> %{url_effective}\n" -L "https://${DOMINIO}/ingreso" || echo "(El sitio aún no responde por https: espere unos minutos a AutoSSL y repita: curl -I https://${DOMINIO}/ingreso)"
  curl -sSI -m 20 "https://${DOMINIO}/ingreso" 2>/dev/null | grep -iE "^(HTTP|strict-transport|content-security)" | cut -c1-100
fi
echo; echo "LISTO. Abra https://${DOMINIO}  (ingreso de pruebas: https://${DOMINIO}/ingreso → «Ingresar con usuario y contraseña»)."
