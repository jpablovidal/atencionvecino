<?php

namespace App\Services\Admin;

/**
 * Catálogo de integraciones configurables desde el mantenedor de APIs.
 * Campo: [etiqueta, tipo, ruta de config, ['secreto'=>bool,'opciones'=>[...],'ayuda'=>'','privada'=>bool,'requerido'=>bool]]
 * Tipos: text | url (https) | select | number | bool | path (ruta absoluta de archivo, fuera del docroot).
 */
class ApiCatalogo
{
    /** @return array<string,array{etiqueta:string,descripcion:string,campos:array<string,array{0:string,1:string,2:string,3?:array<string,mixed>}>}> */
    public static function servicios(): array
    {
        return [
            'repositorio' => ['etiqueta' => 'Repositorio de documentos (Google Drive)', 'descripcion' => 'Dónde se guardan los archivos cifrados. Se administra en la sección Repositorio.', 'campos' => [
                'modo' => ['Modo de almacenamiento', 'select', 'sgd.repositorio.modo', ['opciones' => ['local' => 'Disco del servidor (fuera del docroot)', 'drive' => 'Unidad compartida de Google Drive']]],
                'drive_sa_json' => ['Archivo JSON de la cuenta de servicio de Drive', 'path', 'sgd.repositorio.drive_sa_json', ['subida' => 'drive', 'ayuda' => 'Ruta absoluta fuera del docroot, permisos 600 (p. ej. /home/USUARIO/sgd_secrets/drive-sa.json). La clave NO se pega en el sistema.']],
                'drive_shared_drive_id' => ['ID de la unidad compartida', 'text', 'sgd.repositorio.drive_shared_drive_id', ['id_drive' => true, 'ayuda' => 'Se ve en la URL de la unidad compartida: drive.google.com/drive/folders/<ID>.']],
                'drive_root_folder_id' => ['ID de la carpeta raíz del repositorio', 'text', 'sgd.repositorio.drive_root_folder_id', ['id_drive' => true, 'ayuda' => 'Carpeta dentro de la unidad compartida donde se crearán año/mes.']],
            ]],
            'claveunica' => ['etiqueta' => 'ClaveÚnica (OIDC)', 'descripcion' => 'Autenticación de ciudadanos y funcionarios. Credenciales por ambiente (sandbox, QA y producción).', 'campos' => [
                'mode' => ['Modo', 'select', 'sgd.claveunica.mode', ['opciones' => ['real' => 'Real (ClaveÚnica)', 'mock' => 'Simulado (SOLO desarrollo)']]],
                'ambiente' => ['Ambiente activo', 'select', 'sgd.claveunica.ambiente', ['opciones' => ['sandbox' => 'Sandbox / Testing', 'qa' => 'QA', 'produccion' => 'Producción'], 'ayuda' => 'Sandbox y QA solo aceptan los RUN de prueba del manual (44.444.444-4, 55.555.555-5, 88.888.888-8, 99.999.999-9, clave «testing»). Producción queda bloqueada por ClaveÚnica hasta aprobar la certificación.']],
                'institucion' => ['Institución', 'text', 'sgd.claveunica.institucion'],
                'app_nombre' => ['Nombre de la aplicación (aparece en el login de ClaveÚnica)', 'text', 'sgd.claveunica.app_nombre'],
                'contacto_admin_nombre' => ['Contacto administrativo: nombre completo', 'text', 'sgd.claveunica.contacto_admin_nombre'],
                'contacto_admin_correo' => ['Contacto administrativo: correo institucional (nominativo, no genérico)', 'text', 'sgd.claveunica.contacto_admin_correo'],
                'contacto_admin_telefono' => ['Contacto administrativo: teléfono (+56XXXXXXXXX)', 'text', 'sgd.claveunica.contacto_admin_telefono'],
                'contacto_tec_nombre' => ['Contacto técnico: nombre completo', 'text', 'sgd.claveunica.contacto_tec_nombre'],
                'contacto_tec_correo' => ['Contacto técnico: correo (institucional o del proveedor)', 'text', 'sgd.claveunica.contacto_tec_correo'],
                'contacto_tec_telefono' => ['Contacto técnico: teléfono (+56XXXXXXXXX)', 'text', 'sgd.claveunica.contacto_tec_telefono'],
                'sandbox_client_id' => ['client_id', 'text', 'sgd.claveunica.sandbox_client_id'],
                'sandbox_client_secret' => ['client_secret', 'text', 'sgd.claveunica.sandbox_client_secret', ['secreto' => true]],
                'sandbox_redirect_uri' => ['Redirect URI (callback)', 'url', 'sgd.claveunica.sandbox_redirect_uri', ['privada' => true, 'ayuda' => 'Solo esquema + autoridad + path, sin query ni IP. Ej.: https://gestor.santodomingo.cl/claveunica/callback']],
                'sandbox_logout_uri' => ['Logout URI', 'url', 'sgd.claveunica.sandbox_logout_uri', ['privada' => true]],
                'qa_client_id' => ['client_id', 'text', 'sgd.claveunica.qa_client_id'],
                'qa_client_secret' => ['client_secret', 'text', 'sgd.claveunica.qa_client_secret', ['secreto' => true]],
                'qa_redirect_uri' => ['Redirect URI (callback)', 'url', 'sgd.claveunica.qa_redirect_uri', ['privada' => true]],
                'qa_logout_uri' => ['Logout URI', 'url', 'sgd.claveunica.qa_logout_uri', ['privada' => true]],
                'produccion_client_id' => ['client_id', 'text', 'sgd.claveunica.produccion_client_id'],
                'produccion_client_secret' => ['client_secret', 'text', 'sgd.claveunica.produccion_client_secret', ['secreto' => true]],
                'produccion_redirect_uri' => ['Redirect URI (callback)', 'url', 'sgd.claveunica.produccion_redirect_uri', ['privada' => true, 'ayuda' => 'Debe coincidir exactamente con la registrada ante ClaveÚnica.']],
                'produccion_logout_uri' => ['Logout URI', 'url', 'sgd.claveunica.produccion_logout_uri', ['privada' => true]],
                'client_id' => ['client_id (general)', 'text', 'sgd.claveunica.client_id'],
                'client_secret' => ['client_secret (general)', 'text', 'sgd.claveunica.client_secret', ['secreto' => true]],
                'redirect_uri' => ['Redirect URI (general)', 'url', 'sgd.claveunica.redirect_uri', ['privada' => true]],
                'logout_uri' => ['Logout URI (general)', 'url', 'sgd.claveunica.logout_uri', ['privada' => true]],
            ]],
            'firmagob' => ['etiqueta' => 'FirmaGob (firma electrónica avanzada)', 'descripcion' => 'API v2 de firma. Pruebe primero en el ambiente de pruebas; cada ambiente tiene sus propias credenciales.', 'campos' => [
                'mode' => ['Ambiente', 'select', 'sgd.firmagob.mode', ['opciones' => ['test' => 'Pruebas (ambiente de certificación; firmas sin validez)', 'prod' => 'Producción (certificado real del titular)', 'mock' => 'Simulado (SOLO desarrollo)']]],
                'test_api_token_key' => ['api_token_key de PRUEBAS', 'text', 'sgd.firmagob.test_api_token_key', ['secreto' => true]],
                'test_secret' => ['Secret de PRUEBAS (clave HS256 del JWT)', 'text', 'sgd.firmagob.test_secret', ['secreto' => true]],
                'prod_api_token_key' => ['api_token_key de PRODUCCIÓN', 'text', 'sgd.firmagob.prod_api_token_key', ['secreto' => true]],
                'prod_secret' => ['Secret de PRODUCCIÓN (clave HS256 del JWT)', 'text', 'sgd.firmagob.prod_secret', ['secreto' => true]],
                'entity' => ['Entidad', 'text', 'sgd.firmagob.entity', ['ayuda' => 'Tal como aparece en la RA de FirmaGob (en su registro: «I. Municipalidad de Santo Domingo»).']],
                'purpose' => ['Propósito', 'text', 'sgd.firmagob.purpose', ['ayuda' => 'Debe coincidir con el nombre del propósito en la RA (p. ej. «Propósito General»).']],
                'api_token_key' => ['api_token_key (general)', 'text', 'sgd.firmagob.api_token_key', ['secreto' => true]],
                'secret' => ['Secret (general)', 'text', 'sgd.firmagob.secret', ['secreto' => true]],
            ]],
            'casilla' => ['etiqueta' => 'Casilla Única / Notificador', 'descripcion' => 'Modo outbox: nada se envía hasta registrar evidencia. Contrato de API A VALIDAR.', 'campos' => [
                'mode' => ['Modo', 'select', 'sgd.casilla.mode', ['opciones' => ['outbox' => 'Outbox (manual)', 'api' => 'API']]],
                'api_url' => ['URL de la API', 'url', 'sgd.casilla.api_url'],
                'api_token' => ['Token fijo de la API (alternativa a OAuth2)', 'text', 'sgd.casilla.api_token', ['secreto' => true]],
                'client_id' => ['Client ID (OAuth2)', 'text', 'sgd.casilla.client_id'],
                'client_secret' => ['Client Secret (OAuth2)', 'text', 'sgd.casilla.client_secret', ['secreto' => true]],
                'token_url' => ['URL del token (https)', 'url', 'sgd.casilla.token_url'],
                'scope' => ['Scope (opcional)', 'text', 'sgd.casilla.scope'],
            ]],
            'google_mail' => ['etiqueta' => 'Correo institucional (Gmail API)', 'descripcion' => 'Cuenta de servicio con delegación de dominio (alcance gmail.send).', 'campos' => [
                'mailer' => ['Método de envío', 'select', 'mail.default', ['opciones' => ['gmail_api' => 'API de Gmail (cuenta de servicio con delegación de dominio)', 'log' => 'Solo registro (no se envía ningún correo; para pruebas)'], 'ayuda' => 'Con «Solo registro» los correos quedan en el registro del servidor y no salen.']],
                'sender' => ['Correo del Workspace a suplantar (remitente)', 'text', 'sgd.google.sender', ['ayuda' => 'Debe ser una casilla real del Workspace; los correos se enviarán como si los mandara esta cuenta. Ej.: no-responder@santodomingo.cl']],
                'service_account_json' => ['Archivo JSON de la cuenta de servicio', 'path', 'sgd.google.service_account_json', ['subida' => 'gmail']],
                'rate_per_minute' => ['Máximo de correos por minuto', 'number', 'sgd.google.rate_per_minute'],
            ]],
            'docdigital' => ['etiqueta' => 'DocDigital (comunicaciones oficiales)', 'descripcion' => 'API Middleware V2. Rutas A VALIDAR con la guía de integración.', 'campos' => [
                'mode' => ['Modo', 'select', 'sgd.docdigital.mode', ['opciones' => ['outbox' => 'Outbox (manual)', 'demo' => 'DemoDoc (pruebas)', 'prod' => 'Producción', 'mock' => 'Simulado (SOLO desarrollo)']]],
                'url_demo' => ['URL DemoDoc', 'url', 'sgd.docdigital.url_demo'],
                'url_prod' => ['URL producción', 'url', 'sgd.docdigital.url_prod'],
                'client_id' => ['Client ID de DemoDoc', 'text', 'sgd.docdigital.client_id'],
                'client_secret' => ['Client Secret de DemoDoc', 'text', 'sgd.docdigital.client_secret', ['secreto' => true]],
                'token_url' => ['URL del token (opcional)', 'url', 'sgd.docdigital.token_url', ['ayuda' => 'Por defecto: {URL}/oauth/token, con HTTP Basic (Client ID y Client Secret), según la especificación v3.']],
                'prod_client_id' => ['Client ID de PRODUCCIÓN', 'text', 'sgd.docdigital.prod_client_id', ['ayuda' => 'Con modo «Producción» reemplaza al de DemoDoc; así cada ambiente conserva sus credenciales.']],
                'prod_client_secret' => ['Client Secret de PRODUCCIÓN', 'text', 'sgd.docdigital.prod_client_secret', ['secreto' => true]],
                'prod_entidad_id' => ['Id de la entidad en PRODUCCIÓN', 'text', 'sgd.docdigital.prod_entidad_id', ['ayuda' => 'Los ids de entidades difieren entre DemoDoc y producción: confírmelo con «Probar conexión» en modo Producción.']],
                'prod_tipo_documento_id' => ['Id del tipo de documento por defecto en PRODUCCIÓN', 'text', 'sgd.docdigital.prod_tipo_documento_id'],
                'api_token' => ['Token fijo de la API (alternativa a OAuth2)', 'text', 'sgd.docdigital.api_token', ['secreto' => true]],
                'entidad_id' => ['Id de la entidad creadora en DemoDoc (opcional)', 'text', 'sgd.docdigital.entidad_id', ['ayuda' => 'Si se omite se usa la única entidad asociada al token (GET /entidades/token).']],
                'tipo_documento_id' => ['Id del tipo de documento por defecto en DemoDoc (opcional)', 'text', 'sgd.docdigital.tipo_documento_id', ['ayuda' => 'Vea los tipos disponibles en «Probar conexión». Si se omite se busca el tipo por nombre.']],
                'unidad_recepcion' => ['Código de la unidad receptora', 'text', 'sgd.docdigital.unidad_recepcion'],
            ]],
            'pisee' => ['etiqueta' => 'PISEE (interoperabilidad)', 'descripcion' => 'Nodo de interoperabilidad de la municipalidad.', 'campos' => [
                'mode' => ['Modo', 'select', 'sgd.pisee.mode', ['opciones' => ['disabled' => 'Deshabilitado', 'api' => 'API', 'mock' => 'Simulado (SOLO desarrollo)']]],
                'base_url' => ['URL del nodo', 'url', 'sgd.pisee.base_url', ['privada' => true, 'ayuda' => 'El nodo puede estar en una IP privada de la red municipal.']],
                'header_cliente' => ['Cabecera de identificación', 'text', 'sgd.pisee.header_cliente'],
                'valor_cliente' => ['Valor de la cabecera', 'text', 'sgd.pisee.valor_cliente'],
                'cert' => ['Certificado de cliente (PEM)', 'path', 'sgd.pisee.cert'],
                'key' => ['Clave privada del certificado (PEM)', 'path', 'sgd.pisee.key'],
                'ca' => ['Bundle de CA', 'path', 'sgd.pisee.ca'],
            ]],
            'codificador' => ['etiqueta' => 'Codificador de instituciones y procesos', 'descripcion' => 'Catálogo local; sincronización por API opcional.', 'campos' => [
                'institucion_codigo' => ['Código de esta institución', 'text', 'sgd.institucion_codigo'],
                'mode' => ['Modo', 'select', 'sgd.codificador.mode', ['opciones' => ['manual' => 'Manual (CSV)', 'api' => 'Sincronización por API']]],
                'api_url' => ['URL de la API', 'url', 'sgd.codificador.api_url'],
                'api_token' => ['Token de la API', 'text', 'sgd.codificador.api_token', ['secreto' => true]],
            ]],
            'llm' => ['etiqueta' => 'Asistente de redacción (IA)', 'descripcion' => 'Apagado por defecto. Una sola API HTTPS fija.', 'campos' => [
                'enabled' => ['Habilitado', 'bool', 'sgd.llm.enabled'],
                'api_key' => ['Clave de la API', 'text', 'sgd.llm.api_key', ['secreto' => true]],
                'model' => ['Modelo', 'text', 'sgd.llm.model'],
            ]],
        ];
    }

