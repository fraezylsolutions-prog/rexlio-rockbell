-- =============================================================================
-- Migration: table-first waiter flow - foundation (Stage 1).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive: two defaulted columns, one
-- permission row and its default grants. No existing row is modified.
--
-- WHAT
--   tbl_tables.created_at     when an auto table was created; an auto table with
--                             no order after four hours is released by the panel.
--   tbl_tables.auto_created   1 = created by "+ New Table" on the POS; released
--                             (soft-deleted) automatically when its order is
--                             completed, cancelled or absorbed by a merge.
--                             Admin-created tables stay 0 and are never released.
--   tbl_users.ir_table_seq    per-waiter counter behind the auto name
--                             "<first name>-0001". Issued server-side, atomically
--                             (Sale::createWaiterTable), so two tills can never
--                             produce the same number - the same reasoning as the
--                             device tags for order numbers.
--   act_on_any_running_order  permission (parent 372, next to
--                             view_all_running_orders): may Modify / Invoice /
--                             Split / Merge / Print / Cancel a running order that
--                             is not their own. Seeing other users' orders stays
--                             governed by view_all_running_orders.
--
-- Granted to Admin, Manager and Cashier by default, same as
-- view_all_running_orders. Switchable per role on the existing role screen.
-- Permissions are snapshotted into the session at LOGIN - sign out and in.
--
-- SAFE DEGRADATION: without this, "+ New Table" answers "not available" and the
-- POS behaves as before; nothing else changes.
--
-- Rollback: DELETE the tbl_role_access rows then the tbl_access row;
--           ALTER TABLE tbl_tables DROP COLUMN auto_created, DROP COLUMN created_at;
--           ALTER TABLE tbl_users DROP COLUMN ir_table_seq;
-- =============================================================================

ALTER TABLE `tbl_tables` ADD COLUMN `auto_created` TINYINT NOT NULL DEFAULT 0,
                         ADD COLUMN `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `tbl_users`  ADD COLUMN `ir_table_seq` INT NOT NULL DEFAULT 0;

INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
VALUES ('', 'act_on_any_running_order', 'act_on_any_running_order', 372, NULL, 'Live');

INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, 372, a.id, 'Live'
FROM tbl_roles r, tbl_access a
WHERE a.function_name = 'act_on_any_running_order' AND a.parent_id = 372
  AND r.role_name IN ('Admin', 'Manager', 'Cashier') AND r.del_status = 'Live';

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_tables' AND COLUMN_NAME IN ('auto_created','created_at')) = 2
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_users' AND COLUMN_NAME='ir_table_seq') = 1
    AND (SELECT COUNT(*) FROM tbl_access WHERE function_name='act_on_any_running_order' AND parent_id=372) = 1
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_access a ON a.id=ra.access_child_id
          WHERE a.function_name='act_on_any_running_order') >= 1
  THEN 'PASS' ELSE 'FAIL' END AS result;
