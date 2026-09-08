-- =============================================================================
-- Migration: Admin Register Management (force-close a register on someone
-- else's behalf), plus its permission and audit accountability columns.
--
-- APPLY TO LIVE SEPARATELY AND BEFORE THE CODE DEPLOY, AFTER A VERIFIED BACKUP.
-- Purely additive (new nullable columns, new permission rows) - existing code
-- and existing rows are unaffected. Safe to run while the site is live.
--
-- Rollback: DROP COLUMN system_closing_balance, force_closed_by,
-- force_close_reason from tbl_register; DELETE the two rows this adds to
-- tbl_access and tbl_role_access (see the SELECTs at the bottom for their ids).
-- =============================================================================

ALTER TABLE tbl_register
  ADD COLUMN system_closing_balance FLOAT NULL AFTER closing_balance,
  ADD COLUMN force_closed_by INT NULL AFTER register_status,
  ADD COLUMN force_close_reason TEXT NULL AFTER force_closed_by;

-- Permission: configurable, not hardcoded to a role name. Child of the existing
-- "register" module (id 376) so it appears in the Role permission screen
-- alongside "open" automatically - that screen groups by parent_id with no
-- code change needed on its side.
INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
VALUES ('', 'manage_registers', 'manage_registers', 376, NULL, 'Live');

-- Grant to Admin (role_id 2) and Manager (role_id 3) by default, matching the
-- Cashier/Admin/Manager grant pattern already used for "open" (376/377).
-- Admin does not strictly need this row (checkAccess short-circuits role=='Admin'),
-- but every other permission in this system carries an explicit Admin row too,
-- so this stays consistent rather than being a special case.
INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, 376, a.id, 'Live'
FROM tbl_roles r, tbl_access a
WHERE a.function_name = 'manage_registers' AND a.parent_id = 376
  AND r.role_name IN ('Admin', 'Manager') AND r.del_status = 'Live';

-- =============================================================================
-- VERIFICATION - run these after applying, both should return non-empty rows.
-- =============================================================================
SELECT id, function_name, label_name, parent_id FROM tbl_access WHERE function_name = 'manage_registers';
SELECT ra.id, r.role_name, ra.access_parent_id, ra.access_child_id
FROM tbl_role_access ra JOIN tbl_roles r ON r.id = ra.role_id
JOIN tbl_access a ON a.id = ra.access_child_id
WHERE a.function_name = 'manage_registers';

-- PASS/FAIL, per db/migrations/_TEMPLATE.sql. If this prints FAIL, STOP and do
-- not deploy the code: the force-close screen writes to the three new columns
-- and its menu entry is gated on the permission row.
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_register'
        AND COLUMN_NAME IN ('system_closing_balance','force_closed_by','force_close_reason')) = 3
    AND (SELECT COUNT(*) FROM tbl_access WHERE function_name='manage_registers' AND parent_id=376) = 1
  THEN 'PASS' ELSE 'FAIL' END AS result;
