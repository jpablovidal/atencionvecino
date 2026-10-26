# Traspaso de contexto — SGD Municipalidad de Santo Domingo

> Documento para que una sesión nueva de Claude Code (otra cuenta) retome el proyecto **sin perder nada**. Léalo completo antes de actuar. No contiene credenciales y nunca debe contenerlas.

## 1. Dónde trabajar
- **Repositorio GitHub:** `https://github.com/jpablovidal/atencionvecino` (propietario `jpablovidal`). La cuenta nueva debe tener acceso (instalar/autorizar la app de Claude en ese repositorio y reconectar GitHub en claude.ai).
- **Rama de trabajo:** `ccr-b06ae676-vzb1oo` (es la **única** rama; contiene todo el trabajo). Se desarrolla, se hace commit y push **solo ahí**. **No crear Pull Requests** salvo que la persona usuaria lo pida.
- **Carpeta:** raíz del clon del repositorio (en sesiones en la nube: `/home/user/atencionvecino`). Si la sesión no la trae clonada: `git clone https://github.com/jpablovidal/atencionvecino && cd atencionvecino && git checkout ccr-b06ae676-vzb1oo`.
- **Idioma:** español de Chile para todo (código de negocio, textos, documentación, conversación).
- **Persona usuaria:** jvidal@santodomingo.cl (administración TI de la municipalidad). Pronombres no declarados: usar «la persona usuaria».

## 2. Qué es el sistema
SGD (gestión documental y atención al vecino) de la **I. Municipalidad de Santo Domingo**, Chile. Laravel 12 / PHP 8.3, MySQL/MariaDB (pruebas también en SQLite), hosting compartido **cPanel** sin SSH root. Marco visual: *framework kit de gobierno* (gob.cl), **sin CDN**. Integraciones (todas simuladas hasta cargar credenciales reales): ClaveÚnica, FirmaGob, Gmail API, **DocDigital**, PISEE, Codificador de instituciones, Casilla Única. Bitácora con cadena HMAC, bóveda cifrada de archivos, 2FA obligatorio del administrador.
- **Producción/pruebas desplegado en:** `https://gestor.santodomingo.cl` (usuario cPanel `santod85`, código en `~/sgd`, base y usuario MySQL `santod85_gestor2`). Está como **AMBIENTE DE PRUEBAS** (`SGD_AMBIENTE=pruebas`).
- **Módulos (todos entregados):** trámites con formularios propios (campos, listados, fórmulas), solicitudes del vecino (también por terceros), expedientes (incluso de oficio), documentos con flujo de visación y firma (FirmaGob), DocDigital (despacho/recepción, directorio de entidades y usuarios), notificaciones, archivo municipal (microformas), transparencia, decretos de pago y egresos, seguimiento/autoridad/OIRS, restringidos, delegaciones de firma, **subrogancias**, roles y permisos (con roles adicionales, permisos individuales **sin techo de rol** salvo los reservados, **asignación masiva de personas**, perfil **Administrador Municipal** de clave `adm_municipal`), bandeja **Por firmar / Por visar**, manuales por perfil y recorrido guiado, accesibilidad.

