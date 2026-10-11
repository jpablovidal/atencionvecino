<?php

return [
    'nombre' => 'SGD - I. Municipalidad de Santo Domingo',

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

    // Validación pública
    'validar_rate_per_minute' => 30,
];
