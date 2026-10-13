-- ============================================================================
-- SGD Municipalidad de Santo Domingo - Esquema (MySQL 8 / MariaDB 10.6+, InnoDB, utf8mb4)
-- GENERADO desde las migraciones con: php scripts/generate-schema-sql.php > database/sql/01_schema.sql
-- Importar en phpMyAdmin (pestaña Importar) sobre una base de datos vacía.
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

create table `sessions` (`id` varchar(64) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` varchar(255) null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `sessions` add index `sessions_user_id_index`(`user_id`);

alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);

create table `unidades` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(30) not null, `nombre` varchar(150) not null, `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `unidades` add unique `unidades_codigo_unique`(`codigo`);

create table `usuarios` (`id` bigint unsigned not null auto_increment primary key, `run` varchar(12) not null, `nombres` varchar(120) not null, `apellidos` varchar(120) not null, `correo` varchar(190) not null, `cargo` varchar(120) null, `rol` varchar(20) not null, `unidad_id` bigint unsigned null, `puede_firmar` tinyint(1) not null default '0', `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `usuarios` add constraint `usuarios_unidad_id_foreign` foreign key (`unidad_id`) references `unidades` (`id`);

alter table `usuarios` add unique `usuarios_run_unique`(`run`);

create table `tramites` (`id` bigint unsigned not null auto_increment primary key, `codigo_interno` varchar(40) not null, `nombre` varchar(190) not null, `descripcion` text null, `codigo_cpat` varchar(30) null, `plazo_dias` smallint unsigned not null default '20', `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `tramites` add unique `tramites_codigo_interno_unique`(`codigo_interno`);

create table `tramite_unidad` (`tramite_id` bigint unsigned not null, `unidad_id` bigint unsigned not null, primary key (`tramite_id`, `unidad_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `tramite_unidad` add constraint `tramite_unidad_tramite_id_foreign` foreign key (`tramite_id`) references `tramites` (`id`);

alter table `tramite_unidad` add constraint `tramite_unidad_unidad_id_foreign` foreign key (`unidad_id`) references `unidades` (`id`);

create table `tramite_anexos` (`id` bigint unsigned not null auto_increment primary key, `tramite_id` bigint unsigned not null, `nombre` varchar(190) not null, `obligatorio` tinyint(1) not null default '1', `activo` tinyint(1) not null default '1') default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `tramite_anexos` add constraint `tramite_anexos_tramite_id_foreign` foreign key (`tramite_id`) references `tramites` (`id`);

create table `tipos_documento` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(30) not null, `nombre` varchar(120) not null, `prefijo` varchar(20) not null, `firma_alcalde` tinyint(1) not null default '0', `firmas_agil` tinyint unsigned not null default '1', `firmas_clasico` tinyint unsigned not null default '2', `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `tipos_documento` add unique `tipos_documento_codigo_unique`(`codigo`);

create table `plantillas` (`id` bigint unsigned not null auto_increment primary key, `tipo_documento_id` bigint unsigned not null, `nombre` varchar(150) not null, `contenido_html` longtext not null, `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `plantillas` add constraint `plantillas_tipo_documento_id_foreign` foreign key (`tipo_documento_id`) references `tipos_documento` (`id`);

create table `delegaciones_firma` (`id` bigint unsigned not null auto_increment primary key, `tipo_documento_id` bigint unsigned not null, `delegado_id` bigint unsigned not null, `decreto_referencia` varchar(120) not null, `vigente_desde` date not null, `vigente_hasta` date not null, `creado_por` bigint unsigned not null, `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `delegaciones_firma` add constraint `delegaciones_firma_tipo_documento_id_foreign` foreign key (`tipo_documento_id`) references `tipos_documento` (`id`);

alter table `delegaciones_firma` add constraint `delegaciones_firma_delegado_id_foreign` foreign key (`delegado_id`) references `usuarios` (`id`);

alter table `delegaciones_firma` add constraint `delegaciones_firma_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

create table `solicitudes` (`id` bigint unsigned not null auto_increment primary key, `folio` varchar(30) not null, `run` varchar(12) not null, `nombres` varchar(120) not null, `apellidos` varchar(120) not null, `correo` varchar(190) not null, `tramite_id` bigint unsigned not null, `detalle` text not null, `canal_notificacion` varchar(15) not null, `estado` varchar(15) not null default 'ingresada', `expediente_id` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `solicitudes` add constraint `solicitudes_tramite_id_foreign` foreign key (`tramite_id`) references `tramites` (`id`);

alter table `solicitudes` add index `solicitudes_tramite_id_estado_index`(`tramite_id`, `estado`);

alter table `solicitudes` add unique `solicitudes_folio_unique`(`folio`);

alter table `solicitudes` add index `solicitudes_run_index`(`run`);

alter table `solicitudes` add index `solicitudes_expediente_id_index`(`expediente_id`);

create table `expedientes` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(40) not null, `solicitud_id` bigint unsigned null, `unidad_id` bigint unsigned not null, `responsable_id` bigint unsigned null, `estado` varchar(15) not null default 'abierto', `metadatos` json not null, `motivo_reapertura` text null, `cerrado_en` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `expedientes` add constraint `expedientes_solicitud_id_foreign` foreign key (`solicitud_id`) references `solicitudes` (`id`);

alter table `expedientes` add constraint `expedientes_unidad_id_foreign` foreign key (`unidad_id`) references `unidades` (`id`);

alter table `expedientes` add constraint `expedientes_responsable_id_foreign` foreign key (`responsable_id`) references `usuarios` (`id`);

alter table `expedientes` add unique `expedientes_codigo_unique`(`codigo`);

alter table `expedientes` add unique `expedientes_solicitud_id_unique`(`solicitud_id`);

create table `devoluciones` (`id` bigint unsigned not null auto_increment primary key, `solicitud_id` bigint unsigned not null, `creado_por` bigint unsigned not null, `observacion` text not null, `plazo_dias` tinyint unsigned not null, `vence_en` timestamp not null, `respuesta` text null, `respondida_en` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `devoluciones` add constraint `devoluciones_solicitud_id_foreign` foreign key (`solicitud_id`) references `solicitudes` (`id`);

alter table `devoluciones` add constraint `devoluciones_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

create table `documentos` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `tipo_documento_id` bigint unsigned not null, `titulo` varchar(190) not null, `resumen` text null, `palabras_clave` varchar(255) null, `nivel_acceso` varchar(15) not null default 'reservado', `sensible` tinyint(1) not null default '0', `estado` varchar(15) not null default 'borrador', `flujo` varchar(10) not null default 'agil', `version_actual` int unsigned not null default '1', `html` longtext not null, `numero` int unsigned null, `anio` smallint unsigned null, `fecha_firma` timestamp null, `codigo_validacion` varchar(20) null, `pdf_ruta` varchar(80) null, `pdf_sha256` varchar(64) null, `metadatos` json null, `creado_por` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `documentos` add constraint `documentos_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `documentos` add constraint `documentos_tipo_documento_id_foreign` foreign key (`tipo_documento_id`) references `tipos_documento` (`id`);

alter table `documentos` add constraint `documentos_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `documentos` add unique `documentos_tipo_documento_id_anio_numero_unique`(`tipo_documento_id`, `anio`, `numero`);

alter table `documentos` add index `documentos_expediente_id_estado_index`(`expediente_id`, `estado`);

alter table `documentos` add unique `documentos_codigo_validacion_unique`(`codigo_validacion`);

create table `documento_versiones` (`id` bigint unsigned not null auto_increment primary key, `documento_id` bigint unsigned not null, `version` int unsigned not null, `html` longtext not null, `html_sha256` varchar(64) not null, `pdf_ruta` varchar(80) null, `pdf_sha256` varchar(64) null, `creado_por` bigint unsigned not null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `documento_versiones` add constraint `documento_versiones_documento_id_foreign` foreign key (`documento_id`) references `documentos` (`id`);

alter table `documento_versiones` add constraint `documento_versiones_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `documento_versiones` add unique `documento_versiones_documento_id_version_unique`(`documento_id`, `version`);

create table `documento_pasos` (`id` bigint unsigned not null auto_increment primary key, `documento_id` bigint unsigned not null, `orden` tinyint unsigned not null, `tipo` varchar(10) not null, `usuario_id` bigint unsigned not null, `estado` varchar(12) not null default 'pendiente', `simulada` tinyint(1) not null default '0', `checksum_original` varchar(64) null, `comentario` text null, `completado_en` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `documento_pasos` add constraint `documento_pasos_documento_id_foreign` foreign key (`documento_id`) references `documentos` (`id`);

alter table `documento_pasos` add constraint `documento_pasos_usuario_id_foreign` foreign key (`usuario_id`) references `usuarios` (`id`);

alter table `documento_pasos` add unique `documento_pasos_documento_id_orden_unique`(`documento_id`, `orden`);

create table `correlativos` (`tipo_documento_id` bigint unsigned not null, `anio` smallint unsigned not null, `ultimo` int unsigned not null default '0', primary key (`tipo_documento_id`, `anio`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `correlativos` add constraint `correlativos_tipo_documento_id_foreign` foreign key (`tipo_documento_id`) references `tipos_documento` (`id`);

create table `archivos` (`id` bigint unsigned not null auto_increment primary key, `solicitud_id` bigint unsigned null, `expediente_id` bigint unsigned null, `documento_id` bigint unsigned null, `ambito` varchar(15) not null, `nombre_original` varchar(190) not null, `mime` varchar(60) not null, `tamano` int unsigned not null, `sha256` varchar(64) not null, `ruta` varchar(80) not null, `titulo` varchar(190) null, `resumen` text null, `palabras_clave` varchar(255) null, `nivel_acceso` varchar(15) not null default 'reservado', `sensible` tinyint(1) not null default '0', `subido_por` varchar(12) not null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `archivos` add constraint `archivos_solicitud_id_foreign` foreign key (`solicitud_id`) references `solicitudes` (`id`);

alter table `archivos` add constraint `archivos_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `archivos` add constraint `archivos_documento_id_foreign` foreign key (`documento_id`) references `documentos` (`id`);

alter table `archivos` add unique `archivos_ruta_unique`(`ruta`);

create table `comentarios` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `documento_id` bigint unsigned null, `usuario_id` bigint unsigned not null, `tipo` varchar(12) not null default 'comentario', `texto` text not null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `comentarios` add constraint `comentarios_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `comentarios` add constraint `comentarios_documento_id_foreign` foreign key (`documento_id`) references `documentos` (`id`);

alter table `comentarios` add constraint `comentarios_usuario_id_foreign` foreign key (`usuario_id`) references `usuarios` (`id`);

create table `derivaciones` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `de_usuario_id` bigint unsigned not null, `a_usuario_id` bigint unsigned not null, `motivo` text not null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `derivaciones` add constraint `derivaciones_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `derivaciones` add constraint `derivaciones_de_usuario_id_foreign` foreign key (`de_usuario_id`) references `usuarios` (`id`);

alter table `derivaciones` add constraint `derivaciones_a_usuario_id_foreign` foreign key (`a_usuario_id`) references `usuarios` (`id`);

create table `notificaciones` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `documento_id` bigint unsigned null, `solicitud_id` bigint unsigned not null, `canal` varchar(15) not null, `estado` varchar(12) not null default 'pendiente', `destinatario_run` varchar(12) not null, `destinatario_correo` varchar(190) null, `evidencia_tipo` varchar(30) null, `evidencia` varchar(255) null, `evidencia_en` timestamp null, `intentos` tinyint unsigned not null default '0', `ultimo_error` varchar(120) null, `creado_por` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `notificaciones` add constraint `notificaciones_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `notificaciones` add constraint `notificaciones_documento_id_foreign` foreign key (`documento_id`) references `documentos` (`id`);

alter table `notificaciones` add constraint `notificaciones_solicitud_id_foreign` foreign key (`solicitud_id`) references `solicitudes` (`id`);

alter table `notificaciones` add constraint `notificaciones_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

create table `audit_log` (`id` bigint unsigned not null auto_increment primary key, `ts` timestamp(6) not null default CURRENT_TIMESTAMP(6), `actor_run` varchar(12) null, `actor_rol` varchar(20) null, `accion` varchar(60) not null, `entidad` varchar(40) null, `entidad_id` varchar(40) null, `ip` varchar(45) null, `sensible` tinyint(1) not null default '0', `detalle` text null, `prev_hmac` char(64) not null, `hmac` char(64) not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `audit_log` add index `audit_log_accion_index`(`accion`);

create table `incidentes` (`id` bigint unsigned not null auto_increment primary key, `titulo` varchar(190) not null, `descripcion` text not null, `severidad` varchar(10) not null, `estado` varchar(12) not null default 'abierto', `detectado_en` timestamp not null, `reportable_csirt` tinyint(1) not null default '0', `alerta_temprana_en` timestamp null, `segundo_reporte_en` timestamp null, `informe_final_en` timestamp null, `creado_por` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `incidentes` add constraint `incidentes_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

create table `instituciones` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(40) not null, `nombre` varchar(190) not null, `tipo` varchar(60) null, `codigo_padre` varchar(40) null, `activo` tinyint(1) not null default '1', `origen` varchar(10) not null default 'manual', `sincronizado_en` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `instituciones` add unique `instituciones_codigo_unique`(`codigo`);

create table `procesos_codificador` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(40) not null, `nombre` varchar(190) not null, `institucion_codigo` varchar(40) null, `activo` tinyint(1) not null default '1', `origen` varchar(10) not null default 'manual', `sincronizado_en` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `procesos_codificador` add unique `procesos_codificador_codigo_unique`(`codigo`);

alter table `tramites` add `proceso_codigo` varchar(40) null;

alter table `documentos` add `pdf_firmado_ruta` varchar(80) null;

alter table `documentos` add `pdf_firmado_sha256` varchar(64) null;

create table `pisee_servicios` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(40) not null, `nombre` varchar(190) not null, `institucion_codigo` varchar(40) null, `ruta` varchar(200) not null, `metodo` varchar(4) not null default 'GET', `param_run` varchar(40) null, `descripcion` text null, `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pisee_servicios` add unique `pisee_servicios_codigo_unique`(`codigo`);

create table `pisee_consultas` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `servicio_id` bigint unsigned not null, `usuario_id` bigint unsigned not null, `run_consultado` varchar(12) not null, `finalidad` varchar(255) not null, `estado` varchar(10) not null, `http_status` smallint unsigned null, `error` varchar(120) null, `archivo_id` bigint unsigned null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pisee_consultas` add constraint `pisee_consultas_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `pisee_consultas` add constraint `pisee_consultas_servicio_id_foreign` foreign key (`servicio_id`) references `pisee_servicios` (`id`);

alter table `pisee_consultas` add constraint `pisee_consultas_usuario_id_foreign` foreign key (`usuario_id`) references `usuarios` (`id`);

alter table `expedientes` add `origen` varchar(12) not null default 'solicitud';

alter table `expedientes` add `comunicacion_id` bigint unsigned null;

alter table `expedientes` add index `expedientes_comunicacion_id_index`(`comunicacion_id`);

create table `comunicaciones_oficiales` (`id` bigint unsigned not null auto_increment primary key, `direccion` varchar(10) not null, `expediente_id` bigint unsigned null, `documento_id` bigint unsigned null, `institucion_codigo` varchar(40) null, `institucion_nombre` varchar(190) null, `asunto` varchar(190) not null, `estado` varchar(10) not null default 'pendiente', `evidencia_tipo` varchar(30) null, `evidencia` varchar(255) null, `evidencia_en` timestamp null, `intentos` tinyint unsigned not null default '0', `ultimo_error` varchar(120) null, `archivo_id` bigint unsigned null, `responde_a_id` bigint unsigned null, `acuse_estado` varchar(10) null, `acuse_tipo` varchar(10) null, `acuse_evidencia` varchar(255) null, `acuse_en` timestamp null, `acuse_por_id` bigint unsigned null, `unidad_id` bigint unsigned null, `creado_por` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `comunicaciones_oficiales` add constraint `comunicaciones_oficiales_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `comunicaciones_oficiales` add constraint `comunicaciones_oficiales_documento_id_foreign` foreign key (`documento_id`) references `documentos` (`id`);

alter table `comunicaciones_oficiales` add constraint `comunicaciones_oficiales_acuse_por_id_foreign` foreign key (`acuse_por_id`) references `usuarios` (`id`);

alter table `comunicaciones_oficiales` add constraint `comunicaciones_oficiales_unidad_id_foreign` foreign key (`unidad_id`) references `unidades` (`id`);

alter table `comunicaciones_oficiales` add constraint `comunicaciones_oficiales_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `comunicaciones_oficiales` add index `comunicaciones_oficiales_direccion_estado_index`(`direccion`, `estado`);

alter table `comunicaciones_oficiales` add unique `comunic_dir_evid_unique`(`direccion`, `evidencia_tipo`, `evidencia`);

alter table `comunicaciones_oficiales` add index `comunicaciones_oficiales_responde_a_id_index`(`responde_a_id`);

create table `config_sitio` (`id` bigint unsigned not null auto_increment primary key, `clave` varchar(40) not null, `valor` longtext not null, `actualizado_por` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `config_sitio` add constraint `config_sitio_actualizado_por_foreign` foreign key (`actualizado_por`) references `usuarios` (`id`);

alter table `config_sitio` add unique `config_sitio_clave_unique`(`clave`);

create table `menu_items` (`id` bigint unsigned not null auto_increment primary key, `area` varchar(10) not null, `clave` varchar(40) null, `etiqueta` varchar(60) not null, `url` varchar(255) null, `nueva_pestana` tinyint(1) not null default '0', `orden` smallint unsigned not null default '100', `activo` tinyint(1) not null default '1', `roles` varchar(120) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `menu_items` add unique `menu_items_clave_unique`(`clave`);

create table `api_config` (`id` bigint unsigned not null auto_increment primary key, `servicio` varchar(30) not null, `activo` tinyint(1) not null default '0', `valores` longtext not null, `secretos` longtext null, `actualizado_por` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `api_config` add constraint `api_config_actualizado_por_foreign` foreign key (`actualizado_por`) references `usuarios` (`id`);

alter table `api_config` add unique `api_config_servicio_unique`(`servicio`);

create table `roles` (`id` bigint unsigned not null auto_increment primary key, `clave` varchar(20) not null, `nombre` varchar(80) not null, `descripcion` varchar(255) null, `rol_base` varchar(20) not null, `sistema` tinyint(1) not null default '0', `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `roles` add unique `roles_clave_unique`(`clave`);

create table `rol_permisos` (`rol_id` bigint unsigned not null, `permiso` varchar(60) not null, primary key (`rol_id`, `permiso`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `rol_permisos` add constraint `rol_permisos_rol_id_foreign` foreign key (`rol_id`) references `roles` (`id`);

alter table `usuarios` add `ultimo_acceso` timestamp null;

create table `firma_estilos` (`id` bigint unsigned not null auto_increment primary key, `nombre` varchar(80) not null, `texto_encabezado` varchar(80) not null default 'Firmado electrónicamente por', `mostrar_nombre` tinyint(1) not null default '1', `mostrar_cargo` tinyint(1) not null default '1', `mostrar_run` tinyint(1) not null default '0', `mostrar_fecha` tinyint(1) not null default '1', `mostrar_codigo` tinyint(1) not null default '1', `color_borde` varchar(7) not null default '#006fb3', `color_fondo` varchar(7) not null default '#ffffff', `color_texto` varchar(7) not null default '#111111', `tamano_fuente` tinyint unsigned not null default '8', `ancho_mm` smallint unsigned not null default '70', `alto_mm` smallint unsigned not null default '28', `borde_estilo` varchar(8) not null default 'solid', `alineacion_texto` varchar(8) not null default 'left', `defecto` tinyint(1) not null default '0', `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `firma_estilos` add unique `firma_estilos_nombre_unique`(`nombre`);

create table `firma_posiciones` (`id` bigint unsigned not null auto_increment primary key, `nombre` varchar(80) not null, `tipo_documento_id` bigint unsigned null, `orden_firma` tinyint unsigned not null default '1', `pagina` varchar(8) not null default 'ultima', `alineacion` varchar(10) not null default 'izquierda', `margen_superior_mm` smallint unsigned not null default '12', `x_mm` smallint unsigned null, `y_mm` smallint unsigned null, `estilo_id` bigint unsigned null, `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `firma_posiciones` add constraint `firma_posiciones_tipo_documento_id_foreign` foreign key (`tipo_documento_id`) references `tipos_documento` (`id`);

alter table `firma_posiciones` add constraint `firma_posiciones_estilo_id_foreign` foreign key (`estilo_id`) references `firma_estilos` (`id`);

alter table `tipos_documento` add `folio_formato` varchar(80) not null default '{PREFIJO} N° {N}/{AAAA}';

alter table `tipos_documento` add `folio_digitos` tinyint unsigned not null default '2';

alter table `tipos_documento` add `folio_reinicio_anual` tinyint(1) not null default '1';

alter table `tipos_documento` add `folio_inicio` int unsigned not null default '1';

alter table `documentos` add `folio` varchar(80) null;

alter table `documentos` add unique `documentos_tipo_documento_id_folio_unique`(`tipo_documento_id`, `folio`);

alter table `archivos` add `almacen` varchar(10) not null default 'local';

create table `repositorio_carpetas` (`id` bigint unsigned not null auto_increment primary key, `clave` varchar(40) not null, `drive_folder_id` varchar(100) not null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `repositorio_carpetas` add unique `repositorio_carpetas_clave_unique`(`clave`);

create table `repositorio_reubicaciones` (`id` bigint unsigned not null auto_increment primary key, `ruta_origen` varchar(80) not null, `ruta_destino` varchar(80) not null, `md5` char(32) not null, `migrado_en` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `repositorio_reubicaciones` add unique `repositorio_reubicaciones_ruta_origen_unique`(`ruta_origen`);

insert into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('admin', 'Administrador', 'Gestiona mantenedores, seguridad y configuración. No lee expedientes.', 'admin', 1, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

insert into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('funcionario', 'Funcionario', 'Gestiona solicitudes y expedientes de su unidad.', 'funcionario', 1, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

insert into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('alcalde', 'Alcalde / alcaldesa', 'Firma decretos y crea delegaciones de firma.', 'alcalde', 1, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

insert into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('secretario', 'Secretario(a) municipal', 'Visa y firma; unidad receptora de comunicaciones oficiales.', 'secretario', 1, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

insert into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('auditor', 'Auditor', 'Acceso de solo lectura y verificación de auditoría.', 'auditor', 1, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

insert into `firma_estilos` (`nombre`, `defecto`, `activo`, `created_at`, `updated_at`) values ('Estándar', 1, 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

insert into `firma_posiciones` (`activo`, `alineacion`, `created_at`, `margen_superior_mm`, `nombre`, `orden_firma`, `pagina`, `updated_at`) values (1, 'izquierda', '2026-01-01 00:00:00', 14, 'Primera firma (izquierda)', 1, 'ultima', '2026-01-01 00:00:00'), (1, 'derecha', '2026-01-01 00:00:00', 14, 'Segunda firma (derecha)', 2, 'ultima', '2026-01-01 00:00:00');

alter table `plantillas` add `descripcion` varchar(255) null;

alter table `plantillas` add `alcance` varchar(10) not null default 'todas';

alter table `plantillas` add `version` int unsigned not null default '1';

alter table `plantillas` add `actualizado_por` bigint unsigned null;

alter table `plantillas` add constraint `plantillas_actualizado_por_foreign` foreign key (`actualizado_por`) references `usuarios` (`id`);

create table `plantilla_unidades` (`plantilla_id` bigint unsigned not null, `unidad_id` bigint unsigned not null, primary key (`plantilla_id`, `unidad_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `plantilla_unidades` add constraint `plantilla_unidades_plantilla_id_foreign` foreign key (`plantilla_id`) references `plantillas` (`id`);

alter table `plantilla_unidades` add constraint `plantilla_unidades_unidad_id_foreign` foreign key (`unidad_id`) references `unidades` (`id`);

create table `plantilla_versiones` (`id` bigint unsigned not null auto_increment primary key, `plantilla_id` bigint unsigned not null, `version` int unsigned not null, `nombre` varchar(150) not null, `contenido_html` longtext not null, `sha256` char(64) not null, `creado_por` bigint unsigned null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `plantilla_versiones` add constraint `plantilla_versiones_plantilla_id_foreign` foreign key (`plantilla_id`) references `plantillas` (`id`);

alter table `plantilla_versiones` add constraint `plantilla_versiones_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `plantilla_versiones` add unique `plantilla_versiones_plantilla_id_version_unique`(`plantilla_id`, `version`);

create table `recursos_imagen` (`id` bigint unsigned not null auto_increment primary key, `nombre` varchar(120) not null, `mime` varchar(20) not null, `ancho` smallint unsigned not null, `alto` smallint unsigned not null, `tamano` int unsigned not null, `sha256` char(64) not null, `ruta` varchar(255) not null, `subido_por` bigint unsigned null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `recursos_imagen` add constraint `recursos_imagen_subido_por_foreign` foreign key (`subido_por`) references `usuarios` (`id`);

alter table `recursos_imagen` add index `recursos_imagen_sha256_index`(`sha256`);

select * from `plantillas`;

alter table `archivos` add `firma_estado` varchar(20) null;

alter table `archivos` add `firma_detalle` longtext null;

alter table `archivos` add `firma_analizada_en` timestamp null;

alter table `archivos` add index `archivos_firma_estado_index`(`firma_estado`);

create table `metricas_recursos` (`id` bigint unsigned not null auto_increment primary key, `ts` timestamp not null, `cpu_pct` decimal(5, 1) null, `carga1` decimal(6, 2) null, `mem_usada` bigint unsigned null, `mem_total` bigint unsigned null, `disco_usado` bigint unsigned null, `disco_total` bigint unsigned null, `bd_bytes` bigint unsigned null, `archivos_bytes` bigint unsigned null, `sesiones` int unsigned null, `cola_pendiente` int unsigned null, `cola_fallida` int unsigned null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `metricas_recursos` add index `metricas_recursos_ts_index`(`ts`);

create table `cuentas_prueba` (`id` bigint unsigned not null auto_increment primary key, `run` varchar(12) not null, `tipo` varchar(12) not null, `nombres` varchar(120) not null, `apellidos` varchar(120) not null, `password` varchar(255) not null, `activo` tinyint(1) not null default '1', `ultimo_ingreso` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `cuentas_prueba` add unique `cuentas_prueba_run_unique`(`run`);

create table `conexiones_resumen` (`id` bigint unsigned not null auto_increment primary key, `bucket` datetime not null, `direccion` varchar(10) not null, `clave` varchar(190) not null, `grupo` varchar(20) not null, `hits` int unsigned not null default '0', `errores` int unsigned not null default '0', `ultimo` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `conexiones_resumen` add unique `conexiones_uniq`(`bucket`, `direccion`, `clave`, `grupo`);

alter table `conexiones_resumen` add index `conexiones_resumen_direccion_bucket_index`(`direccion`, `bucket`);

create table `geo_dns_cache` (`host` varchar(190) not null, `ip` varchar(45) null, `pais` varchar(2) null, `resuelto_en` timestamp null, primary key (`host`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `tramites` add `estado` varchar(20) not null default 'borrador';

alter table `tramites` add `unidad_propietaria_id` bigint unsigned null;

alter table `tramites` add constraint `tramites_unidad_propietaria_id_foreign` foreign key (`unidad_propietaria_id`) references `unidades` (`id`);

alter table `tramites` add `instrucciones` text null;

alter table `tramites` add `version` int unsigned not null default '1';

alter table `tramites` add `publicado_en` timestamp null;

alter table `tramites` add `actualizado_por` bigint unsigned null;

alter table `tramites` add constraint `tramites_actualizado_por_foreign` foreign key (`actualizado_por`) references `usuarios` (`id`);

alter table `tramites` add index `tramites_estado_index`(`estado`);

alter table `tramite_anexos` add `descripcion` varchar(255) null;

alter table `tramite_anexos` add `orden` smallint unsigned not null default '0';

create table `tramite_versiones` (`id` bigint unsigned not null auto_increment primary key, `tramite_id` bigint unsigned not null, `version` int unsigned not null, `estado` varchar(20) not null, `evento` varchar(20) not null, `datos` longtext not null, `creado_por` bigint unsigned null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `tramite_versiones` add constraint `tramite_versiones_tramite_id_foreign` foreign key (`tramite_id`) references `tramites` (`id`);

alter table `tramite_versiones` add constraint `tramite_versiones_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `tramite_versiones` add index `tramite_versiones_tramite_id_version_index`(`tramite_id`, `version`);

SET FOREIGN_KEY_CHECKS = 1;