## 3. Reglas permanentes (instrucciones de la persona usuaria)
1. **Trabajar de forma autónoma.** Ante ambigüedad, elegir la opción **más segura** y dejarla documentada en `docs/DECISIONES.md` (siguiente número libre: **160**).
2. **No afirmar lo no probado.** Lo no verificable va en `docs/LIMITACIONES.md`; contratos externos sin confirmar se marcan **«A VALIDAR»**. Nunca inventar contratos de API.
3. **Seguridad de credenciales:** el *Client Secret* de DocDigital que se compartió por chat **no debe aparecer jamás** en repo, docs, tests ni ZIP (la persona usuaria debe rotarlo). Las credenciales las ingresa la persona usuaria en el servidor (consulta oculta o panel de administración, cifradas) y **nunca se imprimen**. No leer la columna `password` (texto plano) de la tabla del directorio; solo `pass` (bcrypt).
4. **Cada entrega** = ZIP único actualizado + manuales + comando de actualización de cPanel + docs al día (README, manuales en `resources/manuales/`, `docs/PENTEST.md`, `docs/DECISIONES.md`, `docs/LIMITACIONES.md`, sección «Novedades» de la guía) + pruebas en **SQLite y MariaDB**.
5. **Commits** con estos pies: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>` y `Claude-Session: <url de la sesión>`. Push a la rama indicada. Un hook de la sesión exige que no queden cambios sin confirmar.
6. Sin scripts ni estilos en línea (CSP estricta): los comportamientos van en `public/assets/js/*.js` con manejadores delegados. Respetar `prefers-reduced-motion`, la clase `a11y-motion` y el alto contraste.
7. Reportar con honestidad: si una prueba falla, decirlo; si algo no se pudo verificar, decirlo.

## 4. Comandos esenciales
```bash
# Pruebas (SQLite en memoria)
php artisan test
# Pruebas en MariaDB (hay que levantarla; muere entre turnos)
fuser 3307/tcp || (nohup mariadbd --user=root --datadir=/tmp/claude-0/mdb/data --socket=/tmp/claude-0/mdb/my.sock --port=3307 --bind-address=127.0.0.1 >/tmp/claude-0/mdb.log 2>&1 &)
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=sgdtest DB_USERNAME=t DB_PASSWORD=t php artisan test
# (si el entorno es nuevo: instalar mariadb-server, crear base `sgdtest` y usuario `t`/`t`)
vendor/bin/pint            # estilo (reescribe nombres completos a imports en tests)
# Tras CADA migración nueva:
php scripts/generate-schema-sql.php > database/sql/01_schema.sql
php scripts/generate-schema-sql.php --seed > database/sql/02_seed_demo.sql
php scripts/generate-schema-sql.php --triggers > database/sql/03_triggers_opcional.sql
# Manuales en PDF (usuario + implementación)
php scripts/build-manuales-pdf.php
# Release
bash scripts/build-release.sh                  # produce sgd-release.zip y su SHA-256
python3 scripts/generar-guia.py - docs/GUIA_INSTALACION.md && python3 scripts/generar-guia.py - docs/INSTALACION_GESTOR.md
```
- **Comando de actualización que se le da a la persona usuaria (cPanel → Terminal):**
  `cd ~ && sha256sum sgd-release.zip && unzip -q -o sgd-release.zip && rm -f sgd-release.zip && bash ~/sgd/scripts/instalar-cpanel.sh`
- El SHA-256 del ZIP se informa **en el mensaje de entrega**; las guías dentro del ZIP no llevan hash fijo. Entregar con `SendUserFile` solo el ZIP.
- Si la sesión no trae la CSV del Codificador, omitir `CODIFICADOR_CSV_COMPLETO` (esa prueba se salta).

## 5. Trampas conocidas (ya costaron tiempo)
- Cada `Schema::create` lleva `$t->engine = 'InnoDB'`; los nombres de migración deben coincidir con `2025_*_create_sgd_*.php` para entrar al SQL generado; en migraciones con datos usar fechas **fijas** (el SQL generado debe ser reproducible); `INSERT` de roles con `insertOrIgnore`.
- **`usuarios.rol` es `varchar(20)`**: las claves de rol deben caber (por eso `adm_municipal`). SQLite no lo detecta; **MariaDB sí**: siempre correr ambas suites.
- `ProduccionService`: toda tabla nueva debe clasificarse en `BORRAR` o `CONSERVAR` (lo verifica una prueba).
- `DemoSeeder`: los roles personalizados de demostración usan ids explícitos desde 13 (los 12 roles del sistema ocupan 1–12); agregar un rol del sistema obliga a correr el id.
- `Usuario::ROLES` y `Permisos::OP` enumeran los roles base; agregar un rol base exige revisar `operaExpedientes`, `scopeOperadores`, `ManualRenderer::POR_ROL`, `Permisos` y `CasillaPanelTest`.
- `DelegacionService` firma «como alcalde» a todo rol base `alcalde`: por eso el Administrador Municipal tiene base propia.
- No lanzar suites mientras se editan archivos o se regenera el SQL (corridas contaminadas dan fallos falsos). No usar `pkill -f`/`pgrep -f` con patrones que calcen con la propia línea de comando (mata la shell): usar `fuser -k PUERTO/tcp`.
- Servidor local de QA: `SGD_ADMIN_2FA=false APP_ENV=local APP_DEBUG=true DB_CONNECTION=sqlite DB_DATABASE=<archivo> CLAVEUNICA_MODE=mock SESSION_DRIVER=sgd_database php -S 127.0.0.1:8131 -t public` (sin archivo router). Login de desarrollo: `/login` (vecino) y `/ingreso/funcionario`. Navegador: Playwright con `NODE_PATH=/tmp/pw/node_modules:/opt/node-tools/node_modules CHROME_PATH=/opt/pw-browsers/chromium node script.cjs` (archivos `.cjs`).
- Pint, el SQL generado y los tests de esquema (`EsquemaSqlTest`) detectan deriva: si fallan, regenerar el SQL.

## 6. Mapa del código
`app/Services/**` lógica de negocio · `app/Http/Controllers/**` · `app/Policies` (`ExpedientePolicy`: acceso por unidad, responsable, derivación, paso y **subrogancia**) · `app/Support/Permisos.php` (catálogo de permisos: `[etiqueta, grupo, bases(techo), reservado?, bases por defecto]`; `MENOR_RANGO` del Administrador Municipal) · `app/Models/Usuario.php` (`tienePermiso`, `permisosEfectivos`) · `app/Services/Admin/{PermisosUsuarioService,AsignacionMasivaService,MenuService}.php` · `app/Services/SubroganciaService.php` · `resources/views/**` · `resources/manuales/*.md` (manuales por perfil) · `public/assets/{css/sgd.css,js/*.js}` · `database/migrations`, `database/seeders/DemoSeeder.php` · `docs/` (DECISIONES 1–159, LIMITACIONES, PENTEST rondas 1–40, SEGURIDAD, NORMATIVA, guías) · `scripts/` (instalador cPanel, build, generadores) · `tests/` (~1130 pruebas).

## 7. Estado actual (al 26-10-2026)
- Todo lo pedido está **implementado, probado (SQLite 1134 / MariaDB 1131, sin fallas) y entregado**; el último ZIP tiene SHA-256 `f6f4bb9a7c8f4fcfc98e107197355998fc67009d61f490948422450e290256b0` y la persona usuaria ya instaló versiones anteriores en `gestor.santodomingo.cl`.
- **No hay tareas abiertas.** Las entregas más recientes: asignación masiva y formularios modernos; permisos sin techo, Administrador Municipal, bandeja Por firmar/Por visar; subrogancias; ZIP único con manuales de implementación (`docs/LEEME_PRIMERO.md`).
- **Pendiente de la municipalidad (no de código):** ingresar credenciales reales (ClaveÚnica, FirmaGob, Gmail, DocDigital, PISEE, Casilla Única) y validar en un piloto; **rotar el Client Secret de DocDigital**; confirmar el conjunto de permisos por defecto del Administrador Municipal; ejecutar el paso a producción cuando corresponda. Detalle en `docs/LIMITACIONES.md`.
- Ideas razonables si piden más: avisar por correo al iniciar/terminar una subrogancia; registrar «en subrogancia de» en cada comentario; pasar las solicitudes por asignar de la dirección del titular al subrogante.

## 8. Cómo retomar
1. `git fetch origin ccr-b06ae676-vzb1oo && git checkout ccr-b06ae676-vzb1oo && git log --oneline | head` y leer este archivo, `README.md` y `docs/DECISIONES.md` (últimas filas).
2. Correr `php artisan test` para confirmar la línea base.
3. Ante un pedido nuevo: crear una lista de tareas, implementar, probar (SQLite + MariaDB), actualizar docs/manuales/guía, construir el ZIP, commit + push y entregar con el SHA-256 y el comando de actualización.
