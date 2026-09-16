-- =============================================================================
-- Migration: module switches (H0 of the Hotel Operations add-on).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive: one new table, one
-- permission row with its child and its default grant. No existing table is
-- altered; no existing row is modified.
--
-- WHAT
--   tbl_modules          one row per switchable add-on. is_enabled is read PER
--                        REQUEST by irModuleEnabled() (never snapshotted into the
--                        session, unlike permissions), so flipping a switch takes
--                        effect on the next request - no re-login, no deploy.
--                        settings is reserved (JSON) for per-module options.
--   'hotel' row          the Hotel Operations add-on, OFF until switched on.
--   tbl_access 'modules' the Settings > Modules screen (function 'update'),
--                        granted to Admin only. The code looks the row up by
--                        module_name (irAccessModuleId), so its id may differ
--                        between installs.
--
-- Permissions are snapshotted at LOGIN - the Admin must sign out and in once
-- to see the Modules screen. The module switch itself needs no re-login.
--
-- SAFE DEGRADATION: without this migration irModuleEnabled() answers FALSE for
-- everything (no table -> nothing switched on) and the Modules screen says so.
--
-- Rollback: DROP TABLE tbl_modules; DELETE the tbl_role_access rows whose
--           access_child_id belongs to the 'modules' access row; DELETE the
--           'modules' rows from tbl_access.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tbl_modules` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `module_key`  VARCHAR(40) NOT NULL,
  `is_enabled`  TINYINT NOT NULL DEFAULT 0,
  `settings`    TEXT NULL,
  `updated_by`  INT NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_key` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `tbl_modules` (`module_key`, `is_enabled`) VALUES ('hotel', 0);

INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT 'modules', '', 'modules', 0, 3, 'Live'
 WHERE NOT EXISTS (SELECT 1 FROM tbl_access WHERE module_name='modules' AND parent_id=0);

INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT '', 'update', 'update', m.id, NULL, 'Live'
  FROM tbl_access m
 WHERE m.module_name='modules' AND m.parent_id=0
   AND NOT EXISTS (SELECT 1 FROM tbl_access c WHERE c.parent_id=m.id AND c.function_name='update');

INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, m.id, c.id, 'Live'
  FROM tbl_roles r
  JOIN tbl_access m ON m.module_name='modules' AND m.parent_id=0
  JOIN tbl_access c ON c.parent_id=m.id AND c.function_name='update'
 WHERE r.role_name='Admin' AND r.del_status='Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access ra WHERE ra.role_id=r.id AND ra.access_child_id=c.id);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_modules') = 1
    AND (SELECT COUNT(*) FROM tbl_modules WHERE module_key='hotel') = 1
    AND (SELECT COUNT(*) FROM tbl_access WHERE module_name='modules' AND parent_id=0) = 1
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id=c.parent_id WHERE m.module_name='modules' AND c.function_name='update') = 1
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_access c ON c.id=ra.access_child_id JOIN tbl_access m ON m.id=c.parent_id
          WHERE m.module_name='modules' AND c.function_name='update') >= 1
  THEN 'PASS' ELSE 'FAIL' END AS result;
