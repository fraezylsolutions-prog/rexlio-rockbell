-- =============================================================================
-- Migration: Hotel Operations add-on - reports permission (H6).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: one NEW tbl_access
-- module row (hotel_reports) with one function (view), granted to Admin and
-- Manager. No table changes. Idempotent (safe to re-run).
--
-- The hotel reports (Value Generated, Occupancy, Stays, Housekeeping
-- Productivity) live in the Hotel controller so the module switch gates them
-- too; the Reports menu links there. One permission covers all of them.
-- The id is looked up BY NAME at runtime (irAccessModuleId('hotel_reports')).
--
-- Rollback: DELETE the tbl_role_access rows whose access_child_id belongs to
--           module hotel_reports; DELETE its child row, then the module row.
-- =============================================================================

INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT 'hotel_reports', '', 'hotel_reports', 0, 4, 'Live'
 WHERE NOT EXISTS (SELECT 1 FROM tbl_access WHERE module_name = 'hotel_reports' AND parent_id = 0);

INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT '', 'view', 'view', m.id, NULL, 'Live'
  FROM tbl_access m
 WHERE m.module_name = 'hotel_reports' AND m.parent_id = 0
   AND NOT EXISTS (SELECT 1 FROM tbl_access c WHERE c.parent_id = m.id AND c.function_name = 'view');

INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, m.id, c.id, 'Live'
  FROM tbl_roles r
  JOIN tbl_access m ON m.module_name = 'hotel_reports' AND m.parent_id = 0
  JOIN tbl_access c ON c.parent_id = m.id AND c.function_name = 'view'
 WHERE r.role_name IN ('Admin', 'Manager') AND r.del_status = 'Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access ra WHERE ra.role_id = r.id AND ra.access_child_id = c.id);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM tbl_access WHERE module_name = 'hotel_reports' AND parent_id = 0) = 1
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id = c.parent_id WHERE m.module_name = 'hotel_reports' AND c.function_name = 'view') = 1
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_roles r ON r.id = ra.role_id JOIN tbl_access c ON c.id = ra.access_child_id JOIN tbl_access m ON m.id = c.parent_id
          WHERE m.module_name = 'hotel_reports' AND r.role_name = 'Admin') >= 1
  THEN 'PASS' ELSE 'FAIL' END AS result;
