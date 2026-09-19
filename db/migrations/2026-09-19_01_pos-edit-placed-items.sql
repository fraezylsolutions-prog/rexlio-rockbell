-- =============================================================================
-- Migration: POS - permission to edit PLACED items when modifying an order (P2).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: one NEW tbl_access
-- function under the POS module (73), granted to Admin and Manager. No table
-- changes, no existing row modified. Idempotent (safe to re-run).
--
-- WHAT IT GATES  When an order is opened with Modify, the items already placed
--   are shown but LOCKED for anyone without this function: no edit, no removal,
--   quantity cannot go below what was placed (adding more is still allowed).
--   Holders get today's behaviour, still subject to the kitchen-status checks
--   and to pos_7 (delete item when modifying). Newly added items are always
--   editable. Business rule 2026-09-19: Admin and Manager only; Cashier and
--   Waiter not - grant under Settings > Roles if wanted.
--
-- NOTE  pos_7 was NOT reused: on live data it is granted to Waiter and not to
--   Cashier, and the waiter app bypasses it in code.
--
-- Permissions are snapshotted at LOGIN - sign out and in after applying.
-- Rollback: DELETE the tbl_role_access rows for this function, then the
--           tbl_access row (parent_id 73, function_name 'pos_26').
-- =============================================================================

INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT '', 'pos_26', 'Edit_Placed_Items_When_Modifying_Order', 73, NULL, 'Live'
 WHERE NOT EXISTS (SELECT 1 FROM tbl_access WHERE parent_id = 73 AND function_name = 'pos_26');

INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, 73, c.id, 'Live'
  FROM tbl_roles r
  JOIN tbl_access c ON c.parent_id = 73 AND c.function_name = 'pos_26'
 WHERE r.role_name IN ('Admin', 'Manager') AND r.del_status = 'Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access ra WHERE ra.role_id = r.id AND ra.access_child_id = c.id);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM tbl_access WHERE parent_id = 73 AND function_name = 'pos_26') = 1
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_roles r ON r.id = ra.role_id JOIN tbl_access c ON c.id = ra.access_child_id
          WHERE c.parent_id = 73 AND c.function_name = 'pos_26' AND r.role_name = 'Admin') >= 1
  THEN 'PASS' ELSE 'FAIL' END AS result;
