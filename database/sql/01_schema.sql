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

create table `tipos_documento` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(30) not null, `nombre` varchar(120) not null, `prefijo` varchar(10) not null, `firma_alcalde` tinyint(1) not null default '0', `firmas_agil` tinyint unsigned not null default '1', `firmas_clasico` tinyint unsigned not null default '2', `activo` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

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

create table `expedientes` (`id` bigint unsigned not null auto_increment primary key, `codigo` varchar(40) not null, `solicitud_id` bigint unsigned null, `unidad_id` bigint unsigned not null, `responsable_id` bigint unsigned not null, `estado` varchar(15) not null default 'abierto', `metadatos` json not null, `motivo_reapertura` text null, `cerrado_en` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' engine = InnoDB;

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

SET FOREIGN_KEY_CHECKS = 1;
