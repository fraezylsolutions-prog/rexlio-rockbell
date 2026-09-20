-- =============================================================================
-- Migration: Settings > Modules - "Automatic waiter logout after order placement"
--            becomes a business-wide switch (default ON = today's behaviour).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: ONE row in tbl_modules
-- (module_key 'waiter_auto_logout', is_enabled 1). No table changes, no
-- existing row modified. Idempotent (safe to re-run).
-- Requires 2026-09-16_01_modules.sql (tbl_modules) to be applied first.
--
-- WHY  Auto-logout is safe at the moment of placing (it never fires offline
--   and re-checks the server before leaving), but it cannot protect against
--   the connection dropping AFTER the logout and BEFORE the waiter signs back
--   in - login is server-only, so the waiter is stuck out. On an unreliable
--   network the Admin can now switch the feature off for the whole venue under
--   Settings > Modules; with it off, placing an order never logs anyone out.
--
-- DEFAULT  ON. Until the row exists the code also treats the switch as ON, so
--   deploying the code before this migration changes nothing.
-- Read per request: a flip applies the next time the POS screen is opened.
-- Rollback: DELETE FROM tbl_modules WHERE module_key='waiter_auto_logout';
--           (the code then behaves as ON, exactly as before).
-- =============================================================================

INSERT IGNORE INTO `tbl_modules` (`module_key`, `is_enabled`) VALUES ('waiter_auto_logout', 1);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM tbl_modules WHERE module_key = 'waiter_auto_logout') = 1
  THEN 'PASS' ELSE 'FAIL' END AS result;
