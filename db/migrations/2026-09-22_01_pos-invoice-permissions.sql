-- =============================================================================
-- Migration: POS - Quick Invoice / Print Last Invoice permissions (P5-3).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Data only: grants tbl_role_access rows
-- and relabels two tbl_access rows. No table changes. Idempotent (safe to re-run).
--
-- WHAT WAS WRONG  The POS module (73) has TWO functions labelled 'direct_invoice'
--   ("Quick Invoice" on the Roles screen): pos_24 gates the real Quick Invoice
--   button on the sale screen; pos_25 gates an element that does not exist in the
--   view. Indistinguishable on the Roles screen, pos_25 was granted to everyone
--   and pos_24 to no one - so every non-Admin got "You haven't permission" on
--   Quick Invoice. Print Last Invoice (pos_13) was also withheld from Waiters
--   who hold Create Invoice (pos_11).
--
-- WHAT THIS DOES
--   1. grants pos_24 to every role that holds pos_25 (the intent when the box was ticked)
--   2. grants pos_13 to every role that holds pos_11 (may create -> may reprint the last)
--   3. relabels pos_24 -> 'quick_invoice_button' and pos_25 -> 'quick_invoice_unused'
--      so the Roles screen shows "Quick Invoice (sale screen button)" and
--      "Quick Invoice (unused - kept for compatibility)". The code still reads the
--      function names; only the display label changes.
--
-- Permissions are snapshotted at LOGIN - sign out and in after applying.
-- Rollback: DELETE the tbl_role_access rows added (pos_24 / pos_13 for the roles
--           listed by the SELECT below), and set the two label_name values back
--           to 'direct_invoice'.
-- =============================================================================

-- 1. pos_24 wherever pos_25 is granted
INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT ra.role_id, 73, c24.id, 'Live'
  FROM tbl_role_access ra
  JOIN tbl_access c25 ON c25.id = ra.access_child_id AND c25.parent_id = 73 AND c25.function_name = 'pos_25'
  JOIN tbl_access c24 ON c24.parent_id = 73 AND c24.function_name = 'pos_24'
 WHERE ra.del_status = 'Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access x WHERE x.role_id = ra.role_id AND x.access_child_id = c24.id);

-- 2. pos_13 wherever pos_11 is granted
INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT ra.role_id, 73, c13.id, 'Live'
  FROM tbl_role_access ra
  JOIN tbl_access c11 ON c11.id = ra.access_child_id AND c11.parent_id = 73 AND c11.function_name = 'pos_11'
  JOIN tbl_access c13 ON c13.parent_id = 73 AND c13.function_name = 'pos_13'
 WHERE ra.del_status = 'Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access x WHERE x.role_id = ra.role_id AND x.access_child_id = c13.id);

-- 3. distinguishable labels
UPDATE tbl_access SET label_name = 'quick_invoice_button' WHERE parent_id = 73 AND function_name = 'pos_24';
UPDATE tbl_access SET label_name = 'quick_invoice_unused' WHERE parent_id = 73 AND function_name = 'pos_25';

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM tbl_access WHERE parent_id = 73 AND function_name = 'pos_24' AND label_name = 'quick_invoice_button') = 1
    AND (SELECT COUNT(*) FROM tbl_access WHERE parent_id = 73 AND function_name = 'pos_25' AND label_name = 'quick_invoice_unused') = 1
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_access c25 ON c25.id = ra.access_child_id AND c25.function_name = 'pos_25' AND c25.parent_id = 73
          WHERE ra.del_status = 'Live' AND NOT EXISTS (SELECT 1 FROM tbl_role_access x JOIN tbl_access c24 ON c24.id = x.access_child_id AND c24.function_name = 'pos_24' AND c24.parent_id = 73 WHERE x.role_id = ra.role_id)) = 0
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_access c11 ON c11.id = ra.access_child_id AND c11.function_name = 'pos_11' AND c11.parent_id = 73
          WHERE ra.del_status = 'Live' AND NOT EXISTS (SELECT 1 FROM tbl_role_access x JOIN tbl_access c13 ON c13.id = x.access_child_id AND c13.function_name = 'pos_13' AND c13.parent_id = 73 WHERE x.role_id = ra.role_id)) = 0
  THEN 'PASS' ELSE 'FAIL' END AS result;

-- Who holds what now (informational)
SELECT a.function_name, a.label_name, GROUP_CONCAT(r.role_name ORDER BY r.role_name) AS roles
  FROM tbl_access a
  LEFT JOIN tbl_role_access ra ON ra.access_child_id = a.id AND ra.del_status = 'Live'
  LEFT JOIN tbl_roles r ON r.id = ra.role_id
 WHERE a.parent_id = 73 AND a.function_name IN ('pos_11', 'pos_13', 'pos_24', 'pos_25')
 GROUP BY a.id ORDER BY a.function_name;
