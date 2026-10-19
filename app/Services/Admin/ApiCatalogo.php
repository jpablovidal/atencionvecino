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
            'claveunica' => ['etiqueta' => 'ClaveÚnica (OIDC)', 'descripcion' => 'Autenticación de ciudadanos y funcionarios.', 'campos' => [
                'mode' => ['Modo', 'select', 'sgd.claveunica.mode', ['opciones' => ['real' => 'Real', 'mock' => 'Simulado (SOLO desarrollo)']]],
                'client_id' => ['Client ID', 'text', 'sgd.claveunica.client_id'],
                'client_secret' => ['Client Secret', 'text', 'sgd.claveunica.client_secret', ['secreto' => true]],
                'redirect_uri' => ['URL de retorno', 'url', 'sgd.claveunica.redirect_uri'],
            ]],
            'firmagob' => ['etiqueta' => 'FirmaGob (firma electrónica avanzada)', 'descripcion' => 'API v2 de firma. Pruebe primero en el ambiente test.', 'campos' => [
                'mode' => ['Ambiente', 'select', 'sgd.firmagob.mode', ['opciones' => ['test' => 'Pruebas (test)', 'prod' => 'Producción', 'mock' => 'Simulado (SOLO desarrollo)']]],
                'api_token_key' => ['api_token_key', 'text', 'sgd.firmagob.api_token_key', ['secreto' => true]],
                'secret' => ['Clave del JWT (HS256)', 'text', 'sgd.firmagob.secret', ['secreto' => true]],
                'entity' => ['Entidad', 'text', 'sgd.firmagob.entity'],
                'purpose' => ['Propósito', 'text', 'sgd.firmagob.purpose'],
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
                'sender' => ['Buzón remitente', 'text', 'sgd.google.sender', ['ayuda' => 'p. ej. no-responder@santodomingo.cl']],
                'service_account_json' => ['Archivo JSON de la cuenta de servicio', 'path', 'sgd.google.service_account_json', ['subida' => 'gmail']],
                'rate_per_minute' => ['Máximo de correos por minuto', 'number', 'sgd.google.rate_per_minute'],
            ]],
            'docdigital' => ['etiqueta' => 'DocDigital (comunicaciones oficiales)', 'descripcion' => 'API Middleware V2. Rutas A VALIDAR con la guía de integración.', 'campos' => [
                'mode' => ['Modo', 'select', 'sgd.docdigital.mode', ['opciones' => ['outbox' => 'Outbox (manual)', 'demo' => 'DemoDoc (pruebas)', 'prod' => 'Producción', 'mock' => 'Simulado (SOLO desarrollo)']]],
                'url_demo' => ['URL DemoDoc', 'url', 'sgd.docdigital.url_demo'],
                'url_prod' => ['URL producción', 'url', 'sgd.docdigital.url_prod'],
                'client_id' => ['Client ID (OAuth2)', 'text', 'sgd.docdigital.client_id'],
                'client_secret' => ['Client Secret (OAuth2)', 'text', 'sgd.docdigital.client_secret', ['secreto' => true]],
                'token_url' => ['URL del token (opcional)', 'url', 'sgd.docdigital.token_url', ['ayuda' => 'Por defecto: {URL}/oauth/token, con HTTP Basic (Client ID y Client Secret), según la especificación v3.']],
                'prod_client_id' => ['Client ID de PRODUCCIÓN (opcional)', 'text', 'sgd.docdigital.prod_client_id', ['ayuda' => 'Con modo «Producción» reemplaza al Client ID general; así DemoDoc y producción conservan cada uno sus credenciales.']],
                'prod_client_secret' => ['Client Secret de PRODUCCIÓN (opcional)', 'text', 'sgd.docdigital.prod_client_secret', ['secreto' => true]],
                'prod_entidad_id' => ['Id de la entidad en PRODUCCIÓN (opcional)', 'text', 'sgd.docdigital.prod_entidad_id', ['ayuda' => 'Los ids de entidades difieren entre DemoDoc y producción: confírmelo con «Probar conexión» en modo Producción.']],
                'prod_tipo_documento_id' => ['Id del tipo de documento en PRODUCCIÓN (opcional)', 'text', 'sgd.docdigital.prod_tipo_documento_id'],
                'api_token' => ['Token fijo de la API (alternativa a OAuth2)', 'text', 'sgd.docdigital.api_token', ['secreto' => true]],
                'entidad_id' => ['Id de la entidad creadora (opcional)', 'text', 'sgd.docdigital.entidad_id', ['ayuda' => 'Si se omite se usa la única entidad asociada al token (GET /entidades/token).']],
                'tipo_documento_id' => ['Id del tipo de documento por defecto (opcional)', 'text', 'sgd.docdigital.tipo_documento_id', ['ayuda' => 'Vea los tipos disponibles en «Probar conexión». Si se omite se busca el tipo por nombre.']],
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
}
