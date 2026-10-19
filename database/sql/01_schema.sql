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

create table `archivo_microformas` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(20) null, `titulo` varchar(190) not null, `descripcion` text null, `tipo_soporte` varchar(20) not null, `unidad_id` bigint unsigned null, `serie` varchar(120) null, `fecha_desde` date null, `fecha_hasta` date null, `n_documentos` int unsigned not null default '0', `n_imagenes` int unsigned not null default '0', `ubicacion` varchar(190) null, `nivel_acceso` varchar(15) not null default 'reservado', `estado` varchar(20) not null default 'borrador', `expediente_id` bigint unsigned null, `creado_por` bigint unsigned not null, `certificada_por` bigint unsigned null, `certificada_en` timestamp null, `acta_numero` varchar(60) null, `acta_archivo_id` bigint unsigned null, `sha256_conjunto` varchar(64) null, `disposicion` varchar(12) not null default 'conservar', `disposicion_estado` varchar(12) not null default 'no_aplica', `disposicion_motivo` text null, `disposicion_solicitada_por` bigint unsigned null, `disposicion_autorizada_por` bigint unsigned null, `disposicion_acta` varchar(60) null, `disposicion_en` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `archivo_microformas` add constraint `archivo_microformas_unidad_id_foreign` foreign key (`unidad_id`) references `unidades` (`id`);

alter table `archivo_microformas` add constraint `archivo_microformas_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `archivo_microformas` add constraint `archivo_microformas_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `archivo_microformas` add constraint `archivo_microformas_certificada_por_foreign` foreign key (`certificada_por`) references `usuarios` (`id`);

alter table `archivo_microformas` add constraint `archivo_microformas_acta_archivo_id_foreign` foreign key (`acta_archivo_id`) references `archivos` (`id`);

alter table `archivo_microformas` add constraint `archivo_microformas_disposicion_solicitada_por_foreign` foreign key (`disposicion_solicitada_por`) references `usuarios` (`id`);

alter table `archivo_microformas` add constraint `archivo_microformas_disposicion_autorizada_por_foreign` foreign key (`disposicion_autorizada_por`) references `usuarios` (`id`);

alter table `archivo_microformas` add index `archivo_microformas_estado_index`(`estado`);

alter table `archivo_microformas` add index `archivo_microformas_tipo_soporte_index`(`tipo_soporte`);

alter table `archivo_microformas` add unique `archivo_microformas_codigo_unique`(`codigo`);

create table `archivo_microforma_archivos` (`id` bigint unsigned not null auto_increment primary key, `microforma_id` bigint unsigned not null, `archivo_id` bigint unsigned not null, `orden` int unsigned not null default '0') default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `archivo_microforma_archivos` add constraint `archivo_microforma_archivos_microforma_id_foreign` foreign key (`microforma_id`) references `archivo_microformas` (`id`);

alter table `archivo_microforma_archivos` add constraint `archivo_microforma_archivos_archivo_id_foreign` foreign key (`archivo_id`) references `archivos` (`id`);

alter table `archivo_microforma_archivos` add unique `archivo_microforma_archivos_archivo_id_unique`(`archivo_id`);

alter table `archivo_microforma_archivos` add index `archivo_microforma_archivos_microforma_id_orden_index`(`microforma_id`, `orden`);