    /**
     * Presentación por tarjetas de cada servicio: título, texto introductorio, campos y texto del botón de guardado.
     * Los campos del catálogo que no estén en ningún grupo se muestran al final en «Otros ajustes».
     *
     * @return list<array{titulo:string,intro?:string,campos:list<string>,boton?:string}>
     */
    public static function grupos(string $servicio): array
    {
        $g = match ($servicio) {
            'claveunica' => [
                ['titulo' => 'Activación y ambiente', 'intro' => 'El botón de ClaveÚnica se muestra cuando el modo es «Real» y el ambiente activo tiene sus cuatro datos completos (client_id, client_secret, Redirect URI y Logout URI).', 'campos' => ['mode', 'ambiente']],
                ['titulo' => 'Datos para el trámite en Cerofilas', 'intro' => 'Informativos: complételos aquí para tenerlos a mano al llenar el formulario «Solicitud de Credenciales de Integración a ClaveÚnica»; no se envían a Gobierno Digital. Tipo de público objetivo a declarar: Interno (institución) para funcionarios y Público (ciudadanía) para vecinos.', 'campos' => ['institucion', 'app_nombre', 'contacto_admin_nombre', 'contacto_admin_correo', 'contacto_admin_telefono', 'contacto_tec_nombre', 'contacto_tec_correo', 'contacto_tec_telefono']],
                ['titulo' => 'Credenciales — Sandbox / Testing', 'campos' => ['sandbox_client_id', 'sandbox_client_secret', 'sandbox_redirect_uri', 'sandbox_logout_uri'], 'boton' => 'Guardar credenciales de Sandbox / Testing'],
                ['titulo' => 'Credenciales — QA', 'campos' => ['qa_client_id', 'qa_client_secret', 'qa_redirect_uri', 'qa_logout_uri'], 'boton' => 'Guardar credenciales de QA'],
                ['titulo' => 'Credenciales — Producción', 'campos' => ['produccion_client_id', 'produccion_client_secret', 'produccion_redirect_uri', 'produccion_logout_uri'], 'boton' => 'Guardar credenciales de Producción'],
                ['titulo' => 'Credenciales generales (compatibilidad con .env)', 'intro' => 'Se usan solo si el ambiente activo no tiene las suyas.', 'campos' => ['client_id', 'client_secret', 'redirect_uri', 'logout_uri']],
            ],
            'firmagob' => [
                ['titulo' => 'Ambiente', 'campos' => ['mode']],
                ['titulo' => 'Credenciales de PRUEBAS', 'intro' => 'Ambiente de certificación: las firmas no tienen validez y no usan las credenciales reales.', 'campos' => ['test_api_token_key', 'test_secret'], 'boton' => 'Guardar credenciales de pruebas'],
                ['titulo' => 'Credenciales de PRODUCCIÓN', 'intro' => 'Firma con el certificado real del titular.', 'campos' => ['prod_api_token_key', 'prod_secret'], 'boton' => 'Guardar credenciales de producción'],
                ['titulo' => 'Entidad y propósito', 'campos' => ['entity', 'purpose']],
                ['titulo' => 'Credenciales generales (compatibilidad con .env)', 'intro' => 'Se usan solo si el ambiente activo no tiene las suyas.', 'campos' => ['api_token_key', 'secret']],
            ],
            'google_mail' => [
                ['titulo' => 'Transporte y remitente', 'campos' => ['mailer', 'sender', 'rate_per_minute']],
                ['titulo' => 'Cuenta de servicio con delegación de dominio', 'intro' => 'En Google Cloud Console cree una cuenta de servicio, genere una clave (tipo JSON) y descárguela. Luego, como administrador de Google Workspace, en Admin Console → Seguridad → Controles de API → Delegación en todo el dominio, agregue el ID de cliente de la cuenta de servicio (número largo, no el correo) con el permiso (scope) https://www.googleapis.com/auth/gmail.send', 'campos' => ['service_account_json'], 'boton' => 'Guardar cuenta de servicio'],
            ],
            'docdigital' => [
                ['titulo' => 'Conexión', 'campos' => ['mode', 'unidad_recepcion']],
                ['titulo' => 'DemoDoc (pruebas)', 'intro' => 'Credenciales del ambiente de certificación. Los ids de entidad y de tipo de documento difieren de los de producción.', 'campos' => ['url_demo', 'client_id', 'client_secret', 'entidad_id', 'tipo_documento_id'], 'boton' => 'Guardar credenciales de DemoDoc'],
                ['titulo' => 'Producción', 'intro' => 'Requiere la integración certificada por la Secretaría de Gobierno Digital.', 'campos' => ['url_prod', 'prod_client_id', 'prod_client_secret', 'prod_entidad_id', 'prod_tipo_documento_id'], 'boton' => 'Guardar credenciales de producción'],
                ['titulo' => 'Avanzado', 'campos' => ['token_url', 'api_token']],
            ],
            'repositorio' => [
                ['titulo' => 'Almacenamiento', 'campos' => ['modo']],
                ['titulo' => 'Unidad compartida de Google Drive', 'campos' => ['drive_sa_json', 'drive_shared_drive_id', 'drive_root_folder_id'], 'boton' => 'Guardar'],
            ],
            default => [],
        };
        $usados = array_merge(...array_map(fn ($x) => $x['campos'], $g ?: [['campos' => []]]));
        $resto = array_values(array_diff(array_keys(self::servicios()[$servicio]['campos'] ?? []), $usados));
        if ($g === []) {
            return [['titulo' => 'Configuración', 'campos' => $resto]];
        }

        return $resto ? array_merge($g, [['titulo' => 'Otros ajustes', 'campos' => $resto]]) : $g;
    }
}
