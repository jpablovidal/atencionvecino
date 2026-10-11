<?php

return [
    'nombre' => 'SGD - I. Municipalidad de Santo Domingo',

    // Permite guardar secretos (cifrados con APP_KEY) en la BD desde el mantenedor de APIs. Apagado por defecto: usar .env.
    'api_secretos_en_bd' => (bool) env('SGD_API_SECRETOS_EN_BD', false),

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
    'claveunica' => [
        'mode' => env('CLAVEUNICA_MODE', 'mock'), // mock|real  (mock prohibido en producción)
        'client_id' => env('CLAVEUNICA_CLIENT_ID'),
        'client_secret' => env('CLAVEUNICA_CLIENT_SECRET'),
        'redirect_uri' => env('CLAVEUNICA_REDIRECT_URI'),
        'authorize_url' => 'https://accounts.claveunica.gob.cl/openid/authorize/',
        'token_url' => 'https://accounts.claveunica.gob.cl/openid/token/',
        'userinfo_url' => 'https://www.claveunica.gob.cl/openid/userinfo/',
        'scope' => 'openid run name',
    ],

    // FirmaGob v2
    'firmagob' => [
        'mode' => env('FIRMAGOB_MODE', 'mock'), // mock|test|prod  (mock prohibido en producción)
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

    // Casilla Única
    'casilla' => [
        'mode' => env('CASILLA_MODE', 'outbox'), // outbox|api
        'api_url' => env('CASILLA_API_URL'),
        'api_token' => env('CASILLA_API_TOKEN'),
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
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL', 'claude-sonnet-5-5'),
        'url' => 'https://api.anthropic.com/v1/messages',
        'max_input_chars' => 6000,
        'max_output_tokens' => 800,
        'per_minute' => 6,
        'per_day' => 30,
        'timeout' => 30,
    ],

    // Código de ESTA institución en el Codificador de Instituciones y Procesos del Estado (A VALIDAR formato)
    'institucion_codigo' => env('CODIFICADOR_INSTITUCION_CODIGO'),

    // Codificador: manual (mantenedor/CSV) | api (sincronización; forma del JSON A VALIDAR)
    'codificador' => [
        'mode' => env('CODIFICADOR_MODE', 'manual'),
        'api_url' => env('CODIFICADOR_API_URL'),
        'api_token' => env('CODIFICADOR_API_TOKEN'),
        'timeout' => 20,
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
        'url_demo' => env('DOCDIGITAL_URL_DEMO'),
        'url_prod' => env('DOCDIGITAL_URL_PROD'),
        'api_token' => env('DOCDIGITAL_API_TOKEN'),
        'path_enviar' => env('DOCDIGITAL_PATH_ENVIAR'), // A VALIDAR con la guía de integración API MW V2
        'path_recibidos' => env('DOCDIGITAL_PATH_RECIBIDOS'),
        'path_acuse' => env('DOCDIGITAL_PATH_ACUSE'), // acuse de recibo (A VALIDAR)
        'unidad_recepcion' => env('DOCDIGITAL_UNIDAD_RECEPCION', 'SECMUN'), // código de unidad que recibe lo entrante
        'timeout' => 30,
        'max_bytes' => 10 * 1024 * 1024,
    ],

    // Validación pública
    'validar_rate_per_minute' => 30,
];
