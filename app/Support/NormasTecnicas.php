<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Normas técnicas de la Ley 21.180 (Transformación Digital del Estado) y matriz de cumplimiento del sistema.
 *
 * Los decretos 7, 8, 9, 10, 11 y 12 (todos de 2023, publicados el 17-08-2023) se leyeron en su texto oficial (Biblioteca del Congreso Nacional,
 * versión del 21-10-2026; el Decreto 12 en su última versión, modificada por el Decreto 876 del 24-12-2025). Las guías técnicas que cada norma ordena dictar a la División de Gobierno
 * Digital no se entregaron: lo que depende de ellas (nombres exactos de metadatos, formatos, contratos de API) sigue «A VALIDAR».
 */
class NormasTecnicas
{
    public const ESTADOS = [
        'implementado' => 'Implementado',
        'parcial' => 'Parcial',
        'pendiente' => 'Pendiente',
        'institucional' => 'Del municipio',
        'externo' => 'Componente externo',
    ];

    /** Fecha en que termina la versión anterior de PISEE (Decreto 12, artículo segundo transitorio). */
    public const PISEE_FIN = '2026-12-31';

    /** @return array<int,array{numero:int,titulo:string,publicacion:string,version:string,bcn:?string,wiki:string,leido:bool}> */
    public static function decretos(): array
    {
        return [
            7 => ['numero' => 7, 'titulo' => 'Norma Técnica de Seguridad de la Información y Ciberseguridad', 'publicacion' => '17-08-2023', 'version' => 'Única', 'bcn' => 'https://bcn.cl/3hri8', 'wiki' => 'https://wikiguias.digital.gob.cl/Normas/Decreto7', 'leido' => true],
            8 => ['numero' => 8, 'titulo' => 'Norma Técnica de Notificaciones', 'publicacion' => '17-08-2023', 'version' => 'Única', 'bcn' => 'https://bcn.cl/3lbiy', 'wiki' => 'https://wikiguias.digital.gob.cl/Normas/Decreto8', 'leido' => true],
            9 => ['numero' => 9, 'titulo' => 'Norma Técnica de Autenticación', 'publicacion' => '17-08-2023', 'version' => 'Única', 'bcn' => 'https://bcn.cl/3h75f', 'wiki' => 'https://wikiguias.digital.gob.cl/Normas/Decreto9', 'leido' => true],
            10 => ['numero' => 10, 'titulo' => 'Norma Técnica de Documentos y Expedientes Electrónicos para la gestión de procedimientos administrativos', 'publicacion' => '17-08-2023', 'version' => 'Única', 'bcn' => 'https://bcn.cl/3hh4y', 'wiki' => 'https://wikiguias.digital.gob.cl/Normas/Decreto10', 'leido' => true],
            11 => ['numero' => 11, 'titulo' => 'Norma Técnica de Calidad y Funcionamiento de las plataformas electrónicas que sustentan procedimientos administrativos', 'publicacion' => '17-08-2023', 'version' => 'Única', 'bcn' => 'https://bcn.cl/3m2os', 'wiki' => 'https://wikiguias.digital.gob.cl/Normas/Decreto11', 'leido' => true],
            12 => ['numero' => 12, 'titulo' => 'Norma Técnica de Interoperabilidad', 'publicacion' => '17-08-2023', 'version' => 'Última versión del 24-12-2025 (modificada por el Decreto 876)', 'bcn' => 'https://bcn.cl/OsIA4v', 'wiki' => 'https://wikiguias.digital.gob.cl/Normas/Decreto12', 'leido' => true],
        ];
    }

    /** Aviso por el fin de la versión anterior de PISEE (Decreto 12, segundo transitorio). */
    public static function avisoPisee(?Carbon $hoy = null): string
    {
        $hoy ??= now('America/Santiago');
        $fin = Carbon::parse(self::PISEE_FIN, 'America/Santiago')->endOfDay();
        if ($hoy->gt($fin)) {
            return 'La versión anterior de PISEE dejó de funcionar el 31-12-2026: desde esa fecha la red de interoperabilidad del Estado (Decreto 12) es la única vía. Este adaptador PISEE ya no se puede usar.';
        }
        $d = (int) $hoy->copy()->startOfDay()->diffInDays($fin->copy()->startOfDay());

        return "La versión anterior de PISEE termina el 31-12-2026 (en $d días): desde esa fecha la red de interoperabilidad del Estado (Decreto 12, artículo segundo transitorio) es la única vía. Hay que migrar antes; este adaptador corresponde a PISEE.";
    }

