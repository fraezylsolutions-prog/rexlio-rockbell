-- =============================================================================
-- Migration: running-order versioning for cross-till "Open here".
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive: three nullable/defaulted
-- columns on tbl_kitchen_sales. No existing row is modified; every existing
-- running order simply starts at version 1.
--
-- WHY: a running order can now be opened ("adopted") on a second till from the
-- Running Order screen. Two tills then each hold a copy. Without a version the
-- server applied whichever push arrived last, silently overwriting the other
-- till's work. Now every server-side change to the row (modify, adoption)
-- increments `version`; a till pushes the version it acted on and a stale push
-- is refused, the till refreshed. adopted_by / adopted_at record the last
-- adoption for support and audit.
--
-- No permission rows: adoption reuses the Running Order screen's visibility
-- rule (own order, or the view_all_running_orders permission).
--
-- SAFE DEGRADATION: if this is not applied, Sale/getOrderForAdoption and the
-- version checks fail closed (no adoption, no stale refusals) and the POS
-- behaves exactly as before.
--
-- Rollback: ALTER TABLE tbl_kitchen_sales DROP COLUMN version,
--           DROP COLUMN adopted_by, DROP COLUMN adopted_at;
-- =============================================================================

ALTER TABLE `tbl_kitchen_sales`
  ADD COLUMN `version`    INT      NOT NULL DEFAULT 1,
  ADD COLUMN `adopted_by` INT      NULL DEFAULT NULL,
  ADD COLUMN `adopted_at` DATETIME NULL DEFAULT NULL;

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_kitchen_sales'
        AND COLUMN_NAME IN ('version','adopted_by','adopted_at')) = 3
  THEN 'PASS' ELSE 'FAIL' END AS result;
