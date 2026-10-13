# Guía de instalación — https://gestor.santodomingo.cl

Instalación en **2 pasos**: (1) subir el ZIP con el Administrador de archivos y (2) pegar **un solo comando** en la Terminal de cPanel. El comando crea el subdominio, la configuración con claves nuevas, las tablas, los datos de demostración, las cuentas de prueba, el cron y verifica el sitio. Se probó de punta a punta sobre MariaDB 10.11 (instalación nueva, actualización sobre la misma base y flujos completos en navegador).

> Esta instalación queda como **AMBIENTE DE PRUEBAS** (`SGD_AMBIENTE=pruebas`): integraciones simuladas (ClaveÚnica, FirmaGob, DocDigital, PISEE), correo solo a registro y ingreso con usuario y contraseña (vence a los 30 días). Para operar con integraciones reales vea «Pasar a producción».

## Antes de empezar (ya lo tiene hecho)
- Base de datos y usuario MySQL **`santod85_gestor2`** (ambos con ese nombre) con **todos los privilegios** sobre la base.
- PHP 8.3 disponible en cPanel (el comando lo comprueba y se detiene con un mensaje claro si falta algo).
- Archivo **`sgd-release.zip`** (SHA-256 el indicado junto con la entrega).

## Paso 1 — Subir el ZIP (único paso manual)
cPanel → **Administrador de archivos** → carpeta personal (`/home/su_usuario`, **no** `public_html`) → **Cargar** → `sgd-release.zip`.

## Paso 2 — Instalar (un solo comando)
cPanel → **Terminal**. Pegue y presione Enter; cuando lo pida, escriba la **contraseña del usuario MySQL** `santod85_gestor2` (no se ve al escribir):
```bash
cd ~ && sha256sum sgd-release.zip && unzip -q -o sgd-release.zip && rm -f sgd-release.zip && bash ~/sgd/scripts/instalar-cpanel.sh
```
(Compare el SHA-256 que imprime con el de esta guía.) El instalador hace, en orden: comprueba PHP y extensiones → comprueba la conexión a la base → crea el subdominio `gestor.santodomingo.cl` con raíz `sgd/public`, PHP 8.3 y solicita el certificado AutoSSL → carpetas y permisos → `.env` con claves únicas → tablas y datos de demostración → **muestra una tabla con las 12 cuentas de prueba y sus contraseñas aleatorias (se muestran una sola vez: anótelas)** → cron → verificación.

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
| Director de Obras | `10000008-3` | Funcionario con firma |
| **Directora DIDECO** | `10000009-1` | Crea, publica y deshabilita trámites de su dirección |
| **Redactor DOM** | `10000010-5` | Crea borradores de trámites; no publica |
| Vecina/o 1 y 2 | `20000001-3` / `20000002-1` | Catálogo público, solicitar trámites con adjuntos |

Flujo sugerido: (1) como **Directora DIDECO**: *Trámites y servicios* → *Nuevo trámite* → guardar borrador → *Vista previa* → *Publicar*. (2) Sin iniciar sesión: **/tramites** muestra el catálogo con buscador y filtro; abra el trámite y presione *Realizar este trámite en línea* → ingrese como **Vecina 1** y volverá directo al formulario; adjunte los documentos y envíe (recibe folio y comprobante). (3) **Funcionario DIDECO** la ve en su bandeja y se la asigna (se crea el expediente); redacta un oficio con el editor, la **Secretaria** lo firma (FirmaGob simulado, OTP de 6 dígitos) y se valida públicamente con el código SDD. (4) **Auditoría** revisa la bitácora y su cadena HMAC.

## Actualizar a una versión nueva (mismo procedimiento)
Suba el nuevo ZIP a `~/` y pegue **el mismo comando del Paso 2**. Si detecta `~/sgd/.env`, entra en modo **actualización**: conserva `.env`, archivos y datos, aplica solo las migraciones nuevas y limpia cachés (no pide contraseña ni vuelve a cargar datos de demostración).

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