    /**
     * Requisitos por decreto y lo que hace el sistema. Estados: implementado (lo hace el sistema y está probado), parcial, pendiente (no lo hace el sistema),
     * institucional (lo define o decide el municipio) y externo (lo provee Gobierno Digital u otro órgano).
     *
     * @return list<array{decreto:int,art:string,requisito:string,estado:string,sistema:string,donde:string}>
     */
    public static function matriz(): array
    {
        $f = fn (int $d, string $art, string $req, string $estado, string $sis, string $donde = '') => ['decreto' => $d, 'art' => $art, 'requisito' => $req, 'estado' => $estado, 'sistema' => $sis, 'donde' => $donde];

        return [
            // ---- Decreto 7: seguridad de la información y ciberseguridad
            $f(7, '3-4', 'Diagnóstico inicial de ciberseguridad de las plataformas, incluido en el Catálogo de Plataformas (Decreto 11)', 'parcial', 'Se registra la fecha y el resumen del diagnóstico y el catálogo de plataformas. El método del diagnóstico lo fijarán las guías técnicas (no entregadas): A VALIDAR.', 'Administración › Cumplimiento normativo'),
            $f(7, '5', 'Política de Seguridad de la Información y Ciberseguridad aprobada por acto administrativo de la Jefatura Superior, con objetivos, alcance, normativa y roles', 'institucional', 'El texto y el acto los debe emitir el municipio; el sistema registra el acto, la fecha y la vigencia, y avisa cuando toca revisarla.', 'Administración › Cumplimiento normativo'),
            $f(7, '5 (4)', 'Responsable institucional de seguridad y ciberseguridad y responsable de los activos de información, no externalizables', 'parcial', 'Se registran ambas personas y el sistema avisa si falta alguna. La designación y que no se externalice es decisión del municipio (si ya existe encargado de ciberseguridad según el Instructivo Presidencial 8/2018, se entiende cumplido).', 'Administración › Cumplimiento normativo'),
            $f(7, '7', 'Función de identificación: contexto, gobernanza, activos de información, gestión de riesgos y proveedores en la nube', 'parcial', 'Matriz de riesgos OWASP y pentest documentados, inventario de librerías (SBOM), catálogo de plataformas y proveedores (Google, Gobierno Digital). Falta el inventario y la clasificación formal de activos de información y el análisis de riesgos institucional (municipio).', 'docs/SEGURIDAD.md · docs/PENTEST.md · Inventario de librerías'),
            $f(7, '8', 'Función de protección: servidores, redes, autenticación y control de acceso, concienciación, seguridad de los datos y registro de eventos', 'parcial', 'Implementado: ClaveÚnica, segundo factor obligatorio del administrador, roles y permisos mínimos, cifrado de archivos en reposo, cabeceras y CSP estrictas, límites de intentos, bitácora con cadena HMAC. Del municipio: seguridad del servidor y la red (hosting), capacitación de funcionarios.', 'docs/SEGURIDAD.md'),
            $f(7, '9', 'Función de detección: análisis de eventos, monitoreo continuo y protección contra código malicioso', 'parcial', 'Verificación diaria de la cadena de auditoría, alertas de accesos denegados y errores, validación de archivos por firma binaria y defensa contra código malicioso en plantillas e IA. No hay antivirus en el hosting compartido ni monitoreo externo continuo.', 'Seguridad · docs/SEGURIDAD.md'),
            $f(7, '10', 'Función de respuesta: planificación, comunicación, análisis y mitigación de incidentes y mejora', 'parcial', 'Plan de incidentes y panel con hitos de reporte al CSIRT (plazos A VALIDAR). La comunicación y el reporte los hace el encargado de forma manual.', 'Seguridad › Incidentes · docs/INCIDENTES.md'),
            $f(7, '11', 'Función de recuperación: planes de recuperación y comunicación del estado', 'parcial', 'Respaldo y restauración documentados, cola de envíos con reintentos. El plan de recuperación ante desastres formal y las pruebas de restauración son del municipio.', 'README · docs/INCIDENTES.md'),
            $f(7, '12-13', 'Guías técnicas y gradualidad dictadas por la División de Gobierno Digital', 'externo', 'Las guías técnicas no han sido entregadas: los detalles operativos de cada función quedan A VALIDAR cuando se publiquen.', ''),
            $f(7, '14', 'Revisar y actualizar la norma cada dos años', 'institucional', 'Debe revisarse al menos cada dos años desde su vigencia (17-08-2023): la matriz del sistema se vuelve a contrastar cuando se publiquen versiones nuevas.', ''),

            // ---- Decreto 8: notificaciones
            $f(8, '3, 5, 10', 'Domicilio Digital Único (DDU): Casilla Única o correo, a elección del interesado; Registro de DDU del Registro Civil', 'parcial', 'El formulario permite elegir el canal (correo, Casilla Única o presencial) y registra la elección. No consulta el Registro de DDU del Registro Civil (su API y guía técnica no se entregaron).', 'Formulario de solicitud · Casilla Única'),
            $f(8, '7', 'Aviso de notificación: informativo, nunca sustituye a la notificación', 'implementado', 'El correo de aviso a quien usa Casilla Única es una copia informativa sin documentos ni datos personales; la notificación es la de la Casilla.', 'Correos de aviso'),
            $f(8, '8', 'Excepción a la notificación electrónica (soporte papel/presencial)', 'parcial', 'Canal presencial con acta y constancia de fecha y hora. No se comunica la excepción al Registro Civil (módulo institucional).', 'Notificar al ciudadano'),
            $f(8, '11-12', 'Administrador institucional y envío por módulo institucional o API de notificaciones', 'parcial', 'Adaptador configurable de Casilla Única (modo outbox o API) y permiso para gestionarlo. El contrato real de la API de la Plataforma de Notificaciones es A VALIDAR con su guía técnica.', 'APIs e integraciones › Casilla Única'),
            $f(8, '13', 'Datos del envío: código del órgano, RUN, id de expediente, id del procedimiento, tipo, asunto y descripción, documentos o URI', 'parcial', 'El sistema ya registra RUN, expediente, documento y canal, y conoce el código de institución y del procedimiento (CPAT); falta enviarlos en el formato exacto de la API, que depende de la guía técnica (A VALIDAR).', 'Notificaciones'),
            $f(8, '15, 17-18', 'Resultado del envío con código de transacción y estados (enviada, excepcionada, sin DDU, fallida: reenviar)', 'parcial', 'Estados pendiente, enviada y fallida con reintentos y evidencia (folio de la Casilla o ID del mensaje). Los estados «excepcionada» y «sin DDU» dependen del Registro de DDU.', 'Expediente › Notificaciones'),
            $f(8, '16, 19', 'Constancia de fecha y hora del envío, y de la notificación hecha en dependencias', 'implementado', 'Cada notificación guarda su evidencia con fecha y hora; la presencial registra el acta con quien la realizó.', 'Expediente › Notificaciones'),
            $f(8, '20', 'Correo como DDU: informar al interesado que mantenga la cuenta habilitada, revise el correo no deseado y verifique el remitente', 'implementado', 'El formulario muestra esas tres recomendaciones al elegir «correo», y los avisos indican el remitente institucional.', 'Formulario de solicitud'),
            $f(8, '21', 'Interrupción del servicio: reenviar al volver a estar disponible', 'parcial', 'Los envíos fallidos quedan marcados y se pueden reintentar; no hay certificado de indisponibilidad de la plataforma.', 'Notificaciones › Reintentar'),

            // ---- Decreto 9: autenticación
            $f(9, '3-4, 10', 'Usar mecanismos oficiales de autenticación (ClaveÚnica) con integración certificada', 'implementado', 'Ingreso único con ClaveÚnica (OpenID Connect) con las credenciales certificadas por Gobierno Digital; el RUN lo entrega ClaveÚnica y no se puede modificar.', 'Ingreso'),
            $f(9, '3 inc. 2', 'Otros mecanismos solo con autorización expresa previa de SEGPRES', 'parcial', 'Existe un ingreso alternativo de funcionarios con usuario y contraseña del directorio municipal (no es un mecanismo oficial). Hay que obtener la autorización de SEGPRES o desactivarlo en APIs e integraciones › Directorio.', 'Directorio › Permitir ingresar con usuario y contraseña'),
            $f(9, '5', 'Clave Tributaria para personas jurídicas', 'pendiente', 'El sistema atiende solo personas naturales con ClaveÚnica; no integra Clave Tributaria (personas jurídicas, apoderados o representantes).', ''),
            $f(9, '6', 'OpenID Connect y OAuth 2.0; factores almacenados cifrados; transmisión con TLS 1.2 o superior', 'implementado', 'Flujo de código con «state» de un solo uso; el sistema no guarda contraseñas ni factores de ClaveÚnica; las comunicaciones usan HTTPS (el TLS lo provee el hosting con AutoSSL).', 'Ingreso · docs/CLAVEUNICA_BOTON.md'),
            $f(9, '7', 'Prevención de accesos no autorizados: límite de intentos fallidos con bloqueo y desbloqueo', 'implementado', 'ClaveÚnica administra su propio límite; el ingreso por usuario del directorio bloquea tras varios intentos fallidos durante 15 minutos y limita por IP.', 'Ingreso'),
            $f(9, '8-9', 'Lineamientos gráficos y accesibilidad del proceso de autenticación', 'implementado', 'Botón oficial de ClaveÚnica según el manual de marca; pantalla de ingreso accesible (WCAG).', 'Ingreso · docs/ACCESIBILIDAD.md'),
            $f(9, '12', 'Informar al CSIRT y al administrador del mecanismo ante sospecha de riesgos', 'parcial', 'El módulo de incidentes permite marcar un incidente como reportable al CSIRT y registrar su alerta temprana; la comunicación al CSIRT la hace la persona responsable.', 'Seguridad › Incidentes'),
            $f(9, '13', 'Registro de accesos autenticados: identificador del usuario y fecha y hora en UTC+00:00', 'implementado', 'Cada ingreso y salida queda en la auditoría con RUN, rol, IP y hora; la bitácora y su exportación muestran la hora en UTC además de la hora de Chile. La sincronización horaria con el SHOA la provee el hosting (A VALIDAR con el proveedor).', 'Seguridad › Auditoría'),
            $f(9, '14', 'Protección de datos personales y derechos ARCO', 'implementado', 'Política de privacidad pública (/privacidad), consentimiento informado al ingresar una solicitud y posibilidad de cambiarlo después.', 'Pie de página › Privacidad'),
            $f(9, '15', 'Factores de autenticación adicionales en plataformas con información sensible', 'implementado', 'Segundo factor (TOTP) obligatorio para la administración.', 'Seguridad › Segundo factor'),

            // ---- Decreto 10: documentos y expedientes
            $f(10, '3-4', 'Política de Gestión Documental (objetivo, roles, procedimientos, preservación, criterios, gobernanza) revisada cada 1 a 3 años', 'parcial', 'El sistema registra la política vigente (decreto, fecha de aprobación) y avisa cuando corresponde revisarla. Redactarla y aprobarla es del municipio.', 'Cumplimiento normativo › Política de Gestión Documental'),
            $f(10, '5-6', 'Plataforma de gestión de expedientes y las 5 fases del ciclo vital (recepción, creación, procesamiento, transición, disposición final)', 'parcial', 'Recepción (formulario web y comunicaciones DocDigital), creación de expediente, procesamiento (derivar, comentar, documentos, notificar) y disposición con acta en el Archivo municipal. La transferencia al Archivo Nacional es manual.', 'Expedientes · Archivo municipal'),
            $f(10, '7, 17, 32', 'Repositorio documental con preservación (confidencialidad, disponibilidad e integridad) y plazos de conservación', 'parcial', 'Archivos cifrados en reposo con hash por versión, repositorio local o Google Drive, respaldo documentado. Los plazos de conservación y la transferencia al Archivo Nacional los define la Política (municipio).', 'Repositorio · docs/SEGURIDAD.md'),
            $f(10, '8', 'Codificación con el Gestor de Códigos del Estado (órgano, procedimiento y cobertura geográfica)', 'parcial', 'Código de institución PE-MUN-00243 del Codificador, cliente de su API y código CPAT por trámite. La ubicación (comuna, provincia, región, país) se registra en los metadatos; los códigos territoriales del Gestor son A VALIDAR.', 'Administración › Codificación y normas'),
            $f(10, '9', 'Fecha y hora de toda gestión en UTC+00:00, con ubicación geográfica, sincronizada con el SHOA', 'parcial', 'Los metadatos del expediente llevan la fecha de apertura en UTC y con el desfase de Chile, y la ubicación; la bitácora muestra y exporta la hora UTC. Los registros se almacenan con la hora de Chile (la conversión es exacta); la sincronización con el SHOA es del hosting.', 'Expediente › Metadatos · Auditoría'),
            $f(10, '11, 19, 34', 'Trazabilidad mínima: creación, incorporación, modificación, transferencia y eliminación; acceso al expediente; poderes', 'implementado', 'Cadena de auditoría HMAC de toda acción con RUN y hora, versiones con motivo y responsables, registro de cada apertura de un expediente (con datos sensibles) y del responsable. Las acciones automáticas se identifican como «sistema».', 'Seguridad › Auditoría · Expediente'),
            $f(10, '12-14, 22', 'Estructura y metadatos estandarizados del documento y del expediente', 'parcial', 'Metadatos MGDEE (expediente) y MGDDE (documento) vinculados al índice electrónico. Los nombres exactos de los campos dependen de la guía técnica (no entregada): esquema marcado «A VALIDAR».', 'Expediente › Metadatos'),
            $f(10, '13', 'Formatos válidos de los documentos electrónicos (según la guía técnica)', 'parcial', 'PDF, JPG, PNG y TIF validados por contenido; los PDF generados no son PDF/A. La lista de formatos de la guía técnica es A VALIDAR.', 'Administración › Archivos'),
            $f(10, '15, 29, 36', 'Acceso de interesados y usuarios con los mecanismos de autenticación oficiales; consulta de sus expedientes', 'implementado', 'El vecino accede con ClaveÚnica a «Mis solicitudes» (estado, etapas, documentos notificados); el personal, con ClaveÚnica y rol.', 'Mis solicitudes'),
            $f(10, '16, 31', 'Consulta de trazabilidad para verificar identificación y modificaciones, e identificar a quien las ejecutó', 'implementado', 'Bitácora de auditoría con filtros y exportación; índice electrónico del expediente con SHA-256; verificación pública de documentos por código.', 'Seguridad › Auditoría · /validar'),
            $f(10, '18', 'Crear el expediente con un identificador único institucional (IUIe)', 'implementado', 'Cada expediente recibe un código y un identificador único con el código de institución, configurable en Administración › Codificación.', 'Expediente'),
            $f(10, '19-20', 'Esquema del expediente: documentos, índice electrónico, metadatos, trazabilidad, registro de accesos, registro de actos y de otras acciones; fidelidad y orden sucesivo', 'implementado', 'El expediente muestra documentos, índice con hash, metadatos, comentarios y derivaciones, comunicaciones, notificaciones y vínculos en orden; los accesos y acciones quedan en la auditoría.', 'Expediente'),
            $f(10, '21, 25-26', 'Incorporación de documentos: formulario web, documentos digitales y físicos como microformas; expediente híbrido', 'parcial', 'La solicitud web genera un documento (comprobante) con sus anexos; el Archivo municipal registra microformas con acta de certificación y disposición. El expediente híbrido (referencia a papel) se registra como microforma asociada, sin estructura específica de la guía.', 'Solicitudes · Archivo municipal'),
            $f(10, '23', 'Vinculación y anidamiento de expedientes (acumulación de procedimientos)', 'implementado', 'Se pueden asociar expedientes y microformas, con su contenido, quedando registrado quién y por qué.', 'Expediente › Expedientes y microformas asociados'),
            $f(10, '24', 'Datos y documentos interoperados: identificar origen, servicio y autorización del interesado si son sensibles', 'parcial', 'Las consultas PISEE guardan servicio, finalidad, funcionario, RUN consultado y resultado cifrado en el expediente. No guardan aún el código del órgano ni la autorización del interesado; además PISEE termina el 31-12-2026 (ver Decreto 12).', 'Expediente › Consultas a servicios del Estado'),
            $f(10, '27-28', 'Incorporar al expediente las comunicaciones oficiales y las notificaciones', 'implementado', 'Las comunicaciones DocDigital (recibidas y enviadas, con acuse) y las notificaciones con su evidencia quedan en el expediente.', 'Expediente'),
            $f(10, '30', 'Certificado de presentación con firma electrónica: fecha y hora, origen, expediente, órgano, quien presenta y quien recibe', 'parcial', 'El comprobante electrónico incluye fecha y hora (Chile y UTC), origen (formulario web), órgano y su código, identificación de quien presenta, plataforma que recibe y código de verificación con QR; el número de expediente se informa en el seguimiento al tomarse la solicitud. No lleva firma electrónica avanzada institucional.', 'Comprobante de ingreso'),
            $f(10, '33', 'Enlaces persistentes a documentos', 'parcial', 'Enlace público permanente por código de validación (/validar/{código}); las vinculaciones entre expedientes son internas.', '/validar'),
            $f(10, '35', 'Estándares mínimos: control de poderes, roles de acceso a información sensible, copias autorizadas, condiciones del repositorio', 'parcial', 'Roles y permisos por usuario, restricción de expedientes, documentos y archivos sensibles, copias autorizadas con certificado. El control de poderes de apoderados y representantes de personas jurídicas no está implementado.', 'Roles y permisos · Restringidos · Copias'),
            $f(10, '37', 'Registro de accesos a documentos con datos sensibles (usuario, fecha y hora, documentos) y perfiles que impidan ver datos de otras partes', 'implementado', 'Cada apertura de un documento sensible o restringido queda registrada y el panel «Datos personales (DPO)» y «Accesos a restringidos» los listan con quién, cuándo y qué.', 'Datos personales (DPO) · Accesos a restringidos'),
            $f(10, '38-40', 'Guías técnicas, gradualidad y revisión de la norma cada 2 años', 'institucional', 'Las guías técnicas las dicta la División de Gobierno Digital; la revisión periódica de esta matriz es parte del Ciclo de Calidad del municipio.', ''),

            // ---- Decreto 11: calidad y funcionamiento
            $f(11, '3, 11', 'Catálogo de plataformas electrónicas con línea de base, actualizado al cerrar cada plan', 'implementado', 'Catálogo con las plataformas del municipio (el SGD y sus integraciones), línea de base, prioridad y vida útil estimada.', 'Cumplimiento normativo › Catálogo de plataformas'),
            $f(11, '4-6, 8, 10', 'Plan de Mejora Continua: iniciativas, objetivos, responsables, plazos, costos, subcaracterísticas (mínimo 4), métricas y umbrales, riesgos', 'implementado', 'Registro de iniciativas por plataforma con etapa del ciclo (1 a 5), responsable, plazo, costo, subcaracterística, métrica y umbrales (exitoso, aceptable, insuficiente), riesgos y vida útil.', 'Cumplimiento normativo › Plan de mejora continua'),
            $f(11, '9', 'Evaluación de la calidad e Informe de Evaluación', 'parcial', 'Cada iniciativa registra su resultado medido y el análisis; el informe se arma con esos datos (vista imprimible). El diseño de la evaluación y su informe formal los redacta el municipio.', 'Cumplimiento normativo'),
            $f(11, '7, 12-14', 'Formulación presupuestaria y proyectos EvalTIC', 'institucional', 'Se hace en la plataforma de la Dirección de Presupuestos; el plan registra el costo estimado de cada iniciativa para apoyar la formulación.', ''),
            $f(11, '15-16', 'Coordinador(a) de Transformación Digital designado y reportando avances', 'parcial', 'El sistema registra la designación (decreto, titular y subrogante). Informar el cambio a la División de Gobierno Digital y reportar avances son tareas de la persona designada.', 'Cumplimiento normativo › Coordinador(a)'),

            // ---- Decreto 12: interoperabilidad
            $f(12, '3-6', 'Integrarse a la red de interoperabilidad mediante un nodo (propio o de Gobierno Digital)', 'pendiente', 'El sistema no incluye un nodo de interoperabilidad ni se conecta a los servicios centralizados. Debe solicitarse el nodo a la Secretaría de Gobierno Digital e integrarlo con el sistema.', ''),
            $f(12, 'segundo transitorio', 'Fin de la versión anterior de PISEE el 31-12-2026: desde esa fecha la red de interoperabilidad es la única vía', 'pendiente', 'El módulo PISEE del sistema corresponde a la versión anterior y deja de ser utilizable en esa fecha. El panel de integraciones muestra un aviso con los días que faltan.', 'APIs e integraciones › PISEE'),
            $f(12, '8, 12 bis, 13', 'Catálogo de Servicios, términos y condiciones, Gestor de Solicitudes (15 días hábiles)', 'externo', 'Los provee la Secretaría de Gobierno Digital. El municipio debe publicar sus servicios y términos solo si provee servicios; hoy el sistema solo consume.', ''),
            $f(12, '9', 'Registro de trazabilidad de cada transacción (plataforma, órgano, funcionario, procedimiento, plazo, UTC, RUN y autorización si es sensible, tipo de respuesta)', 'parcial', 'Las consultas PISEE guardan funcionario, RUN consultado, finalidad, estado, error y fecha. Faltan el código del órgano destinatario, el procedimiento, el plazo y la hora en UTC dentro del propio registro, y el envío al registro centralizado.', 'Expediente › Consultas a servicios del Estado'),
            $f(12, '14-15', 'Gestor de Autorizaciones y requisitos del consentimiento: libre, específico, informado, esencialmente revocable; negarse no termina el procedimiento', 'parcial', 'La autorización para interoperar datos sensibles es del Gestor de Autorizaciones (externo, vía ClaveÚnica). El consentimiento propio del formulario cumple los mismos principios: es libre (si no consiente, igual se tramita), específico e informado, y la persona puede cambiarlo (revocarlo) después, sin efecto retroactivo.', 'Formulario de solicitud · Mis solicitudes'),
            $f(12, '17', 'Gestor de Códigos del Estado', 'parcial', 'Cliente de la API del Codificador (listado, detalle, jerarquía) y sincronización de instituciones; el contrato exacto es A VALIDAR.', 'Administración › Codificador'),
            $f(12, '18', 'Catálogo de Procedimientos Administrativos y Trámites: nombre, ley de origen, código, interoperabilidad, Gestor de Autorizaciones, contacto', 'parcial', 'Cada trámite tiene nombre, código CPAT, dirección responsable y descripción. No registra la ley de origen, si requiere interoperar ni el contacto para el interesado como campos propios.', 'Trámites y servicios'),
            $f(12, '20', 'Clasificación de datos (personales, sensibles, estadísticos, secretos, reservados)', 'parcial', 'Los documentos se marcan como sensibles y con nivel de acceso (público o reservado) y los expedientes pueden restringirse; no hay una clasificación completa por tipo de dato.', 'Documento › Nivel de acceso'),
            $f(12, '29', 'Revisión de la norma cada 2 años', 'institucional', 'Esta matriz se revisa con cada versión de las normas.', ''),
        ];
    }

    /**
     * Cantidad de filas por estado.
     *
     * @return array<string,int>
     */
    public static function resumen(): array
    {
        $r = array_fill_keys(array_keys(self::ESTADOS), 0);
        foreach (self::matriz() as $f) {
            $r[$f['estado']]++;
        }

        return $r;
    }
}
