-- ============================================================================
-- OPCIONAL: triggers que bloquean UPDATE/DELETE en audit_log y documento_versiones.
-- Requiere el privilegio TRIGGER (muchos hostings compartidos no lo conceden). Si falla, omítalo y
-- aplique los GRANT sin UPDATE/DELETE descritos en el README. Importar tras 01_schema.sql.
-- GENERADO con: php scripts/generate-schema-sql.php --triggers > database/sql/03_triggers_opcional.sql
-- ============================================================================
CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON `audit_log` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log es de solo lectura (append-only)';

CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON `audit_log` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log es de solo lectura (append-only)';

CREATE TRIGGER documento_versiones_no_update BEFORE UPDATE ON `documento_versiones` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'documento_versiones es de solo lectura (append-only)';

CREATE TRIGGER documento_versiones_no_delete BEFORE DELETE ON `documento_versiones` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'documento_versiones es de solo lectura (append-only)';
