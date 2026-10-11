<?php

namespace App\Support;

/**
 * Catálogo de permisos. "bases" = roles base que pueden poseerlo (techo de seguridad): un rol personalizado nunca puede
 * recibir un permiso que su rol base no admite. Los roles del sistema tienen exactamente los permisos de su base.
 */
class Permisos
{
    private const OP = ['funcionario', 'alcalde', 'secretario'];

    /** @return array<string,array{0:string,1:string,2:array<int,string>,3?:bool}> clave => [etiqueta, grupo, bases, solo_sistema] */
    public static function catalogo(): array
    {
        $op = self::OP;
        $opa = [...self::OP, 'auditor'];

        return [
            'expedientes.operar' => ['Operar expedientes (comentar, cargar archivos, editar metadatos)', 'Expedientes', $op],
            'expedientes.derivar' => ['Derivar expedientes', 'Expedientes', $op],
            'expedientes.devolver' => ['Devolver solicitudes al ciudadano', 'Expedientes', $op],
            'expedientes.cerrar' => ['Cerrar y reabrir expedientes', 'Expedientes', $op],
            'expedientes.exportar' => ['Exportar metadatos de expedientes', 'Expedientes', $opa],
            'documentos.crear' => ['Crear y redactar documentos', 'Documentos', $op],
            'vigentes.ver' => ['Ver documentos vigentes', 'Documentos', $opa],
            'notificaciones.gestionar' => ['Notificar al ciudadano', 'Notificaciones y comunicaciones', $op],
            'comunicaciones.ver' => ['Ver comunicaciones oficiales (DocDigital)', 'Notificaciones y comunicaciones', $opa],
            'comunicaciones.acusar' => ['Acusar recibo de comunicaciones', 'Notificaciones y comunicaciones', $op],
            'comunicaciones.despachar' => ['Despachar comunicaciones por DocDigital', 'Notificaciones y comunicaciones', $op],
            'pisee.consultar' => ['Consultar servicios PISEE', 'Notificaciones y comunicaciones', $op],
            'llm.usar' => ['Usar el asistente de redacción con IA', 'Documentos', $op],
            'delegaciones.ver' => ['Ver delegaciones de firma', 'Firma', ['alcalde', 'admin', 'auditor']],
            'delegaciones.crear' => ['Crear y revocar delegaciones de firma', 'Firma', ['alcalde'], true],
            'seguridad.ver' => ['Ver panel de seguridad', 'Seguridad', ['admin', 'auditor']],
            'incidentes.gestionar' => ['Registrar y actualizar incidentes', 'Seguridad', ['admin']],
            'logs.ver' => ['Ver registro de auditoría y logs', 'Seguridad', ['admin', 'auditor']],
            'logs.exportar' => ['Exportar registro de auditoría', 'Seguridad', ['admin', 'auditor']],
            'sesiones.ver' => ['Ver usuarios conectados', 'Seguridad', ['admin']],
            'sesiones.cerrar' => ['Cerrar sesiones de usuarios', 'Seguridad', ['admin']],
            'diagnostico.ver' => ['Ver diagnóstico del servidor', 'Administración', ['admin']],
            'mantenedores.gestionar' => ['Mantenedores (unidades, usuarios, trámites, tipos, plantillas)', 'Administración', ['admin']],
            'codificador.gestionar' => ['Codificador y servicios PISEE', 'Administración', ['admin']],
            'apariencia.gestionar' => ['Apariencia: logo, colores, header, footer', 'Administración', ['admin']],
            'menu.gestionar' => ['Barra de menú', 'Administración', ['admin']],
            'api.gestionar' => ['Configuración de APIs e integraciones', 'Administración', ['admin']],
            'firmas.gestionar' => ['Estilo y posición de firmas', 'Administración', ['admin']],
            'folios.gestionar' => ['Folios por tipo de documento', 'Administración', ['admin']],
            'repositorio.gestionar' => ['Repositorio de documentos (Google Drive)', 'Administración', ['admin']],
            'roles.gestionar' => ['Roles y permisos', 'Administración', ['admin'], true],
        ];
    }

    /** @return array<int,string> permisos por defecto (y techo) de un rol base */
    public static function deBase(string $base): array
    {
        $out = [];
        foreach (self::catalogo() as $k => $d) {
            if (in_array($base, $d[2], true)) {
                $out[] = $k;
            }
        }

        return $out;
    }
}
