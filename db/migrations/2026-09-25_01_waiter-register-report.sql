-- =============================================================================
-- Migration: permission for the Waiter's Register Report.
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: one permission module
-- and its "view" function, plus a grant to the roles that already hold the
-- ordinary Register Report, and to waiters. No existing row is modified and
-- nothing is dropped. Idempotent (safe to re-run).
--
-- WHY  A waiter needs to see their own takings for one register session, and a
--   manager needs to pull the same report for any waiter. Both use one screen,
--   which decides what it will show from this permission PLUS the role: a
--   waiter is pinned to themselves whatever the form says, and that is enforced
--   in the controller, not here. This grant only decides who sees the menu item.
--
-- WHAT IT ADDS
--   tbl_access       module 'waiter_register_report' in the Reports group
--                    (main_module_id 8, as register_report uses), with one
--                    'view' function beneath it.
--   tbl_role_access  the same grant every role holding the Register Report
--                    (parent 159 / child 160) already has, so nobody re-ticks a
--                    box for a report they were already entitled to - plus
--                    waiters, who are the people this exists for.
--
-- Rollback:
--   DELETE ra FROM tbl_role_access ra JOIN tbl_access m ON m.id = ra.access_parent_id
--     WHERE m.module_name = 'waiter_register_report';
--   DELETE c FROM tbl_access c JOIN tbl_access m ON m.id = c.parent_id
--     WHERE m.module_name = 'waiter_register_report';
--   DELETE FROM tbl_access WHERE module_name = 'waiter_register_report';
-- =============================================================================

-- the parent module row, sitting in the Reports group beside register_report
INSERT INTO `tbl_access` (`module_name`, `function_name`, `label_name`, `parent_id`, `main_module_id`, `del_status`)
SELECT 'waiter_register_report', '', 'waiter_register_report', 0, 8, 'Live'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM `tbl_access`
                                   WHERE module_name = 'waiter_register_report' AND parent_id = 0) a);

SET @wrr_parent := (SELECT id FROM `tbl_access`
                     WHERE module_name = 'waiter_register_report' AND parent_id = 0 LIMIT 1);

-- its single function
INSERT INTO `tbl_access` (`module_name`, `function_name`, `label_name`, `parent_id`, `main_module_id`, `del_status`)
SELECT '', 'view', 'view', @wrr_parent, NULL, 'Live'
  FROM DUAL
 WHERE @wrr_parent IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM `tbl_access`
                                   WHERE parent_id = @wrr_parent AND function_name = 'view') b);

SET @wrr_child := (SELECT id FROM `tbl_access`
                    WHERE parent_id = @wrr_parent AND function_name = 'view' LIMIT 1);

-- every role that can already see the Register Report gets this one too
INSERT INTO `tbl_role_access` (`role_id`, `access_parent_id`, `access_child_id`, `del_status`)
SELECT DISTINCT src.role_id, @wrr_parent, @wrr_child, 'Live'
  FROM (SELECT role_id FROM `tbl_role_access`
         WHERE access_parent_id = 159 AND access_child_id = 160
           AND (del_status IS NULL OR del_status = 'Live')) src
 WHERE @wrr_child IS NOT NULL
   AND src.role_id NOT IN (SELECT role_id FROM (SELECT role_id FROM `tbl_role_access`
                                                 WHERE access_child_id = @wrr_child) held);

-- and waiters, who are the people this report exists for
INSERT INTO `tbl_role_access` (`role_id`, `access_parent_id`, `access_child_id`, `del_status`)
SELECT ro.id, @wrr_parent, @wrr_child, 'Live'
  FROM `tbl_roles` ro
 WHERE @wrr_child IS NOT NULL
   AND ro.del_status = 'Live'
   AND LOWER(TRIM(ro.role_name)) IN ('waiter', 'waitress')
   AND ro.id NOT IN (SELECT role_id FROM (SELECT role_id FROM `tbl_role_access`
                                           WHERE access_child_id = @wrr_child) held2);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM tbl_access WHERE module_name = 'waiter_register_report' AND parent_id = 0) = 1
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id = c.parent_id
          WHERE m.module_name = 'waiter_register_report' AND c.function_name = 'view') = 1
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_access m ON m.id = ra.access_parent_id
          WHERE m.module_name = 'waiter_register_report') >= 1
  THEN 'PASS' ELSE 'FAIL' END AS result;

SELECT r.role_name AS roles_granted
  FROM tbl_role_access ra
  JOIN tbl_roles r ON r.id = ra.role_id
  JOIN tbl_access m ON m.id = ra.access_parent_id
 WHERE m.module_name = 'waiter_register_report'
 ORDER BY r.role_name;
