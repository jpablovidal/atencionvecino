<?php

return [
    'nombre' => 'SGD - I. Municipalidad de Santo Domingo',

    // Permite guardar secretos (cifrados con APP_KEY) en la BD desde el mantenedor de APIs. Apagado por defecto: usar .env.
    // Los secretos de las integraciones se pueden ingresar desde el panel y se guardan CIFRADOS con APP_KEY (nunca se muestran). SGD_API_SECRETOS_EN_BD=0 los deja solo en .env.
    'api_secretos_en_bd' => (bool) env('SGD_API_SECRETOS_EN_BD', true),

    // Accesibilidad: canal de contacto para reportar barreras (declaración de accesibilidad)
    'accesibilidad_contacto' => env('SGD_ACCESIBILIDAD_CONTACTO', 'accesibilidad@santodomingo.cl'),

    // Sesión
    'sesion_inactividad_min' => 30,
    'sesion_absoluta_horas' => 8,

    // Almacenamiento (FUERA del docroot)
    'storage_path' => env('SGD_STORAGE_PATH', storage_path('app/sgd_storage')),
    'file_key_id' => env('FILE_ENCRYPTION_KEY_ID', 'k1'),
    // JSON {"k1":"base64(32 bytes)", "k0":"..."}; la clave activa es file_key_id; las antiguas solo descifran.
    'file_keys' => env('FILE_ENCRYPTION_KEYS', ''),
    'audit_hmac_key' => env('AUDIT_HMAC_KEY', ''),

    // Subidas
    'upload_max_bytes' => 10 * 1024 * 1024,
    'upload_max_files' => 8,

    // Segundo factor (TOTP) obligatorio para administradores del sistema. No desactivar en producción (ProductionGuard lo exige).
    'admin_2fa' => (bool) env('SGD_ADMIN_2FA', true),

    // Conexiones: contadores por hora para el mapa/ranking (sin URL ni contenido) y retención en días
    'registrar_conexiones' => (bool) env('SGD_REGISTRAR_CONEXIONES', true),
    'conexiones_retencion_dias' => (int) env('SGD_CONEXIONES_RETENCION_DIAS', 90),

    // Ambiente: produccion (defecto) | pruebas (modos simulados + acceso con contraseña, hasta la fecha indicada AAAA-MM-DD)
    'ambiente' => env('SGD_AMBIENTE', 'produccion'),
    'acceso_pruebas_hasta' => env('SGD_ACCESO_PRUEBAS_HASTA'),

    // Reconocimiento de firmas electrónicas en PDF: paquete PEM de CA de confianza (prestadores acreditados; A VALIDAR). Vacío = no se afirma confianza.
    'firma' => ['ca_bundle' => env('SGD_FIRMA_CA_BUNDLE')],

    // Repositorio de documentos: local (disco fuera del docroot) | drive (unidad compartida de Google Drive)
    'repositorio' => [
        'modo' => env('REPOSITORIO_MODO', 'local'),
        'drive_sa_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'),
        'drive_shared_drive_id' => env('GOOGLE_DRIVE_SHARED_DRIVE_ID'),
        'drive_root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID'),
        'timeout' => 30,
        'max_bytes' => 25 * 1024 * 1024,
    ],

    // ClaveÚnica (OIDC)
    // Carpeta (fuera del docroot) donde el panel guarda claves JSON subidas; por defecto «sgd_secrets» junto a la carpeta de la aplicación
    'secrets_path' => env('SGD_SECRETS_PATH'),

    // Directorio institucional de funcionarios (base «sistemas_usuarios»): quien entra con ClaveÚnica y figura en él es funcionario(a). Se configura desde el panel.
    'directorio' => [
        'enabled' => (bool) env('DIRECTORIO_ENABLED', false),
        'host' => env('DIRECTORIO_HOST'), 'port' => env('DIRECTORIO_PORT', 3306), 'database' => env('DIRECTORIO_DATABASE', 'sistemas_usuarios'),
        'username' => env('DIRECTORIO_USERNAME'), 'password' => env('DIRECTORIO_PASSWORD'), 'tabla' => env('DIRECTORIO_TABLA', 'sistemas_users'),
        'rol_defecto' => env('DIRECTORIO_ROL_DEFECTO', 'funcionario'), 'login_usuario' => (bool) env('DIRECTORIO_LOGIN_USUARIO', true), 'crear_unidades' => (bool) env('DIRECTORIO_CREAR_UNIDADES', true),
    ],

    'claveunica' => [
        'mode' => env('CLAVEUNICA_MODE', 'real'), // mock|real  (mock prohibido en producción)
        'client_id' => env('CLAVEUNICA_CLIENT_ID'),
        'client_secret' => env('CLAVEUNICA_CLIENT_SECRET'),
        'redirect_uri' => env('CLAVEUNICA_REDIRECT_URI'),
        'authorize_url' => 'https://accounts.claveunica.gob.cl/openid/authorize/',
        'token_url' => 'https://accounts.claveunica.gob.cl/openid/token/',
        'userinfo_url' => 'https://accounts.claveunica.gob.cl/openid/userinfo/', // el manual (6.5) exige que todos los endpoints sean accounts.claveunica.gob.cl
        'logout_url' => 'https://accounts.claveunica.gob.cl/api/v1/accounts/app/logout',
        // Ambiente activo y credenciales por ambiente (sandbox / qa / produccion); si las del ambiente activo faltan se usan las generales de arriba
        'ambiente' => env('CLAVEUNICA_AMBIENTE', 'produccion'),
        'sandbox_client_id' => env('CLAVEUNICA_SANDBOX_CLIENT_ID'), 'sandbox_client_secret' => env('CLAVEUNICA_SANDBOX_CLIENT_SECRET'),
        'sandbox_redirect_uri' => env('CLAVEUNICA_SANDBOX_REDIRECT_URI'), 'sandbox_logout_uri' => env('CLAVEUNICA_SANDBOX_LOGOUT_URI'),
        'qa_client_id' => env('CLAVEUNICA_QA_CLIENT_ID'), 'qa_client_secret' => env('CLAVEUNICA_QA_CLIENT_SECRET'),
        'qa_redirect_uri' => env('CLAVEUNICA_QA_REDIRECT_URI'), 'qa_logout_uri' => env('CLAVEUNICA_QA_LOGOUT_URI'),
        'produccion_client_id' => env('CLAVEUNICA_PRODUCCION_CLIENT_ID'), 'produccion_client_secret' => env('CLAVEUNICA_PRODUCCION_CLIENT_SECRET'),
        'produccion_redirect_uri' => env('CLAVEUNICA_PRODUCCION_REDIRECT_URI'), 'produccion_logout_uri' => env('CLAVEUNICA_PRODUCCION_LOGOUT_URI'),
        // Datos informativos del trámite «Solicitud de Credenciales de Integración» (Cerofilas); no se envían a ningún servicio
        'institucion' => env('CLAVEUNICA_INSTITUCION', 'I. Municipalidad de Santo Domingo'), 'sitio_web' => env('CLAVEUNICA_SITIO_WEB', 'https://gestor.santodomingo.cl/login'), 'app_nombre' => env('CLAVEUNICA_APP_NOMBRE'),
        'contacto_admin_nombre' => null, 'contacto_admin_correo' => null, 'contacto_admin_telefono' => null,
        'contacto_tec_nombre' => null, 'contacto_tec_correo' => null, 'contacto_tec_telefono' => null,
        'logout_uri' => env('CLAVEUNICA_LOGOUT_URI'), // Logout URI registrada ante ClaveÚnica (obligatoria desde el manual 6.4); por defecto la portada del sitio
        'scope' => 'openid run name',
    ],

    // FirmaGob v2
    'firmagob' => [
        'mode' => env('FIRMAGOB_MODE', 'prod'), // mock|test|prod  (mock prohibido en producción)
        // Credenciales propias de cada ambiente (reemplazan a las generales según el ambiente activo: test | prod)
        'test_api_token_key' => env('FIRMAGOB_TEST_API_TOKEN_KEY'), 'test_secret' => env('FIRMAGOB_TEST_SECRET'),
        'prod_api_token_key' => env('FIRMAGOB_PROD_API_TOKEN_KEY'), 'prod_secret' => env('FIRMAGOB_PROD_SECRET'),
        'api_token_key' => env('FIRMAGOB_API_TOKEN_KEY'),
        'secret' => env('FIRMAGOB_SECRET'), // clave HS256 del JWT
        'entity' => env('FIRMAGOB_ENTITY', 'I. Municipalidad de Santo Domingo'),
        'purpose' => env('FIRMAGOB_PURPOSE', 'Propósito General'),
        'urls' => [
            'test' => 'https://api.firma.test.digital.gob.cl/firma/v2/files/tickets',
            'prod' => 'https://api.firma.digital.gob.cl/firma/v2/files/tickets',
        ],
        'max_bytes' => 5 * 1024 * 1024,
        'timeout' => 20,
    ],

    // Funcionario que también es vecino: sesión dual (inicio con elección de espacio). false = sesiones separadas.
    'espacio_dual' => (bool) env('SGD_ESPACIO_DUAL', true),

    // Plazos de respuesta de los trámites: días administrativos = hábiles, sin sábados, domingos ni feriados registrados (A VALIDAR con la Ley 19.880)
    'plazos' => [
        'habiles' => (bool) env('SGD_PLAZOS_HABILES', true), // true = días administrativos (lunes a viernes, sin feriados); false = corridos
        'aviso_dias' => (int) env('SGD_PLAZOS_AVISO_DIAS', 3), // «por vencer»: faltan este número de días o menos
    ],

    // Casilla Única
    // Máximo de documentos por lote en la firma y visación masivas (1 a 100)
    'firma_lote_max' => (int) env('SGD_FIRMA_LOTE_MAX', 25),

    // Avisos al ciudadano por cada movimiento de su solicitud (Casilla Única + copia por correo)
    'avisos' => [
        'movimientos' => (bool) env('SGD_AVISOS_MOVIMIENTOS', true),
    ],

    'casilla' => [
        'mode' => env('CASILLA_MODE', 'outbox'), // outbox|api
        'api_url' => env('CASILLA_API_URL'),
        'api_token' => env('CASILLA_API_TOKEN'),
        'client_id' => env('CASILLA_CLIENT_ID'),
        'client_secret' => env('CASILLA_CLIENT_SECRET'),
        'token_url' => env('CASILLA_TOKEN_URL'),
        'scope' => env('CASILLA_SCOPE'),
    ],

    // Google Workspace / Gmail API (cuenta de servicio con delegación de dominio)
    'google' => [
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
        'sender' => env('GOOGLE_MAIL_SENDER', 'no-responder@santodomingo.cl'),
        'scope' => 'https://www.googleapis.com/auth/gmail.send',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'send_url' => 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
        'timeout' => 15,
        'rate_per_minute' => (int) env('GOOGLE_MAIL_RATE_PER_MINUTE', 20),
    ],

    // Asistente IA (apagado por defecto)
    'llm' => [
        'enabled' => (bool) env('LLM_ENABLED', false),
        'provider' => env('LLM_PROVIDER', 'anthropic'), // anthropic | gemini | qwen | openrouter | groq | mistral | otro (ver LlmAsistente::PROVEEDORES)
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL'), // vacío = modelo sugerido del proveedor
        'url' => 'https://api.anthropic.com/v1/messages', // solo Anthropic
        'url_otro' => env('LLM_URL_OTRO'), // solo proveedor «otro» (compatible con OpenAI, HTTPS)
        'max_input_chars' => 6000,
        'max_output_tokens' => 800,
        'per_minute' => 6,
        'per_day' => 30,
        'timeout' => 30,
    ],

    // Código de ESTA institución en el Codificador de Instituciones y Procesos del Estado (A VALIDAR formato)
    'institucion_codigo' => env('CODIFICADOR_INSTITUCION_CODIGO', 'PE-MUN-00243'), // por defecto: Municipalidad de Santo Domingo en el Codificador

    // Codificador: manual (mantenedor/CSV) | api (sincronización; forma del JSON A VALIDAR)
    'codificador' => [
        'mode' => env('CODIFICADOR_MODE', 'manual'),
        'api_url' => env('CODIFICADOR_API_URL'), // vacío = https://codificacion.digital.gob.cl/api (API pública del Codificador)
        'version' => env('CODIFICADOR_VERSION', 'v1'),
        'id_codificacion' => (int) env('CODIFICADOR_ID_CODIFICACION', 1), // id de la codificación «Instituciones» (A VALIDAR)
        'id_codificacion_procesos' => env('CODIFICADOR_ID_CODIFICACION_PROCESOS'), // opcional: codificación de procedimientos administrativos
        'api_token' => env('CODIFICADOR_API_TOKEN'),
        'timeout' => 20,
        'timeout_api' => 60,
        'max_bytes_api' => 20 * 1024 * 1024,
        'max_bytes' => 5 * 1024 * 1024,
    ],

    // PISEE (consumo de servicios del Estado mediante el nodo de interoperabilidad). disabled|mock|api
    'pisee' => [
        'mode' => env('PISEE_MODE', 'disabled'),
        'base_url' => env('PISEE_BASE_URL'), // nodo/Security Server de la municipalidad, https
        'header_cliente' => env('PISEE_HEADER_CLIENTE'), // nombre de la cabecera de identificación del consumidor (A VALIDAR)
        'valor_cliente' => env('PISEE_VALOR_CLIENTE'),
        'cert' => env('PISEE_MTLS_CERT'), // certificado cliente (PEM) fuera del docroot
        'key' => env('PISEE_MTLS_KEY'),
        'ca' => env('PISEE_CA_BUNDLE'),
        'timeout' => 20,
        'max_bytes' => 1024 * 1024,
    ],

    // DocDigital (comunicaciones oficiales del Estado). outbox|mock|demo|prod  (mock y demo prohibidos en producción)
    'docdigital' => [
        'mode' => env('DOCDIGITAL_MODE', 'outbox'),
        'url_demo' => env('DOCDIGITAL_URL_DEMO', 'https://api-demodoc.digital.gob.cl/api'), // servidor DEMO de la especificación OpenAPI v3.2.514
        'url_prod' => env('DOCDIGITAL_URL_PROD', 'https://api-doc.digital.gob.cl/api'), // servidor «Generated server url» del Swagger de producción; A VALIDAR con Gobierno Digital
        'api_token' => env('DOCDIGITAL_API_TOKEN'),
        // OAuth2: POST {url}/oauth/token con HTTP Basic (Client_Id:Client_Secret). `token_url` es opcional (por defecto {url}/oauth/token).
        'client_id' => env('DOCDIGITAL_CLIENT_ID'),
        'client_secret' => env('DOCDIGITAL_CLIENT_SECRET'),
        'token_url' => env('DOCDIGITAL_TOKEN_URL'),
        // Producción tiene otras credenciales e ids que DemoDoc (el manual pide no usar ids estáticos): si se definen, reemplazan a los generales con modo=prod
        'prod_client_id' => env('DOCDIGITAL_PROD_CLIENT_ID'),
        'prod_client_secret' => env('DOCDIGITAL_PROD_CLIENT_SECRET'),
        'prod_entidad_id' => env('DOCDIGITAL_PROD_ENTIDAD_ID'),
        'prod_tipo_documento_id' => env('DOCDIGITAL_PROD_TIPO_DOCUMENTO_ID'),
        'entidad_id' => env('DOCDIGITAL_ENTIDAD_ID'), // id de la entidad creadora; si se omite se usa la única asociada al token (GET /entidades/token)
        'tipo_documento_id' => env('DOCDIGITAL_TIPO_DOCUMENTO_ID'), // tipo por defecto (GET /tipos/documentos/); si se omite se busca por nombre
        'unidad_recepcion' => env('DOCDIGITAL_UNIDAD_RECEPCION', 'SECMUN'), // código de unidad que recibe lo entrante
        'timeout' => 30,
        'max_bytes' => 20 * 1024 * 1024, // la especificación admite hasta 20 MB en el archivo principal
        'max_bytes_anexo' => 50 * 1024 * 1024, // y hasta 50 MB por anexo
        'max_anexos' => (int) env('DOCDIGITAL_MAX_ANEXOS', 10), // anexos por comunicación (enviados y recibidos)
    ],

    // Validación pública
    'validar_rate_per_minute' => (int) env('SGD_PUBLICO_RATE_POR_MINUTO', 30),
];