create table `archivo_microforma_items` (`id` bigint unsigned not null auto_increment primary key, `microforma_id` bigint unsigned not null, `orden` int unsigned not null default '0', `referencia` varchar(120) not null, `descripcion` varchar(255) null, `fojas` varchar(30) null, `posicion` varchar(30) null, `fecha_documento` date null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `archivo_microforma_items` add constraint `archivo_microforma_items_microforma_id_foreign` foreign key (`microforma_id`) references `archivo_microformas` (`id`);

alter table `archivo_microforma_items` add index `archivo_microforma_items_microforma_id_orden_index`(`microforma_id`, `orden`);

create table `archivo_eventos` (`id` bigint unsigned not null auto_increment primary key, `microforma_id` bigint unsigned not null, `tipo` varchar(30) not null, `detalle` text null, `usuario_id` bigint unsigned null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `archivo_eventos` add constraint `archivo_eventos_microforma_id_foreign` foreign key (`microforma_id`) references `archivo_microformas` (`id`);

alter table `archivo_eventos` add constraint `archivo_eventos_usuario_id_foreign` foreign key (`usuario_id`) references `usuarios` (`id`);

alter table `archivo_eventos` add index `archivo_eventos_microforma_id_id_index`(`microforma_id`, `id`);

insert ignore into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('autoridad', 'Autoridad', 'Consulta el seguimiento de las solicitudes: etapa, estado, quién la tiene y si terminó. Solo lectura; no ve documentos.', 'autoridad', 1, 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00');

insert ignore into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('oirs', 'OIRS', 'Visualiza solicitudes, su estado, responsables y plazos vencidos o por vencer. Solo lectura; no ve documentos.', 'oirs', 1, 1, '2026-10-01 00:00:00', '2026-10-01 00:00:00');

alter table `tramites` add `es_transparencia` tinyint(1) not null default '0';

alter table `tramites` add `plazo_habiles` tinyint(1) not null default '0';

alter table `solicitudes` add `prorroga_dias` tinyint unsigned null;

alter table `solicitudes` add `prorroga_motivo` text null;

alter table `solicitudes` add `prorrogada_en` timestamp null;

alter table `solicitudes` add `prorrogada_por` bigint unsigned null;

create table `transparencia_requerimientos` (`id` bigint unsigned not null auto_increment primary key, `solicitud_id` bigint unsigned not null, `unidad_id` bigint unsigned not null, `instruccion` text not null, `plazo_dias` tinyint unsigned not null, `vence_en` timestamp not null, `estado` varchar(12) not null default 'pendiente', `respuesta` text null, `respondido_por` bigint unsigned null, `respondido_en` timestamp null, `creado_por` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `transparencia_requerimientos` add constraint `transparencia_requerimientos_solicitud_id_foreign` foreign key (`solicitud_id`) references `solicitudes` (`id`);

alter table `transparencia_requerimientos` add constraint `transparencia_requerimientos_unidad_id_foreign` foreign key (`unidad_id`) references `unidades` (`id`);

alter table `transparencia_requerimientos` add constraint `transparencia_requerimientos_respondido_por_foreign` foreign key (`respondido_por`) references `usuarios` (`id`);

alter table `transparencia_requerimientos` add constraint `transparencia_requerimientos_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `transparencia_requerimientos` add index `transparencia_requerimientos_unidad_id_estado_index`(`unidad_id`, `estado`);

alter table `transparencia_requerimientos` add index `transparencia_requerimientos_solicitud_id_index`(`solicitud_id`);

create table `transparencia_derivaciones` (`id` bigint unsigned not null auto_increment primary key, `solicitud_id` bigint unsigned not null, `organo` varchar(190) not null, `motivo` text not null, `fecha` date not null, `canal` varchar(15) not null, `referencia` varchar(120) null, `creado_por` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `transparencia_derivaciones` add constraint `transparencia_derivaciones_solicitud_id_foreign` foreign key (`solicitud_id`) references `solicitudes` (`id`);

alter table `transparencia_derivaciones` add constraint `transparencia_derivaciones_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `transparencia_derivaciones` add index `transparencia_derivaciones_solicitud_id_index`(`solicitud_id`);

create table `transparencia_feriados` (`id` bigint unsigned not null auto_increment primary key, `fecha` date not null, `nombre` varchar(120) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `transparencia_feriados` add unique `transparencia_feriados_fecha_unique`(`fecha`);

create table `transparencia_activa_items` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(30) not null, `nombre` varchar(190) not null, `unidad_id` bigint unsigned null, `dia_limite` tinyint unsigned not null default '10', `url_publicacion` varchar(300) null, `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `transparencia_activa_items` add constraint `transparencia_activa_items_unidad_id_foreign` foreign key (`unidad_id`) references `unidades` (`id`);

alter table `transparencia_activa_items` add unique `transparencia_activa_items_codigo_unique`(`codigo`);

create table `transparencia_activa_registros` (`id` bigint unsigned not null auto_increment primary key, `item_id` bigint unsigned not null, `periodo` char(7) not null, `publicado_en` date not null, `url` varchar(300) null, `observacion` varchar(300) null, `registrado_por` bigint unsigned not null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `transparencia_activa_registros` add constraint `transparencia_activa_registros_item_id_foreign` foreign key (`item_id`) references `transparencia_activa_items` (`id`);

alter table `transparencia_activa_registros` add constraint `transparencia_activa_registros_registrado_por_foreign` foreign key (`registrado_por`) references `usuarios` (`id`);

alter table `transparencia_activa_registros` add index `transparencia_activa_registros_item_id_periodo_index`(`item_id`, `periodo`);

insert ignore into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('transparencia', 'Transparencia activa y pasiva', 'Administra las solicitudes de acceso a la información, sus expedientes, requerimientos a las direcciones, plazos, la publicación de transparencia activa y los reportes.', 'transparencia', 1, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('MARCO', 'Marco normativo aplicable', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('ESTRUCTURA', 'Estructura orgánica, facultades y funciones', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('PERSONAL', 'Personal de planta, contrata y honorarios, con sus remuneraciones', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('CONTRATACIONES', 'Contrataciones para el suministro de bienes y servicios', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('TRANSFERENCIAS', 'Transferencias de fondos públicos', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('ACTOS', 'Actos y resoluciones con efectos sobre terceros', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('TRAMITES', 'Trámites y requisitos que debe cumplir el interesado', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('SUBSIDIOS', 'Programas de subsidios y otros beneficios', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('PARTICIPACION', 'Mecanismos de participación ciudadana', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('PRESUPUESTO', 'Presupuesto asignado e informes de ejecución', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('AUDITORIAS', 'Resultados de auditorías', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

insert ignore into `transparencia_activa_items` (`codigo`, `nombre`, `dia_limite`, `activo`, `created_at`, `updated_at`) values ('ENTIDADES', 'Entidades en que tiene participación, representación o intervención', 10, 1, '2026-11-01 00:00:00', '2026-11-01 00:00:00');

alter table `transparencia_feriados` add `tipo` varchar(15) not null default 'nacional';

alter table `transparencia_feriados` add `creado_por` bigint unsigned null;

alter table `tramites` modify `plazo_habiles` tinyint(1) not null default '1';

update `tramites` set `plazo_habiles` = 1;

alter table `plantillas` add `estructura` json null;

alter table `plantilla_versiones` add `estructura` json null;

alter table `documentos` add `plantilla_id` bigint unsigned null;

alter table `expedientes` add `restringido` tinyint(1) not null default '0';

alter table `expedientes` add `restringido_motivo` varchar(300) null;

alter table `expedientes` add `restringido_por` bigint unsigned null;

alter table `expedientes` add `restringido_en` timestamp null;

alter table `archivos` add `restringido` tinyint(1) not null default '0';

alter table `archivos` add `restringido_motivo` varchar(300) null;

alter table `archivos` add `restringido_por` bigint unsigned null;

alter table `archivos` add `restringido_en` timestamp null;

alter table `documentos` add `restringido` tinyint(1) not null default '0';

alter table `documentos` add `restringido_motivo` varchar(300) null;

alter table `documentos` add `restringido_por` bigint unsigned null;

alter table `documentos` add `restringido_en` timestamp null;

create table `acceso_restringido` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `archivo_id` bigint unsigned null, `documento_id` bigint unsigned null, `usuario_id` bigint unsigned not null, `otorgado_por` bigint unsigned not null, `motivo` varchar(300) not null, `vence_en` timestamp null, `revocado_en` timestamp null, `revocado_por` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `acceso_restringido` add constraint `acceso_restringido_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `acceso_restringido` add constraint `acceso_restringido_usuario_id_foreign` foreign key (`usuario_id`) references `usuarios` (`id`);

alter table `acceso_restringido` add constraint `acceso_restringido_otorgado_por_foreign` foreign key (`otorgado_por`) references `usuarios` (`id`);

alter table `acceso_restringido` add index `acceso_restringido_usuario_id_expediente_id_index`(`usuario_id`, `expediente_id`);

alter table `acceso_restringido` add index `acceso_restringido_expediente_id_revocado_en_index`(`expediente_id`, `revocado_en`);

create table `anuncios` (`id` bigint unsigned not null auto_increment primary key, `titulo` varchar(160) not null, `descripcion` text null, `audiencia` varchar(20) not null default 'vecinos', `medio_tipo` varchar(10) null, `medio_mime` varchar(60) null, `medio_ruta` varchar(200) null, `medio_sha256` varchar(64) null, `medio_tamano` bigint unsigned null, `medio_alt` varchar(200) null, `enlace_url` varchar(300) null, `enlace_texto` varchar(60) null, `inicia_en` timestamp null, `termina_en` timestamp null, `activo` tinyint(1) not null default '1', `orden` smallint unsigned not null default '10', `creado_por` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `anuncios` add index `anuncios_activo_audiencia_index`(`activo`, `audiencia`);

create table `solicitud_movimientos` (`id` bigint unsigned not null auto_increment primary key, `solicitud_id` bigint unsigned not null, `expediente_id` bigint unsigned null, `tipo` varchar(24) not null, `descripcion` varchar(240) not null, `unidad_id` bigint unsigned null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `solicitud_movimientos` add constraint `solicitud_movimientos_solicitud_id_foreign` foreign key (`solicitud_id`) references `solicitudes` (`id`);

alter table `solicitud_movimientos` add index `solicitud_movimientos_solicitud_id_id_index`(`solicitud_id`, `id`);

create table `avisos_movimiento` (`id` bigint unsigned not null auto_increment primary key, `movimiento_id` bigint unsigned not null, `canal` varchar(15) not null, `estado` varchar(12) not null default 'pendiente', `evidencia_tipo` varchar(30) null, `evidencia` varchar(255) null, `evidencia_en` timestamp null, `intentos` tinyint unsigned not null default '0', `ultimo_error` varchar(120) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `avisos_movimiento` add constraint `avisos_movimiento_movimiento_id_foreign` foreign key (`movimiento_id`) references `solicitud_movimientos` (`id`);

alter table `avisos_movimiento` add unique `avisos_movimiento_movimiento_id_canal_unique`(`movimiento_id`, `canal`);

create table `respuestas_finales` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `solicitud_id` bigint unsigned not null, `estado` varchar(12) not null default 'instruida', `canal` varchar(15) not null default 'casilla_unica', `motivo_presencial` varchar(300) null, `instruccion` varchar(600) null, `instruida_por` bigint unsigned not null, `despachada_por` bigint unsigned null, `despachada_en` timestamp null, `anulada_motivo` varchar(300) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `respuestas_finales` add constraint `respuestas_finales_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `respuestas_finales` add constraint `respuestas_finales_solicitud_id_foreign` foreign key (`solicitud_id`) references `solicitudes` (`id`);

alter table `respuestas_finales` add constraint `respuestas_finales_instruida_por_foreign` foreign key (`instruida_por`) references `usuarios` (`id`);

alter table `respuestas_finales` add index `respuestas_finales_estado_id_index`(`estado`, `id`);

create table `respuesta_final_items` (`id` bigint unsigned not null auto_increment primary key, `respuesta_id` bigint unsigned not null, `documento_id` bigint unsigned null, `archivo_id` bigint unsigned null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `respuesta_final_items` add constraint `respuesta_final_items_respuesta_id_foreign` foreign key (`respuesta_id`) references `respuestas_finales` (`id`);

alter table `respuesta_final_items` add index `respuesta_final_items_respuesta_id_index`(`respuesta_id`);

alter table `notificaciones` add `archivo_id` bigint unsigned null;

alter table `notificaciones` add `respuesta_id` bigint unsigned null;

insert ignore into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('oficina_partes', 'Oficina de Partes', 'Recibe las respuestas finales instruidas por quienes gestionaron los expedientes y las notifica al solicitante (Casilla Única con copia por correo; excepcionalmente entrega física).', 'funcionario', 1, 1, '2026-12-02 00:00:00', '2026-12-02 00:00:00');

select `id` from `roles` where `clave` = 'oficina_partes' limit 1;

create table `expediente_vinculos` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `tipo` varchar(12) not null, `origen_expediente_id` bigint unsigned null, `microforma_id` bigint unsigned null, `motivo` varchar(300) not null, `creado_por` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `expediente_vinculos` add constraint `expediente_vinculos_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `expediente_vinculos` add constraint `expediente_vinculos_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `expediente_vinculos` add index `expediente_vinculos_expediente_id_tipo_index`(`expediente_id`, `tipo`);

alter table `expediente_vinculos` add index `expediente_vinculos_origen_expediente_id_index`(`origen_expediente_id`);

alter table `archivos` add `vinculo_id` bigint unsigned null;

alter table `archivos` add `origen_descripcion` varchar(200) null;

alter table `archivos` add `origen_sha256` varchar(64) null;

create table `decretos_pago` (`id` bigint unsigned not null auto_increment primary key, `expediente_id` bigint unsigned not null, `compra_expediente_id` bigint unsigned null, `proveedor_nombre` varchar(160) not null, `proveedor_run` varchar(12) null, `concepto` varchar(500) not null, `monto` bigint unsigned not null, `imputacion` varchar(160) null, `orden_compra` varchar(60) null, `factura` varchar(60) null, `fecha` date null, `estado` varchar(10) not null default 'abierto', `origen` varchar(10) null, `documento_id` bigint unsigned null, `archivo_id` bigint unsigned null, `numero_externo` varchar(40) null, `anulado_motivo` varchar(300) null, `creado_por` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `decretos_pago` add constraint `decretos_pago_expediente_id_foreign` foreign key (`expediente_id`) references `expedientes` (`id`);

alter table `decretos_pago` add constraint `decretos_pago_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `decretos_pago` add index `decretos_pago_estado_id_index`(`estado`, `id`);

alter table `decretos_pago` add index `decretos_pago_expediente_id_index`(`expediente_id`);

create table `egresos` (`id` bigint unsigned not null auto_increment primary key, `decreto_pago_id` bigint unsigned not null, `fecha` date not null, `medio_pago` varchar(20) not null, `monto` bigint unsigned not null, `detalle` varchar(1000) not null, `referencia` varchar(80) null, `cuenta` varchar(120) null, `estado` varchar(10) not null default 'abierto', `documento_id` bigint unsigned null, `anulado_motivo` varchar(300) null, `creado_por` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `egresos` add constraint `egresos_decreto_pago_id_foreign` foreign key (`decreto_pago_id`) references `decretos_pago` (`id`);

alter table `egresos` add constraint `egresos_creado_por_foreign` foreign key (`creado_por`) references `usuarios` (`id`);

alter table `egresos` add index `egresos_decreto_pago_id_index`(`decreto_pago_id`);

create table `pago_firmantes` (`id` bigint unsigned not null auto_increment primary key, `documento` varchar(14) not null, `usuario_id` bigint unsigned not null, `cargo` varchar(120) not null, `funcion` varchar(10) not null, `firma_tipo` varchar(10) not null default 'avanzada', `orden` smallint unsigned not null default '1', `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `pago_firmantes` add constraint `pago_firmantes_usuario_id_foreign` foreign key (`usuario_id`) references `usuarios` (`id`);

alter table `pago_firmantes` add index `pago_firmantes_documento_activo_orden_index`(`documento`, `activo`, `orden`);

alter table `documento_pasos` add `firma_permitida` varchar(10) not null default 'avanzada';

alter table `documento_pasos` add `firma_tipo` varchar(10) null;

alter table `tipos_documento` add `permite_firma_simple` tinyint(1) not null default '0';

insert ignore into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('tesorero', 'Tesorero(a) municipal', 'Genera el egreso de los decretos de pago (fecha, medio de pago, monto y detalle) y lo firma con firma simple o avanzada.', 'funcionario', 1, 1, '2026-12-03 00:00:00', '2026-12-03 00:00:00');

select `id` from `roles` where `clave` = 'tesorero' limit 1;

insert ignore into `roles` (`clave`, `nombre`, `descripcion`, `rol_base`, `sistema`, `activo`, `created_at`, `updated_at`) values ('contabilidad', 'Encargado(a) de pagos', 'Carga el expediente de pago y el proceso de compra, genera o carga el decreto de pago y lo envía a visación y firma.', 'funcionario', 1, 1, '2026-12-03 00:00:00', '2026-12-03 00:00:00');

select `id` from `roles` where `clave` = 'contabilidad' limit 1;

alter table `decretos_pago` add `factura_fecha` date null;

alter table `decretos_pago` add index `decretos_pago_proveedor_run_factura_fecha_index`(`proveedor_run`, `factura_fecha`);

create table `areas` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(20) not null, `nombre` varchar(80) not null, `habilitada` tinyint(1) not null default '0', `institucion_codigo` varchar(30) null, `orden` smallint unsigned not null default '10', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `areas` add unique `areas_codigo_unique`(`codigo`);

insert ignore into `areas` (`codigo`, `nombre`, `habilitada`, `orden`, `created_at`, `updated_at`) values ('municipal', 'Área municipal', 1, 1, '2025-12-05 00:00:00', '2025-12-05 00:00:00');

insert ignore into `areas` (`codigo`, `nombre`, `habilitada`, `orden`, `created_at`, `updated_at`) values ('salud', 'Área de salud', 0, 2, '2025-12-05 00:00:00', '2025-12-05 00:00:00');

insert ignore into `areas` (`codigo`, `nombre`, `habilitada`, `orden`, `created_at`, `updated_at`) values ('educacion', 'Área de educación', 0, 3, '2025-12-05 00:00:00', '2025-12-05 00:00:00');

insert ignore into `areas` (`codigo`, `nombre`, `habilitada`, `orden`, `created_at`, `updated_at`) values ('corporacion', 'Corporación municipal', 0, 4, '2025-12-05 00:00:00', '2025-12-05 00:00:00');

create table `usuario_areas` (`usuario_id` bigint unsigned not null, `area_id` bigint unsigned not null, primary key (`usuario_id`, `area_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `usuario_areas` add constraint `usuario_areas_usuario_id_foreign` foreign key (`usuario_id`) references `usuarios` (`id`) on delete cascade;

alter table `usuario_areas` add constraint `usuario_areas_area_id_foreign` foreign key (`area_id`) references `areas` (`id`) on delete cascade;

alter table `unidades` add `area_id` bigint unsigned null;

alter table `expedientes` add `area_id` bigint unsigned null;

alter table `usuarios` add `totp_secreto` text null;

alter table `usuarios` add `totp_confirmado_en` timestamp null;

alter table `usuarios` add `totp_ultimo_paso` bigint unsigned null;

alter table `usuarios` add `totp_respaldo` text null;

create table `archivo_versiones` (`id` bigint unsigned not null auto_increment primary key, `archivo_id` bigint unsigned not null, `numero` int unsigned not null, `nombre_original` varchar(190) not null, `mime` varchar(60) not null, `tamano` int unsigned not null, `sha256` varchar(64) not null, `ruta` varchar(80) not null, `motivo` text null, `resumen_cambios` text null, `creado_por` bigint unsigned null, `autor_nombre` varchar(160) null, `autor_run` varchar(12) null, `firma_estado` varchar(30) null, `firma_detalle` text null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `archivo_versiones` add constraint `archivo_versiones_archivo_id_foreign` foreign key (`archivo_id`) references `archivos` (`id`);

alter table `archivo_versiones` add unique `archivo_versiones_archivo_id_numero_unique`(`archivo_id`, `numero`);

alter table `archivos` add `version` int unsigned not null default '1';

create table `documento_version_eventos` (`id` bigint unsigned not null auto_increment primary key, `documento_id` bigint unsigned not null, `version` int unsigned not null, `evento` varchar(12) not null, `usuario_id` bigint unsigned not null, `detalle` varchar(300) null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `documento_version_eventos` add constraint `documento_version_eventos_documento_id_foreign` foreign key (`documento_id`) references `documentos` (`id`);

alter table `documento_version_eventos` add constraint `documento_version_eventos_usuario_id_foreign` foreign key (`usuario_id`) references `usuarios` (`id`);

alter table `documento_version_eventos` add index `documento_version_eventos_documento_id_version_index`(`documento_id`, `version`);

alter table `documentos` add `identificador` varchar(120) null;

alter table `instituciones` add `docdigital_entidad_id` bigint unsigned null;

alter table `instituciones` add index `instituciones_docdigital_entidad_id_index`(`docdigital_entidad_id`);

create table `comunicacion_anexos` (`id` bigint unsigned not null auto_increment primary key, `comunicacion_id` bigint unsigned not null, `archivo_id` bigint unsigned null, `nombre` varchar(190) not null, `id_externo` varchar(100) null, `estado` varchar(10) not null, `motivo` varchar(190) null, `tamano` int unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `comunicacion_anexos` add constraint `comunicacion_anexos_comunicacion_id_foreign` foreign key (`comunicacion_id`) references `comunicaciones_oficiales` (`id`) on delete cascade;

alter table `comunicacion_anexos` add index `comunicacion_anexos_archivo_id_index`(`archivo_id`);

alter table `comunicaciones_oficiales` add `dd_estado` varchar(50) null;

alter table `comunicaciones_oficiales` add `dd_estado_en` timestamp null;

create table `docdigital_evidencias` (`id` bigint unsigned not null auto_increment primary key, `creado_en` timestamp null, `ambiente` varchar(10) not null, `metodo` varchar(8) not null, `ruta` varchar(255) not null, `consulta` text null, `solicitud` longtext null, `http_status` smallint unsigned null, `respuesta` longtext null, `duracion_ms` int unsigned null, `usuario_id` bigint unsigned null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

alter table `docdigital_evidencias` add index `docdigital_evidencias_creado_en_index`(`creado_en`);

SET FOREIGN_KEY_CHECKS = 1;
