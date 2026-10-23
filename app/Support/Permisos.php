<?php

namespace App\Support;

/**
 * Catálogo de permisos. "bases" = roles base que pueden poseerlo (techo de seguridad): un rol personalizado nunca puede
 * recibir un permiso que su rol base no admite. Los roles del sistema tienen exactamente los permisos de su base.
 */
class Permisos
{
    private const OP = ['funcionario', 'alcalde', 'secretario', 'transparencia', 'administrador_municipal'];

    /** «Administrador Municipal»: como el Alcalde pero de menor rango; estos permisos de la Alcaldía no los recibe por defecto (pueden concederse a una persona). */
    private const MENOR_RANGO = ['restringidos.gestionar', 'expedientes.exportar', 'decretopago.ver', 'pisee.consultar'];

    /** @return array<string,array{0:string,1:string,2:array<int,string>,3?:bool,4?:array<int,string>}> clave => [etiqueta, grupo, bases (techo), solo_sistema, bases que lo reciben por defecto (omitido = las mismas)] */
    public static function catalogo(): array
    {
        $op = self::OP;
        $opa = [...self::OP, 'auditor'];

        return [
            'expedientes.operar' => ['Operar expedientes (comentar, cargar archivos, editar metadatos)', 'Expedientes', $op],
            'expedientes.crear' => ['Iniciar expedientes de oficio: crear un expediente sin solicitud ciudadana, registrar sus partes y comenzar un procedimiento (decreto, reglamento, oficio, etc.)', 'Expedientes', $op, false, ['funcionario', 'alcalde', 'secretario']],
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
            'plantillas.ver' => ['Ver el catálogo de formatos y plantillas', 'Documentos', [...self::OP, 'auditor', 'admin']],
            'plantillas.gestionar' => ['Administrar formatos y plantillas (editor, visibilidad por unidad, imágenes)', 'Administración', ['admin']],
            'tramites.gestionar' => ['Crear, editar y deshabilitar los trámites y servicios de su dirección municipal', 'Trámites y servicios', [...self::OP, 'admin'], false, ['admin']],
            'tramites.publicar' => ['Publicar y reactivar trámites de su dirección (los pone a disposición de la ciudadanía)', 'Trámites y servicios', [...self::OP, 'admin'], false, ['admin']],
            'docdigital.gestionar' => ['Gestionar la conexión con DocDigital (probar, sincronizar ahora y reintentar envíos)', 'Notificaciones y comunicaciones', [...self::OP, 'admin'], false, ['secretario', 'admin']],
            'seguimiento.consultar' => ['Consultar el seguimiento de solicitudes (por RUN, fechas, dirección, etapa o trámite): estado, etapa, quién la tiene y si terminó', 'Seguimiento de solicitudes', ['autoridad', 'alcalde', 'administrador_municipal', 'auditor'], false, ['autoridad', 'alcalde', 'auditor']],
            'oirs.ver' => ['Panel OIRS: solicitudes, estado, responsables, plazos vencidos y por vencer', 'Seguimiento de solicitudes', ['oirs', 'auditor'], false, ['oirs']],
            'transparencia.ver' => ['Ver el panel de transparencia: consultas (solicitudes de acceso a la información), plazos y transparencia activa', 'Transparencia', ['transparencia', 'auditor'], false, ['transparencia']],
            'transparencia.gestionar' => ['Gestionar transparencia: prorrogar plazos, requerir a las direcciones, derivar a otro órgano, administrar feriados y registrar la transparencia activa', 'Transparencia', ['transparencia'], false, ['transparencia']],
            'transparencia.responder' => ['Responder los requerimientos de transparencia que recibe su dirección', 'Transparencia', [...self::OP], false, ['funcionario', 'alcalde', 'secretario', 'transparencia']],
            'transparencia.reportes' => ['Generar y exportar los reportes de transparencia', 'Transparencia', ['transparencia', 'auditor'], false, ['transparencia']],
            'archivos.configurar' => ['Configurar los pesos máximos y los formatos de archivo que admite el sistema', 'Administración', ['admin'], false, ['admin']],
            'areas.gestionar' => ['Configurar las áreas del municipio (municipal, salud, educación, corporación) y sus unidades', 'Administración', ['admin'], false, ['admin']],
            'codificacion.configurar' => ['Configurar el municipio ante el Codificador, la codificación de documentos y expedientes y ver la matriz de normas técnicas', 'Administración', ['admin'], false, ['admin']],
            'produccion.gestionar' => ['Pasar el sistema de pruebas a producción (borra los procesos y expedientes de prueba)', 'Administración', ['admin'], true, ['admin']],
            'simulacion.gestionar' => ['Cargar datos y procesos simulados en el ambiente de pruebas', 'Administración', ['admin'], true, ['admin']],
            'feriados.gestionar' => ['Administrar los feriados y días no laborables que no cuentan como días administrativos en los plazos', 'Administración', ['admin', 'transparencia'], false, ['admin', 'transparencia']],
            'expedientes.vincular' => ['Buscar otros expedientes y microformas y asociarlos (con su contenido) al expediente que gestiona', 'Expedientes', [...self::OP], false, [...self::OP]],
            'decretopago.ver' => ['Ver los decretos de pago y sus egresos', 'Decreto de pago', [...self::OP, 'auditor'], false, ['auditor', 'alcalde']],
            'alcaldia.panel' => ['Ver el panel de Alcaldía: firmas pendientes, decretos de pago y solicitudes de toda la municipalidad', 'Seguimiento de solicitudes', ['alcalde', 'secretario', 'administrador_municipal'], false, ['alcalde']],
            'decretopago.gestionar' => ['Crear decretos de pago: cargar el expediente de pago y el proceso de compra, generar o cargar el PDF y enviarlo a firma', 'Decreto de pago', [...self::OP], false, []],
            'decretopago.configurar' => ['Configurar el decreto de pago y el egreso: correlativo y firmantes', 'Decreto de pago', [...self::OP, 'admin'], false, ['admin']],
            'egresos.gestionar' => ['Generar el egreso de un decreto de pago (fecha, medio de pago, monto y detalle) y firmarlo', 'Decreto de pago', [...self::OP], false, []],
            'respuestas.instruir' => ['Indicar la respuesta final de notificación de una solicitud: qué documentos y archivos del expediente se entregan al solicitante y por qué canal', 'Respuesta al solicitante', [...self::OP], false, [...self::OP]],
            'respuestas.despachar' => ['Responder al solicitante (p. ej. Oficina de Partes): ver las respuestas finales instruidas, notificarlas por Casilla Única con copia por correo o registrar la entrega física', 'Respuesta al solicitante', [...self::OP], false, []],
            'anuncios.gestionar' => ['Administrar los anuncios que se muestran en un modal al cargar el sitio (título, descripción, imagen o video)', 'Administración', ['admin', 'oirs', 'transparencia', 'secretario', 'alcalde'], false, ['admin', 'oirs']],
            'restringidos.gestionar' => ['Marcar expedientes, documentos y archivos como restringidos y administrar quién puede verlos', 'Protección de datos', [...self::OP], false, [...self::OP]],
            'datospersonales.ver' => ['Ver las solicitudes cuyo titular no autorizó el tratamiento de sus datos personales (Oficial de Protección de Datos, DPO)', 'Protección de datos', ['admin', 'auditor'], false, ['admin', 'auditor']],
            'restringidos.auditar' => ['Ver quiénes vieron o intentaron ver expedientes, documentos y archivos restringidos', 'Protección de datos', ['admin', 'auditor'], false, ['admin', 'auditor']],
            'casilla.gestionar' => ['Gestionar el notificador de Casilla Única (probar conexión y reintentar envíos fallidos)', 'Notificaciones y comunicaciones', [...self::OP, 'admin'], false, ['secretario', 'admin']],
            'archivo.ver' => ['Ver y consultar las microformas del archivo municipal', 'Archivo municipal', $opa, false, ['auditor']],
            'archivo.gestionar' => ['Registrar y editar microformas, cargar sus archivos y su índice', 'Archivo municipal', $op, false, []],
            'archivo.certificar' => ['Certificar microformas (registrar el acta del ministro de fe) o devolverlas a borrador', 'Archivo municipal', $op, false, []],
            'archivo.disposicion' => ['Autorizar la disposición (eliminación o transferencia) de los documentos originales', 'Archivo municipal', ['alcalde', 'secretario'], false, []],
            'llm.usar' => ['Usar el asistente de redacción con IA', 'Documentos', $op],
            'delegaciones.ver' => ['Ver delegaciones de firma', 'Firma', ['alcalde', 'admin', 'auditor']],
            'delegaciones.crear' => ['Crear y revocar delegaciones de firma', 'Firma', ['alcalde'], true],
            'seguridad.ver' => ['Ver panel de seguridad', 'Seguridad', ['admin', 'auditor']],
            'incidentes.gestionar' => ['Registrar y actualizar incidentes', 'Seguridad', ['admin']],
            'logs.ver' => ['Ver registro de auditoría y logs', 'Seguridad', ['admin', 'auditor']],
            'logs.exportar' => ['Exportar registro de auditoría', 'Seguridad', ['admin', 'auditor']],
            'sesiones.ver' => ['Ver usuarios conectados', 'Seguridad', ['admin']],
            'sesiones.cerrar' => ['Cerrar sesiones de usuarios', 'Seguridad', ['admin']],
            'conexiones.ver' => ['Ver mapa y ranking de conexiones (IP y países)', 'Seguridad', ['admin', 'auditor']],
            'recursos.ver' => ['Ver consumo de recursos del servidor (CPU, memoria, disco)', 'Seguridad', ['admin']],
            'componentes.ver' => ['Ver librerías, versiones y recursos externos (CDN)', 'Seguridad', ['admin', 'auditor']],
            'diagnostico.ver' => ['Ver diagnóstico del servidor', 'Administración', ['admin']],
            'mantenedores.gestionar' => ['Mantenedores (unidades, usuarios, trámites, tipos, plantillas)', 'Administración', ['admin']],
            'codificador.gestionar' => ['Codificador y servicios PISEE', 'Administración', ['admin']],
            'apariencia.gestionar' => ['Apariencia: logo, colores, header, footer', 'Administración', ['admin']],
            'menu.gestionar' => ['Barra de menú', 'Administración', ['admin']],
            'api.gestionar' => ['Configuración de APIs e integraciones', 'Administración', ['admin']],
            'firmas.gestionar' => ['Estilo y posición de firmas', 'Administración', ['admin']],
            'folios.gestionar' => ['Folios por tipo de documento', 'Administración', ['admin']],
            'repositorio.gestionar' => ['Repositorio de documentos (Google Drive)', 'Administración', ['admin']],
            'cumplimiento.ver' => ['Ver el cumplimiento normativo: matriz de normas técnicas, política de gestión documental, catálogo de plataformas y plan de mejora continua', 'Administración', ['admin', 'auditor'], false, ['admin', 'auditor']],
            'cumplimiento.gestionar' => ['Registrar la política de gestión documental, el coordinador de transformación digital, el catálogo de plataformas y el plan de mejora continua', 'Administración', ['admin'], false, ['admin']],
            'roles.gestionar' => ['Roles y permisos', 'Administración', ['admin'], true],
        ];
    }

    /** @return array<int,string> permisos que recibe POR DEFECTO un rol del sistema de esa base */
    public static function deBase(string $base): array
    {
        if ($base === 'administrador_municipal') {
            return array_values(array_diff(array_intersect(self::deBase('alcalde'), self::techo($base)), self::MENOR_RANGO));
        }
        $out = [];
        foreach (self::catalogo() as $k => $d) {
            if (in_array($base, $d[4] ?? $d[2], true)) {
                $out[] = $k;
            }
        }

        return $out;
    }

    /** @return array<int,string> TECHO: todos los permisos que un rol de esa base puede llegar a tener (los opcionales no se otorgan solos) */
    public static function techo(string $base): array
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
